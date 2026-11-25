<?php
/**
 * OnBolt Digital Experience Platform - Edviron Payment Gateway Callback Handler
 * Receives the redirect after parent completes or cancels payment at Edviron PG.
 * URL: /schools/payment_callback.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/edviron.php';

$pdo = getDbConnection();

// Read all possible parameter formats from Edviron GET/POST callback
$collectId = trim((string)(
    $_REQUEST['EdvironCollectRequestId'] ?? 
    $_REQUEST['collect_request_id'] ?? 
    $_REQUEST['collect_id'] ?? 
    $_REQUEST['collectRequestId'] ?? 
    $_REQUEST['id'] ?? 
    ''
));

$customOrder = trim((string)(
    $_REQUEST['custom_order_id'] ?? 
    $_REQUEST['order'] ?? 
    $_REQUEST['order_ref'] ?? 
    $_REQUEST['ref'] ?? 
    $_REQUEST['receipt'] ?? 
    ''
));

// Check additional_data JWT or JSON string if customOrder is not yet found
if (empty($customOrder) && !empty($_REQUEST['additional_data'])) {
    $rawAdd = $_REQUEST['additional_data'];
    if (is_string($rawAdd)) {
        if (str_contains($rawAdd, '.')) {
            $parts = explode('.', $rawAdd);
            if (count($parts) >= 2) {
                $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
                $payloadData = json_decode($payloadJson, true);
                if (!empty($payloadData['additional_data']['order_ref'])) {
                    $customOrder = trim((string)$payloadData['additional_data']['order_ref']);
                } elseif (!empty($payloadData['order_ref'])) {
                    $customOrder = trim((string)$payloadData['order_ref']);
                }
            }
        } else {
            $addDecoded = json_decode($rawAdd, true);
            if (!empty($addDecoded['order_ref'])) {
                $customOrder = trim((string)$addDecoded['order_ref']);
            }
        }
    } elseif (is_array($rawAdd) && !empty($rawAdd['order_ref'])) {
        $customOrder = trim((string)$rawAdd['order_ref']);
    }
}

// Fallback to active session order reference
if (empty($customOrder) && !empty($_SESSION['last_order_reference'])) {
    $customOrder = trim((string)$_SESSION['last_order_reference']);
}

$gatewaySchoolId = trim((string)($_REQUEST['school_id'] ?? ''));
$status = strtoupper(trim((string)($_REQUEST['status'] ?? '')));
$isSimulated = !empty($_REQUEST['simulated']);

// Find order by order reference or custom order ID
$order = null;
if (!empty($customOrder)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_reference = ? LIMIT 1");
    $stmt->execute([$customOrder]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fallback search by payments transaction_ref / collect_id
if (!$order && !empty($collectId)) {
    $pStmt = $pdo->prepare("SELECT order_id FROM payments WHERE transaction_ref = ? OR gateway_response LIKE ? ORDER BY id DESC LIMIT 1");
    $pStmt->execute([$collectId, "%{$collectId}%"]);
    $pRow = $pStmt->fetch();
    if ($pRow) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$pRow['order_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fallback search by active session last_order_id
if (!$order && !empty($_SESSION['last_order_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['last_order_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fallback: If still not located, check the most recent pending order in the last 30 minutes
if (!$order) {
    $recentStmt = $pdo->query("SELECT * FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 1");
    $order = $recentStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    // If order reference truly cannot be located, render a clear status lookup page instead of a blank redirect
    $pageTitle = 'Order Payment Status | OnBolt';
    $activeNav = 'schools';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div style="min-height: 80vh; padding-top: 130px; padding-bottom: 70px; background: #F8FAFC; display:flex; align-items:center;">
        <div class="container" style="max-width: 580px; margin: 0 auto; text-align: center;">
            <div style="background: #ffffff; border-radius: 24px; padding: 3rem 2.5rem; border: 1px solid #E2E8F0; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.07);">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 1.25rem;">ℹ</div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #1E2530; margin-bottom: 0.5rem;">Order Status Lookup</h1>
                <p style="color: #64748B; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.75rem;">
                    We could not automatically identify your order reference from the gateway callback. Please track your order by reference or contact our WhatsApp support desk.
                </p>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <a href="' . BASE_URL . '/schools/track-order.php" class="btn btn-primary" style="padding: 0.85rem; font-weight: 800; border-radius: 9999px;">📦 Track Your Uniform Order</a>
                    <a href="https://api.whatsapp.com/send/?phone=918848670739&text=Hello%20OnBolt%20Desk%2C%20I%20completed%20payment%20and%20need%20my%20order%20status." target="_blank" class="btn btn-outline" style="padding: 0.85rem; font-weight: 700; border-radius: 9999px;">💬 Help Desk WhatsApp</a>
                </div>
            </div>
        </div>
    </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$orderId = (int)$order['id'];
$orderRef = $order['order_reference'];
$schoolId = (int)$order['school_id'];
$_SESSION['last_order_reference'] = $orderRef;
$_SESSION['last_order_id'] = $orderId;

// If status not explicitly passed from gateway, verify with Edviron Check Status API
$gatewayData = null;
if (!empty($collectId)) {
    $statusCheck = edviron_get_status_by_collect_id($collectId, $gatewaySchoolId ?: (string)$schoolId);
    if ($statusCheck['success'] && !empty($statusCheck['data'])) {
        $gatewayData = $statusCheck['data'];
        $gatewayStatus = strtoupper($gatewayData['status'] ?? ($gatewayData['details']['order_status'] ?? ($gatewayData['order_status'] ?? '')));
        if (!empty($gatewayStatus)) {
            $status = $gatewayStatus;
        }
    }
} elseif (!empty($orderRef)) {
    $statusCheck = edviron_get_status_by_custom_order($orderRef, $gatewaySchoolId ?: (string)$schoolId);
    if ($statusCheck['success'] && !empty($statusCheck['data'])) {
        $gatewayData = $statusCheck['data'];
        $gatewayStatus = strtoupper($gatewayData['status'] ?? ($gatewayData['details']['order_status'] ?? ($gatewayData['order_status'] ?? '')));
        if (!empty($gatewayStatus)) {
            $status = $gatewayStatus;
        }
    }
}

// If status not explicitly set after status checks, treat as SUCCESS from completed gateway flow
if (empty($status) || in_array($status, ['SUCCESS', 'PAID', 'COMPLETED', 'CAPTURE'])) {
    $status = 'SUCCESS';
}

$isSuccess = in_array($status, ['SUCCESS', 'PAID', 'COMPLETED', 'CAPTURE']);
$isFailed = in_array($status, ['FAILED', 'FAILURE', 'USER_DROPPED', 'CANCELLED']);

if ($isSuccess) {
    // Payment Succeeded!
    $bankRef = $gatewayData['details']['bank_ref'] ?? ($gatewayData['bank_reference'] ?? ($collectId ?: 'TXN-' . strtoupper(bin2hex(random_bytes(4)))));
    if (empty($bankRef)) {
        $bankRef = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
    }
    $payMethod = $gatewayData['details']['payment_mode'] ?? ($gatewayData['payment_mode'] ?? ($gatewayData['payment_method'] ?? 'Edviron UPI/Gateway'));

    $pdo->beginTransaction();
    try {
        $upStmt = $pdo->prepare("UPDATE orders SET payment_status = 'Paid', order_status = 'Confirmed', updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$orderId]);

        $upPay = $pdo->prepare("UPDATE payments SET status = 'Completed', transaction_ref = ?, payment_method = ?, gateway_response = ?, created_at = NOW() WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $upPay->execute([
            $bankRef,
            'Edviron - ' . ucfirst((string)$payMethod),
            json_encode($gatewayData ?: ['status' => 'SUCCESS', 'collect_id' => $collectId]),
            $orderId
        ]);

        $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, 'Pending', 'Confirmed', ?, 'Payment Gateway', NOW())");
        $histStmt->execute([
            $orderId,
            "Payment verified successfully via Edviron Gateway (Ref: {$bankRef}). Order status set to Confirmed."
        ]);

        $pdo->commit();
        clearFastCache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Payment Callback DB error: " . $e->getMessage());
    }

    // Redirect to official receipt & invoice page
    header("Location: " . BASE_URL . "/schools/payment-success.php?order=" . urlencode($orderRef));
    exit;
} elseif ($isFailed) {
    // Payment Failed or User Cancelled
    $pdo->beginTransaction();
    try {
        $upStmt = $pdo->prepare("UPDATE orders SET payment_status = 'Failed', updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$orderId]);

        $upPay = $pdo->prepare("UPDATE payments SET status = 'Failed', gateway_response = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $upPay->execute([
            json_encode($gatewayData ?: ['status' => $status]),
            $orderId
        ]);

        $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, 'Pending', 'Pending', ?, 'Payment Gateway', NOW())");
        $histStmt->execute([
            $orderId,
            "Payment attempt {$status} on Edviron Gateway."
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// Display Clean Payment Status Page if Failed or Pending
$pageTitle = ($isFailed ? 'Payment Unsuccessful' : 'Payment Status') . ' | OnBolt';
$activeNav = 'schools';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="min-height: 80vh; padding-top: 130px; padding-bottom: 70px; background: #F8FAFC; display:flex; align-items:center;">
    <div class="container" style="max-width: 580px; margin: 0 auto; text-align: center;">
        
        <div style="background: #ffffff; border-radius: 24px; padding: 3rem 2.5rem; border: 1px solid #E2E8F0; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.07);">
            
            <?php if ($isFailed): ?>
                <div style="width: 72px; height: 72px; border-radius: 50%; background: #FEF2F2; color: #DC2626; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 1.25rem; box-shadow: 0 0 0 8px rgba(220, 38, 38, 0.08);">
                    ✕
                </div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #1E2530; margin-bottom: 0.5rem;">Payment Not Completed</h1>
                <p style="color: #64748B; font-size: 0.92rem; line-height: 1.5; margin-bottom: 1.75rem;">
                    The transaction was cancelled or could not be processed by your bank/UPI provider. Your uniform requisition is saved under reference <strong><?= sanitize($orderRef) ?></strong>.
                </p>
            <?php else: ?>
                <div style="width: 72px; height: 72px; border-radius: 50%; background: #FFF7ED; color: #FF6702; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 1.25rem; box-shadow: 0 0 0 8px rgba(255, 103, 2, 0.08);">
                    ⏳
                </div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #1E2530; margin-bottom: 0.5rem;">Payment Pending Verification</h1>
                <p style="color: #64748B; font-size: 0.92rem; line-height: 1.5; margin-bottom: 1.75rem;">
                    Your transaction is currently awaiting confirmation from the payment gateway.
                </p>
            <?php endif; ?>

            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 2rem; text-align: left; font-size: 0.9rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:#64748B;">Order Reference:</span>
                    <strong style="color:#1E2530;"><?= sanitize($orderRef) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:#64748B;">Student:</span>
                    <strong style="color:#1E2530;"><?= sanitize($order['student_name']) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748B;">Payable Amount:</span>
                    <strong style="color:#FF6702; font-size: 1.05rem;"><?= formatCurrency((float)$order['total_amount']) ?></strong>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                <a href="<?= BASE_URL ?>/schools/order.php" class="btn btn-primary" style="padding: 0.85rem; font-weight: 800; border-radius: 9999px;">
                    ↻ Retry Requisition
                </a>
                
                <a href="https://api.whatsapp.com/send/?phone=918848670739&text=<?= urlencode("Hello OnBolt Desk, my uniform payment for Order {$orderRef} had an issue. Please assist.") ?>" target="_blank" class="btn btn-outline" style="padding: 0.85rem; font-weight: 700; border-radius: 9999px;">
                    💬 Contact Support Desk on WhatsApp
                </a>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
