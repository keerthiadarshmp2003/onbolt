<?php
/**
 * OnBolt Digital Experience Platform - Order Placement API
 * Records verified school uniform requisitions from WhatsApp / Portal Checkout
 */

declare(strict_types=1);

ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/edviron.php';

try {
    $pdo = getDbConnection();

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (empty($data) || !is_array($data)) {
        $data = $_POST;
    }

    $schoolId    = (int)($data['school_id'] ?? ($_SESSION['parent_school_id'] ?? 1));
    $parentId    = !empty($_SESSION['parent_id']) ? (int)$_SESSION['parent_id'] : null;

    // Determine school code for (schoolcode)number Order ID
    $schoolCode = 'TIS';
    if (!empty($schoolId)) {
        $scStmt = $pdo->prepare("SELECT s.code, p.code as parent_code FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.id = ?");
        $scStmt->execute([$schoolId]);
        $scRow = $scStmt->fetch();
        if ($scRow) {
            $rawC = !empty($scRow['code']) ? $scRow['code'] : (!empty($scRow['parent_code']) ? $scRow['parent_code'] : 'TIS');
            $cleanC = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawC));
            if (!empty($cleanC)) $schoolCode = $cleanC;
        }
    }

    $rawRef = trim($data['order_reference'] ?? '');
    if (empty($rawRef)) {
        $orderRef = $schoolCode . rand(100000, 999999);
    } else {
        // Enforce strictly (schoolcode)number format (no spaces, no special characters, no hyphens)
        $cleanRef = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRef));
        if (strpos($cleanRef, 'OB') === 0) {
            $cleanRef = substr($cleanRef, 2);
        }
        if (ctype_digit($cleanRef)) {
            $orderRef = $schoolCode . $cleanRef;
        } else {
            $orderRef = $cleanRef;
        }
    }
    $parentName  = trim($data['parent_name'] ?? '');
    $studentName = trim($data['student_name'] ?? '');
    $gender      = trim($data['gender'] ?? 'Boy');
    $gradeClass  = trim($data['grade_class'] ?? '');
    $mobile      = trim($data['mobile'] ?? '');
    $altMobile   = trim($data['alt_mobile'] ?? '');
    $email       = trim($data['email'] ?? '');
    $address     = trim($data['delivery_address'] ?? '');
    $pincode     = trim($data['pincode'] ?? '');
    $stateDist   = trim($data['state_district'] ?? 'Kerala');
    $items       = $data['items'] ?? [];

    if (empty($studentName) || empty($mobile) || empty($items)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Student name, mobile number, and items are mandatory.']);
        exit;
    }

    $totalItems = 0;
    $subtotal = 0.0;
    foreach ($items as $it) {
        $qty = max(1, (int)($it['quantity'] ?? 1));
        $price = (float)($it['price'] ?? 0);
        $totalItems += $qty;
        $subtotal += ($price * $qty);
    }
    $totalAmount = $subtotal;
    $paymentStatus = 'Pending';
    $rawPayMethod = trim((string)($data['payment_method'] ?? 'UPI'));
    $paymentMethod = formatDisplayPaymentMethod($rawPayMethod);
    $transactionRef = 'PENDING-' . strtoupper(bin2hex(random_bytes(4)));

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $orderStatus = 'Pending';

    // 1. Insert order with NULL order_reference - Order ID is ONLY generated upon confirmed SUCCESS!
    $stmt = $pdo->prepare("INSERT INTO orders (
        order_reference, payment_request_id, gateway_transaction_id, school_id, parent_id, parent_name, student_name, gender, grade_class,
        mobile, alt_mobile, email, delivery_address, pincode, state, total_items,
        subtotal, shipping_fee, tax_amount, total_amount, payment_status, payment_method,
        order_status, admin_notes, created_at
    ) VALUES (NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, ?, ?, ?, ?, ?, NOW())");

    $adminNotes = "State/District: {$stateDist}. Initiated via online uniform ordering portal. Transaction Mode: {$paymentMethod}.";

    $stmt->execute([
        $schoolId,
        $parentId,
        $parentName,
        $studentName,
        $gender,
        $gradeClass,
        $mobile,
        $altMobile,
        $email,
        $address,
        $pincode,
        $stateDist,
        $totalItems,
        $subtotal,
        $totalAmount,
        $paymentStatus,
        $paymentMethod,
        $orderStatus,
        $adminNotes
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // 2. Generate unique sequential payment request ID (e.g. REQ-000045)
    $paymentRequestId = 'REQ-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE orders SET payment_request_id = ? WHERE id = ?")->execute([$paymentRequestId, $orderId]);

    $itemStmt = $pdo->prepare("INSERT INTO order_items (
        order_id, product_id, product_name, size, color, quantity, unit_price, total_price
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($items as $it) {
        $qty = max(1, (int)($it['quantity'] ?? 1));
        $price = (float)($it['price'] ?? 0);
        $itemTotal = $price * $qty;
        $prodId = (int)($it['product_id'] ?? 1);
        $prodName = trim((string)($it['name'] ?? 'Uniform Item'));
        $size = trim((string)($it['size'] ?? 'Standard'));
        $color = !empty($it['color']) ? trim((string)$it['color']) : null;

        $itemStmt->execute([
            $orderId,
            $prodId,
            $prodName,
            $size,
            $color,
            $qty,
            $price,
            $itemTotal
        ]);
    }

    // Build explicit callback URL with unique sequential request ID and school ID
    $callbackUrl = BASE_URL . '/schools/payment_callback.php?request_id=' . urlencode($paymentRequestId) . '&school_id=' . urlencode((string)$schoolId);

    // Call Edviron Payment Gateway API with sequential Request ID (Order ID generated ONLY after Success)
    $collectRes = edviron_create_collect_request([
        'amount'           => $totalAmount,
        'order_reference'  => $paymentRequestId,
        'order_id'         => $paymentRequestId,
        'custom_order_id'  => $paymentRequestId,
        'callback_url'     => $callbackUrl,
        'student_id'       => '-',
        'student_name'     => $studentName,
        'student_email'    => !empty($email) ? $email : 'parent@onbolt.in',
        'student_phone_no' => !empty($mobile) ? $mobile : '9876543210',
        'additional_data'  => [
            'grade_class'  => $gradeClass,
            'order_ref'    => '-',
            'parent_name'  => $parentName,
            'school_id'    => $schoolCode
        ]
    ]);

    $gatewayResponse = '';
    $redirectUrl = '';
    $collectRequestId = '';

    if ($collectRes['success'] && !empty($collectRes['collect_request_url'])) {
        $collectRequestId = $collectRes['collect_request_id'] ?? '';
        $transactionRef = !empty($collectRequestId) ? $collectRequestId : ('TXN-' . strtoupper(bin2hex(random_bytes(6))));
        $redirectUrl = $collectRes['collect_request_url'];
        $gatewayResponse = json_encode($collectRes['raw'] ?? ['status' => 'collect_request_created']);
        // Automatically capture and store gateway transaction ID on orders
        $pdo->prepare("UPDATE orders SET payment_request_id = ?, gateway_transaction_id = ? WHERE id = ?")->execute([$paymentRequestId, $transactionRef, $orderId]);
    } else {
        // Rollback and return exact gateway error without brand mentions
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error'   => $collectRes['error'] ?? 'Unable to initiate online payment session. Please check merchant gateway configuration.'
        ]);
        exit;
    }

    // Insert Payment Record
    $payStmt = $pdo->prepare("INSERT INTO payments (
        order_id, transaction_ref, payment_method, amount, currency, status, gateway_response, created_at
    ) VALUES (?, ?, ?, ?, 'INR', 'Pending', ?, NOW())");
    $payStmt->execute([
        $orderId,
        $transactionRef,
        $paymentMethod,
        $totalAmount,
        $gatewayResponse
    ]);

    // Status history
    $histStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, NULL, 'Pending', ?, 'System', NOW())");
    $histStmt->execute([
        $orderId,
        "Order initiated. Gateway Transaction ID: {$transactionRef}. Transaction Mode: {$paymentMethod}. Awaiting parent payment completion."
    ]);

    // Admin notification
    $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type, link, created_at) VALUES (?, ?, 'order', ?, NOW())");
    $notifStmt->execute([
        "New Payment Request: {$paymentRequestId}",
        "Order requisition for {$studentName} ({$gradeClass}) initiated. Payment Pending (Rs. {$totalAmount}) via {$paymentMethod}",
        "pending_transactions.php?search=" . urlencode($mobile)
    ]);

    $pdo->commit();

    // Persist request details in session as a fallback for return redirect
    $_SESSION['last_payment_request_id'] = $paymentRequestId;
    $_SESSION['last_order_id'] = $orderId;
    unset($_SESSION['last_order_reference']);

    ob_clean();
    echo json_encode([
        'success'                => true,
        'payment_request_id'     => $paymentRequestId,
        'gateway_transaction_id' => $transactionRef,
        'transaction_id'         => $transactionRef,
        'order_reference'        => null,
        'order_id'               => $orderId,
        'collect_request_id'     => $collectRequestId,
        'redirect_url'           => $redirectUrl,
        'payment_status'         => 'Pending',
        'payment_method'         => $paymentMethod,
        'total_items'            => $totalItems,
        'total_amount'           => $totalAmount
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to log order: ' . $e->getMessage()
    ]);
}
