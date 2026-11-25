<?php
/**
 * OnBolt Digital Experience Platform - Edviron Payment Gateway Webhook Receiver
 * Asynchronously processes real-time transaction updates from Edviron servers.
 * URL: /api/edviron_webhook.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/edviron.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook payload']);
    exit;
}

$collectId   = trim((string)($data['EdvironCollectRequestId'] ?? ($data['collect_id'] ?? ($data['collect_request_id'] ?? ($data['collectRequestId'] ?? '')))));
$customOrder = trim((string)($data['custom_order_id'] ?? ($data['order'] ?? ($data['order_ref'] ?? ($data['receipt'] ?? '')))));

if (empty($customOrder) && !empty($data['additional_data'])) {
    $rawAdd = $data['additional_data'];
    if (is_string($rawAdd) && str_contains($rawAdd, '.')) {
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
    } elseif (is_array($rawAdd) && !empty($rawAdd['order_ref'])) {
        $customOrder = trim((string)$rawAdd['order_ref']);
    }
}

$status      = strtoupper(trim((string)($data['status'] ?? ($data['details']['order_status'] ?? ''))));
$bankRef     = trim((string)($data['bank_reference'] ?? ($data['details']['bank_ref'] ?? '')));
$payMethod   = trim((string)($data['payment_method'] ?? ($data['details']['payment_mode'] ?? 'upi')));

$pdo = getDbConnection();

// Find order by payment_request_id or order_reference
$order = null;
if (!empty($customOrder)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE payment_request_id = ? OR order_reference = ? LIMIT 1");
    $stmt->execute([$customOrder, $customOrder]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

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

if (!$order) {
    // Acknowledge receipt even if not mapped to prevent gateway retry storms
    echo json_encode(['status' => 'ignored', 'message' => 'Order not found in system']);
    exit;
}

$orderId = (int)$order['id'];
$orderRef = $order['order_reference'];
$txnId = !empty($bankRef) ? $bankRef : ($collectId ?: 'TXN-' . strtoupper(bin2hex(random_bytes(6))));
$cleanMode = !empty($payMethod) && stripos($payMethod, 'edviron') === false ? 'Online / QR / UPI / Card (' . ucfirst($payMethod) . ')' : 'Online / QR / UPI / Card';

if (in_array($status, ['SUCCESS', 'PAID', 'COMPLETED'])) {
    if (empty($orderRef)) {
        $orderRef = generateOfficialOrderReference((int)$order['school_id']);
    }

    $pdo->beginTransaction();
    try {
        $upStmt = $pdo->prepare("UPDATE orders SET order_reference = ?, payment_status = 'Paid', order_status = 'Confirmed', gateway_transaction_id = ?, payment_method = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$orderRef, $txnId, $cleanMode, $orderId]);

        $upPay = $pdo->prepare("UPDATE payments SET status = 'Completed', transaction_ref = ?, payment_method = ?, gateway_response = ?, created_at = NOW() WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $upPay->execute([
            $txnId,
            $cleanMode,
            $rawBody,
            $orderId
        ]);

        $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, 'Confirmed', ?, 'Webhook', NOW())");
        $histStmt->execute([
            $orderId,
            $order['order_status'],
            "Payment verified via Online Gateway Webhook. Status: {$status}. Transaction ID: {$txnId}. Official Order ID: {$orderRef}"
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Webhook DB error: " . $e->getMessage());
    }
} elseif (in_array($status, ['FAILED', 'FAILURE', 'USER_DROPPED'])) {
    // Keep order_reference = NULL, automatically capture and store transaction ID
    $pdo->beginTransaction();
    try {
        $upStmt = $pdo->prepare("UPDATE orders SET payment_status = 'Failed', gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?");
        $upStmt->execute([$txnId, $orderId]);

        $upPay = $pdo->prepare("UPDATE payments SET status = 'Failed', transaction_ref = ?, gateway_response = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $upPay->execute([$txnId, $rawBody, $orderId]);

        $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, 'Pending', ?, 'Webhook', NOW())");
        $histStmt->execute([
            $orderId,
            $order['order_status'],
            "Payment attempt {$status} on Online Payment Gateway (Transaction ID: {$txnId})."
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

echo json_encode([
    'status' => 'success',
    'order_reference' => $orderRef,
    'payment_status' => $status
]);
