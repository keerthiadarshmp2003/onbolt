<?php
/**
 * OnBolt Digital Experience Platform - Parent Purchasing Data & Messaging
 * URL: /admin/orders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (!adminCan('orders_manage')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access Parent Purchases & Orders.');
}
$adminName = sanitize($admin['full_name']);
$adminRole = sanitize($admin['role_name']);
$pdo = getDbConnection();

$isFullAdmin = (($admin['role_slug'] ?? '') === 'superadmin' || ($admin['role_slug'] ?? '') === 'admin' || ($admin['role_id'] ?? 0) == 1 || ($admin['role_id'] ?? 0) == 4);
if ($isFullAdmin) {
    $allowedStatuses = ['Confirmed', 'In progress', 'Packed', 'Dispatched', 'Dispatched by hand', 'Cancelled', 'Refunded'];
} else {
    $allowedStatuses = ['Confirmed', 'In progress', 'Packed', 'Dispatched', 'Cancelled'];
}
$statuses = $allowedStatuses;

// Handle AJAX Order Details Request for View Modal (Popup displays only details)
if (isset($_GET['ajax_order_details'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $oid = (int)$_GET['ajax_order_details'];
    $stmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.code as school_code, s.city as school_city,
                                  COALESCE(o.gateway_transaction_id, p.transaction_ref) as effective_txn_id 
                           FROM orders o 
                           LEFT JOIN schools s ON o.school_id = s.id 
                           LEFT JOIN payments p ON p.order_id = o.id
                           WHERE o.id = ?
                           ORDER BY p.id DESC LIMIT 1");
    $stmt->execute([$oid]);
    $ord = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ord) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    $iStmt = $pdo->prepare("SELECT oi.*, p.featured_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $iStmt->execute([$oid]);
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $ord,
        'items' => $items,
        'total_formatted' => formatCurrency((float)$ord['total_amount']),
        'date_formatted' => date('d M Y, h:i A', strtotime($ord['created_at']))
    ]);
    exit;
}

// Check for AJAX requests first before loading header.php
$isAjax = (!empty($_POST['is_ajax'])) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Handle Quick Single Order Status Update (Row-by-Row)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['order_status'] ?? '');

    if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        try {
            $prevStmt = $pdo->prepare("SELECT order_reference, order_status FROM orders WHERE id = ?");
            $prevStmt->execute([$orderId]);
            $ordRow = $prevStmt->fetch();

            if ($ordRow) {
                $prevStatus = $ordRow['order_status'];
                $uStmt = $pdo->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
                $uStmt->execute([$newStatus, $orderId]);

                $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $hStmt->execute([$orderId, $prevStatus, $newStatus, "Status updated from {$prevStatus} to {$newStatus}", $adminName]);

                logActivity('update_order_status', 'orders', (int)$admin['id'], 'admin', "Order {$ordRow['order_reference']} status changed to {$newStatus}");

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'order_id' => $orderId,
                        'order_reference' => $ordRow['order_reference'],
                        'new_status' => $newStatus,
                        'message' => "Order {$ordRow['order_reference']} status updated to {$newStatus}!"
                    ]);
                    exit;
                }
                $msg = "Order {$ordRow['order_reference']} status updated to '{$newStatus}' successfully!";
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    exit;
                }
                $err = 'Order not found.';
            }
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $err = 'Failed to update order status: ' . $e->getMessage();
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
            exit;
        }
        $err = 'Invalid order ID or status.';
    }
}

// Handle Bulk Order Status Update (All at a time)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update_status') {
    $rawOrderIds = $_POST['order_ids'] ?? [];
    if (is_string($rawOrderIds)) {
        $rawOrderIds = explode(',', $rawOrderIds);
    }
    $orderIds = array_filter(array_map('intval', (array)$rawOrderIds));
    $newStatus = trim($_POST['order_status'] ?? '');

    if (!empty($orderIds) && in_array($newStatus, $allowedStatuses, true)) {
        try {
            $inClause = implode(',', $orderIds);
            $currOrders = $pdo->query("SELECT id, order_reference, order_status FROM orders WHERE id IN ({$inClause})")->fetchAll();

            $uStmt = $pdo->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
            $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

            $updatedCount = 0;
            foreach ($currOrders as $cord) {
                $uStmt->execute([$newStatus, $cord['id']]);
                $hStmt->execute([$cord['id'], $cord['order_status'], $newStatus, "Updated via Bulk Status Update tool", $adminName]);
                $updatedCount++;
            }

            logActivity('bulk_update_status', 'orders', (int)$admin['id'], 'admin', "Bulk updated {$updatedCount} orders to {$newStatus}");

            $successMsg = "Successfully updated {$updatedCount} orders to '{$newStatus}'!";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'updated_count' => $updatedCount,
                    'order_ids' => $orderIds,
                    'new_status' => $newStatus,
                    'message' => $successMsg
                ]);
                exit;
            }
            $msg = $successMsg;
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            $err = 'Bulk status update failed: ' . $e->getMessage();
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please select at least one order and a valid status.']);
            exit;
        }
        $err = 'Please select at least one order and a valid status.';
    }
}

$msg = $msg ?? '';
$err = $err ?? '';

// Handle Status Update from Order View Form
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = (int)$_POST['order_id'];
    $newStatus = trim($_POST['order_status']);
    $notes = trim($_POST['admin_notes'] ?? '');

    try {
        $prevStmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
        $prevStmt->execute([$orderId]);
        $prevStatus = $prevStmt->fetchColumn() ?: 'Previous';

        $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$newStatus, $notes, $orderId]);

        // Record in status history
        $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $hStmt->execute([$orderId, $prevStatus, $newStatus, !empty($notes) ? "Status set to {$newStatus}. Notes: {$notes}" : "Status set to {$newStatus}.", $adminName]);

        $msg = "Order status updated to '{$newStatus}' successfully!";
        logActivity('update_order_status', 'orders', (int)$admin['id'], 'admin', "Order #{$orderId} changed to {$newStatus}");
    } catch (Exception $e) {
        $err = 'Failed to update order status: ' . $e->getMessage();
    }
}

// Handle Order Confirmation and Notification
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_order') {
    $orderId = (int)$_POST['order_id'];
    $customMessage = trim($_POST['custom_message'] ?? '');

    try {
        $oStmt = $pdo->prepare("SELECT o.*, s.name as school_name FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE o.id = ?");
        $oStmt->execute([$orderId]);
        $ord = $oStmt->fetch();

        if ($ord) {
            $uStmt = $pdo->prepare("UPDATE orders SET order_status = 'Confirmed', updated_at = NOW() WHERE id = ?");
            $uStmt->execute([$orderId]);

            // Record status history
            $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, 'Confirmed', 'Order confirmed by Administrator', ?, NOW())");
            $hStmt->execute([$orderId, $ord['order_status'], $adminName]);

            // Format confirmation message
            $msgText = $customMessage;
            if (empty($msgText)) {
                $msgText = "Dear {$ord['parent_name']}, your uniform order {$ord['order_reference']} for {$ord['student_name']} ({$ord['grade_class']}) at {$ord['school_name']} has been CONFIRMED by OnBolt. Total Paid: " . formatCurrency((float)$ord['total_amount']) . ". We will notify you once uniforms are ready for dispatch.";
            }

            $mStmt = $pdo->prepare("INSERT INTO order_messages (order_id, parent_mobile, parent_name, message_text, channel, sent_by, created_at) VALUES (?, ?, ?, ?, 'WhatsApp / Notification', ?, NOW())");
            $mStmt->execute([$orderId, $ord['mobile'], $ord['parent_name'], $msgText, $adminName]);

            $msg = "Order {$ord['order_reference']} has been CONFIRMED and confirmation message logged successfully!";
            logActivity('confirm_order', 'orders', (int)$admin['id'], 'admin', "Order #{$orderId} confirmed and parent notified.");
        }
    } catch (Exception $e) {
        $err = 'Failed to confirm order: ' . $e->getMessage();
    }
}

// Handle Order Cancellation (Requirement 2)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderId = (int)$_POST['order_id'];
    $reason = trim($_POST['cancel_reason'] ?? '');
    if (empty($reason)) {
        $reason = 'Cancelled by Administrator';
    }

    try {
        $prevStmt = $pdo->prepare("SELECT order_reference, order_status, parent_name, mobile FROM orders WHERE id = ?");
        $prevStmt->execute([$orderId]);
        $ord = $prevStmt->fetch();

        if ($ord) {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = 'Cancelled', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Cancelled: ', ?, ']'), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $orderId]);

            $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, 'Cancelled', ?, ?, NOW())");
            $hStmt->execute([$orderId, $ord['order_status'], "Order Cancelled. Reason: {$reason}", $adminName]);

            logActivity('cancel_order', 'orders', (int)$admin['id'], 'admin', "Order {$ord['order_reference']} cancelled. Reason: {$reason}");
            $msg = "Order {$ord['order_reference']} has been CANCELLED successfully.";
        }
    } catch (Exception $e) {
        $err = 'Failed to cancel order: ' . $e->getMessage();
    }
}

// Handle Order Refund (Requirement 2)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refund_order') {
    $orderId = (int)$_POST['order_id'];
    $refundAmt = (float)($_POST['refund_amount'] ?? 0);
    $refundNotes = trim($_POST['refund_notes'] ?? '');
    if (empty($refundNotes)) {
        $refundNotes = 'Refund processed by Administrator';
    }

    try {
        $prevStmt = $pdo->prepare("SELECT order_reference, order_status, total_amount FROM orders WHERE id = ?");
        $prevStmt->execute([$orderId]);
        $ord = $prevStmt->fetch();

        if ($ord) {
            if ($refundAmt <= 0) {
                $refundAmt = (float)$ord['total_amount'];
            }
            $stmt = $pdo->prepare("UPDATE orders SET order_status = 'Refunded', payment_status = 'Refunded', refund_amount = ?, refund_status = 'Approved', refund_notes = ?, refunded_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$refundAmt, $refundNotes, $orderId]);

            $hStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, comment, changed_by, created_at) VALUES (?, ?, 'Refunded', ?, ?, NOW())");
            $hStmt->execute([$orderId, $ord['order_status'], "Order Refunded (₹{$refundAmt}). Notes: {$refundNotes}", $adminName]);

            logActivity('refund_order', 'orders', (int)$admin['id'], 'admin', "Order {$ord['order_reference']} refunded ₹{$refundAmt}. Notes: {$refundNotes}");
            $msg = "Order {$ord['order_reference']} has been marked as REFUNDED (Amount: ₹" . number_format($refundAmt, 2) . ") successfully.";
        }
    } catch (Exception $e) {
        $err = 'Failed to process refund: ' . $e->getMessage();
    }
}

// Handle Log Sent Message
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_message') {
    $orderId = (int)$_POST['order_id'];
    $parentName = trim($_POST['parent_name'] ?? '');
    $parentMobile = trim($_POST['parent_mobile'] ?? '');
    $messageText = trim($_POST['message_text'] ?? '');
    $channel = trim($_POST['channel'] ?? 'WhatsApp');

    if (!empty($messageText) && !empty($parentMobile)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, parent_mobile, parent_name, message_text, channel, sent_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$orderId, $parentMobile, $parentName, $messageText, $channel, $adminName]);
            $msg = "Message logged and recorded in customer history successfully!";
        } catch (Exception $e) {
            $err = 'Failed to record message: ' . $e->getMessage();
        }
    } else {
        $err = 'Message text and mobile number cannot be empty.';
    }
}

// Handle Order Deletion (with confirmation popup)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
        try {
            $prevStmt = $pdo->prepare("SELECT order_reference FROM orders WHERE id = ?");
            $prevStmt->execute([$orderId]);
            $ordRef = $prevStmt->fetchColumn();

            if ($ordRef) {
                // Delete linked records safely
                $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM order_status_history WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM order_messages WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

                logActivity('delete_order', 'orders', (int)$admin['id'], 'admin', "Permanently deleted order {$ordRef} (ID: {$orderId})");
                $msg = "Order {$ordRef} has been permanently deleted.";
            }
        } catch (Exception $e) {
            $err = 'Failed to delete order: ' . $e->getMessage();
        }
    }
}

// Inspect single order
$viewId = (int)($_GET['view'] ?? 0);
$selectedOrder = null;
$selectedItems = [];
$orderMessages = [];

if ($viewId > 0) {
    $vStmt = $pdo->prepare("SELECT o.*, s.name as school_name, s.slug as school_slug, s.logo as school_logo FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE o.id = ?");
    $vStmt->execute([$viewId]);
    $selectedOrder = $vStmt->fetch();

    if ($selectedOrder) {
        $iStmt = $pdo->prepare("SELECT oi.*, p.featured_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $iStmt->execute([$viewId]);
        $selectedItems = $iStmt->fetchAll();

        // Fetch communication history
        $mStmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY id DESC");
        $mStmt->execute([$viewId]);
        $orderMessages = $mStmt->fetchAll();

        // Fetch payment details
        $pStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$viewId]);
        $selectedPayment = $pStmt->fetch();
    }
}

// Filter and Search parameters
$statusFilter = trim($_GET['status'] ?? '');
$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "SELECT o.*, s.name as school_name 
        FROM orders o 
        LEFT JOIN schools s ON o.school_id = s.id 
        WHERE (o.order_reference IS NOT NULL AND o.order_reference != '')";

if (!empty($statusFilter)) {
    if (in_array($statusFilter, ['Cancelled', 'Cancel'])) {
        $sql .= " AND o.order_status IN ('Cancelled', 'Cancel')";
    } elseif (in_array($statusFilter, ['Refunded', 'Refund'])) {
        $sql .= " AND (o.order_status IN ('Refunded', 'Refund') OR o.refund_status = 'Approved')";
    } elseif (in_array($statusFilter, ['In progress', 'Production'])) {
        $sql .= " AND o.order_status IN ('In progress', 'Production')";
    } else {
        $sql .= " AND o.order_status = " . $pdo->quote($statusFilter);
    }
}
if ($subSchoolFilter > 0) {
    $sql .= " AND o.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $sql .= " AND (o.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}
if (!empty($search)) {
    $sql .= " AND (o.order_reference LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.gateway_transaction_id LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.payment_request_id LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.student_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.parent_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.mobile LIKE " . $pdo->quote("%{$search}%") . ")";
}
$sql .= " ORDER BY o.id DESC";
$ordersList = $pdo->query($sql)->fetchAll();

// Dynamic KPI Metrics (computed based on current sorting/filtering for confirmed orders)
$scopeCond = " AND (o.order_reference IS NOT NULL AND o.order_reference != '')";
if ($subSchoolFilter > 0) {
    $scopeCond .= " AND o.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $scopeCond .= " AND (o.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}
if (!empty($search)) {
    $scopeCond .= " AND (o.order_reference LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.gateway_transaction_id LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.payment_request_id LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.student_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.parent_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR o.mobile LIKE " . $pdo->quote("%{$search}%") . ")";
}

// 1. Total Purchasing Value & 2. Total Parent Purchases
if (!empty($statusFilter)) {
    if (in_array($statusFilter, ['Cancelled', 'Cancel', 'Refunded', 'Refund'])) {
        $totalRevenue = 0.0;
    } else {
        $statusMatch = in_array($statusFilter, ['In progress', 'Production'])
            ? " AND o.order_status IN ('In progress', 'Production')"
            : " AND o.order_status = " . $pdo->quote($statusFilter);
        $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE 1=1 {$statusMatch} {$scopeCond}")->fetchColumn();
    }
    
    $statusMatchAll = "";
    if (in_array($statusFilter, ['Cancelled', 'Cancel'])) {
        $statusMatchAll = " AND o.order_status IN ('Cancelled', 'Cancel')";
    } elseif (in_array($statusFilter, ['Refunded', 'Refund'])) {
        $statusMatchAll = " AND (o.order_status IN ('Refunded', 'Refund') OR o.refund_status = 'Approved')";
    } elseif (in_array($statusFilter, ['In progress', 'Production'])) {
        $statusMatchAll = " AND o.order_status IN ('In progress', 'Production')";
    } else {
        $statusMatchAll = " AND o.order_status = " . $pdo->quote($statusFilter);
    }
    $totalPurchases = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE 1=1 {$statusMatchAll} {$scopeCond}")->fetchColumn();
} else {
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE o.order_status NOT IN ('Cancelled', 'Cancel', 'Refunded', 'Refund') {$scopeCond}")->fetchColumn();
    $totalPurchases = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE 1=1 {$scopeCond}")->fetchColumn();
}

// 3. Confirmed Orders (Awaiting production)
$confirmedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE o.order_status = 'Confirmed' {$scopeCond}")->fetchColumn();

// 4. Cancelled Orders (Replaced In Progress & Added Cancel)
$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE o.order_status IN ('Cancelled', 'Cancel') {$scopeCond}")->fetchColumn();

// 5. Refunded Orders (Added Refund)
$refundedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_status IN ('Refunded', 'Refund') AND o.order_status NOT IN ('Cancelled', 'Cancel')) {$scopeCond}")->fetchColumn();

// 6. Dispatched Orders (Courier / In-Hand Complete)
$dispatchedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_status IN ('Dispatched', 'Dispatched by hand', 'Delivered', 'Dispatched by Hand / Delivered') OR o.order_status LIKE '%Dispatch%' OR o.order_status LIKE '%Deliver%') {$scopeCond}")->fetchColumn();

// Main schools list (only main schools: standalone single schools and multi-school groups)
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC")->fetchAll();

// Under schools list (only sub-schools / campuses with their parent reference)
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

// Check if selected main school has sub-schools
$selectedMainHasSubs = false;
if ($schoolFilter > 0) {
    foreach ($mainSchools as $ms) {
        if ((int)$ms['id'] === $schoolFilter && !empty($ms['has_sub_schools'])) {
            $selectedMainHasSubs = true;
            break;
        }
    }
}
$statuses = $allowedStatuses;

// ==================== EXPORT REQUISITIONS REPORT (EXCEL & PDF) ====================
// Resolve clean human-readable labels for report headers
$activeStatusLabel = !empty($statusFilter) ? $statusFilter : 'All Statuses';
$activeMainSchoolLabel = '';
$activeSubSchoolLabel = '';

if ($schoolFilter > 0) {
    foreach ($mainSchools as $ms) {
        if ((int)$ms['id'] === $schoolFilter) {
            $activeMainSchoolLabel = $ms['name'];
            break;
        }
    }
}

if ($subSchoolFilter > 0) {
    foreach ($underSchools as $us) {
        if ((int)$us['id'] === $subSchoolFilter) {
            $activeSubSchoolLabel = $us['name'];
            if (empty($activeMainSchoolLabel) && !empty($us['parent_name'])) {
                $activeMainSchoolLabel = $us['parent_name'];
            }
            break;
        }
    }
}

// Full School display label
if (!empty($activeSubSchoolLabel)) {
    $activeSchoolDisplay = (!empty($activeMainSchoolLabel) ? $activeMainSchoolLabel . ' - ' : '') . $activeSubSchoolLabel;
} elseif (!empty($activeMainSchoolLabel)) {
    $activeSchoolDisplay = $activeMainSchoolLabel;
} else {
    $activeSchoolDisplay = 'All Partner Schools';
}

$activeSearchLabel = !empty($search) ? $search : '';

$totalOrdersCount = count($ordersList);
$totalItemsCount = 0;
$totalAmountSum = 0.0;
foreach ($ordersList as $o) {
    $totalItemsCount += (int)$o['total_items'];
    $totalAmountSum += (float)$o['total_amount'];
}

if (isset($_GET['export'])) {
    $exportType = strtolower(trim($_GET['export']));

    if ($exportType === 'excel' || $exportType === 'csv') {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $safeSchoolFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $activeSchoolDisplay);
        $filename = "OnBolt_Orders_Report_{$safeSchoolFilename}_" . date('Y-m-d_His') . ".csv";
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Microsoft Excel renders Unicode properly
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['ONBOLT COMPLETE BRANDING SOLUTIONS - Parent Purchasing Requisitions Report']);
        fputcsv($out, [
            'School Scope: ' . $activeSchoolDisplay,
            'Status Filter: ' . $activeStatusLabel,
            (!empty($activeSearchLabel) ? 'Search: ' . $activeSearchLabel : 'All Records'),
            'Generated: ' . date('d M Y, h:i A')
        ]);
        fputcsv($out, []); // Blank separator row

        // Column Headers
        fputcsv($out, [
            'Sl No',
            'Order Reference',
            'School Name',
            'Student Name',
            'Grade / Class',
            'Parent Name',
            'Parent Contact',
            'Total Items',
            'Payment Status',
            'Total Value (INR)',
            'Order Status',
            'Order Date'
        ]);

        $idx = 1;
        foreach ($ordersList as $o) {
            fputcsv($out, [
                $idx++,
                $o['order_reference'],
                $o['school_name'] ?: 'School',
                $o['student_name'],
                $o['grade_class'],
                $o['parent_name'],
                $o['mobile'],
                $o['total_items'] . ' pcs',
                $o['payment_status'],
                number_format((float)$o['total_amount'], 2, '.', ''),
                $o['order_status'],
                date('d M Y, h:i A', strtotime($o['created_at']))
            ]);
        }

        fputcsv($out, []); // Blank row before summary
        fputcsv($out, [
            '',
            '',
            '',
            '',
            '',
            '',
            'GRAND TOTAL:',
            $totalItemsCount . ' pcs',
            '',
            number_format($totalAmountSum, 2, '.', ''),
            '',
            ''
        ]);

        fclose($out);
        exit;
    }

    if ($exportType === 'pdf_print' || $exportType === 'pdf') {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>OnBolt Orders Report - <?= date('d M Y') ?></title>
            <style>
                @page { size: A4 landscape; margin: 12mm 10mm; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #0F172A; margin: 0; padding: 20px; font-size: 11px; }
                .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #FF6702; padding-bottom: 12px; margin-bottom: 12px; }
                .brand-title { font-size: 20px; font-weight: 800; color: #FF6702; letter-spacing: -0.5px; }
                .brand-subtitle { font-size: 11px; color: #64748B; margin-top: 3px; }
                .filters-summary { background: #FFF7ED; border: 1px solid #FFEDD5; border-radius: 6px; padding: 8px 12px; margin-bottom: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 10.5px; }
                .filter-tag { display: inline-flex; gap: 4px; }
                .filter-tag strong { color: #9A3412; }
                table.orders-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
                table.orders-table th { background: #FF6702; color: #ffffff; text-align: left; padding: 6px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; border: 1px solid #EA580C; }
                table.orders-table td { padding: 6px 8px; border: 1px solid #E2E8F0; font-size: 10px; vertical-align: middle; }
                table.orders-table tr:nth-child(even) td { background-color: #F8FAFC; }
                .center { text-align: center; }
                .right { text-align: right; }
                .total-row td { background: #FFF7ED !important; font-weight: bold; font-size: 11px; border-top: 2px solid #FF6702; }
                .no-print { display: flex; gap: 10px; margin-bottom: 15px; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()" style="background:#FF6702; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer;">🖨️ Print / Save as PDF</button>
                <button onclick="window.close()" style="background:#64748B; color:#fff; border:none; padding:8px 14px; border-radius:6px; font-weight:600; cursor:pointer;">Close Window</button>
            </div>
            
            <div class="report-header">
                <div>
                    <div class="brand-title">ONBOLT COMPLETE BRANDING SOLUTIONS</div>
                    <div class="brand-subtitle">Parent Uniform Purchasing Requisitions Report</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px; color:#64748B;">Generated: <?= date('d M Y, h:i A') ?></div>
                    <div style="font-weight:700; color:#0F172A; font-size:11px;">Total Records: <?= $totalOrdersCount ?></div>
                </div>
            </div>

            <div class="filters-summary">
                <div class="filter-tag"><strong>School:</strong> <?= htmlspecialchars($activeSchoolDisplay) ?></div>
                <?php if (!empty($activeSubSchoolLabel)): ?>
                    <div class="filter-tag"><strong>Sub School:</strong> <?= htmlspecialchars($activeSubSchoolLabel) ?></div>
                <?php endif; ?>
                <div class="filter-tag"><strong>Status:</strong> <?= htmlspecialchars($activeStatusLabel) ?></div>
                <?php if (!empty($activeSearchLabel)): ?>
                    <div class="filter-tag"><strong>Search:</strong> <?= htmlspecialchars($activeSearchLabel) ?></div>
                <?php endif; ?>
                <div class="filter-tag"><strong>Total Orders:</strong> <?= $totalOrdersCount ?></div>
                <div class="filter-tag"><strong>Total Items:</strong> <?= $totalItemsCount ?> pcs</div>
                <div class="filter-tag"><strong>Grand Total:</strong> ₹<?= number_format($totalAmountSum, 2) ?></div>
            </div>

            <table class="orders-table">
                <thead>
                    <tr>
                        <th class="center" style="width:35px;">#</th>
                        <th>Order Ref</th>
                        <th>School</th>
                        <th>Student &amp; Grade</th>
                        <th>Parent &amp; Phone</th>
                        <th class="center">Items</th>
                        <th class="center">Payment</th>
                        <th class="right">Total Value</th>
                        <th class="center">Status</th>
                        <th class="center">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordersList)): ?>
                        <tr><td colspan="10" class="center" style="padding:20px; color:#64748B;">No purchasing records match the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($ordersList as $o): ?>
                            <tr>
                                <td class="center"><?= $idx++ ?></td>
                                <td style="font-weight:800; color:#EA580C;"><?= htmlspecialchars($o['order_reference']) ?></td>
                                <td><?= htmlspecialchars($o['school_name'] ?: 'School') ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($o['student_name']) ?></strong><br>
                                    <span style="color:#64748B; font-size:9.5px;"><?= htmlspecialchars($o['grade_class']) ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($o['parent_name']) ?><br>
                                    <span style="color:#64748B; font-size:9.5px;"><?= htmlspecialchars($o['mobile']) ?></span>
                                </td>
                                <td class="center"><?= (int)$o['total_items'] ?> pcs</td>
                                <td class="center">
                                    <span style="<?= $o['payment_status'] === 'Paid' ? 'color:#15803D; font-weight:700;' : 'color:#B45309;' ?>">
                                        <?= htmlspecialchars($o['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="right" style="font-weight:700;">₹<?= number_format((float)$o['total_amount'], 2) ?></td>
                                <td class="center" style="font-weight:700;"><?= htmlspecialchars($o['order_status']) ?></td>
                                <td class="center"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="5" class="right" style="font-weight:800;">GRAND TOTAL:</td>
                            <td class="center" style="font-weight:800;"><?= $totalItemsCount ?> pcs</td>
                            <td></td>
                            <td class="right" style="font-weight:800; color:#EA580C;">₹<?= number_format($totalAmountSum, 2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <script>
                window.addEventListener('load', () => {
                    setTimeout(() => { window.print(); }, 400);
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

$adminActive = 'orders';
$adminTitle = 'Parent Purchases & Customer Messaging';
require_once __DIR__ . '/header.php';
?>

<style>
.order-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(11, 17, 30, 0.75);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.order-modal-backdrop.active {
    display: flex;
}
.order-modal-box {
    background: #ffffff;
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 620px;
    box-shadow: var(--shadow-xl);
    border: 1.5px solid var(--brand-border);
    overflow: hidden;
    animation: modalPop 0.25s ease-out;
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.template-pill {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: var(--brand-surface-alt);
    border: 1px solid var(--brand-border);
    border-radius: var(--radius-pill);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--brand-dark);
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 0.4rem;
    margin-bottom: 0.4rem;
}
.template-pill:hover {
    background: var(--brand-primary);
    color: #ffffff;
    border-color: var(--brand-primary);
}

/* Optimized Table Box & Layout for Orders Page - Scrolls Inside Frame with Sticky Header */
.orders-table-wrapper {
    background: #ffffff;
    border-radius: var(--radius-xl);
    border: 1px solid var(--brand-border);
    box-shadow: var(--shadow-sm);
    overflow-x: auto;
    overflow-y: auto;
    max-height: 520px;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}
.orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    table-layout: auto;
    margin: 0;
}
.orders-table th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--brand-surface-alt);
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    padding: 0.65rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--brand-muted);
    border-bottom: 1px solid var(--brand-border);
    vertical-align: middle;
    white-space: nowrap;
}
.orders-table td {
    padding: 0.55rem 0.45rem;
    border-bottom: 1px solid var(--brand-border);
    vertical-align: middle;
    font-size: 0.78rem;
}
.orders-table tr:last-child td {
    border-bottom: none;
}
.orders-table tr:hover td {
    background: #FFFDFB;
}

/* Symmetrical KPI Grid (Guarantees perfect alignment without lone hanging boxes) */
.orders-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.85rem;
    margin-bottom: 2rem;
    width: 100%;
    box-sizing: border-box;
}
@media (max-width: 1250px) {
    .orders-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
    }
}
@media (max-width: 680px) {
    .orders-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.stat-card-kpi {
    background: #ffffff;
    border-radius: var(--radius-lg);
    padding: 1.15rem 1.25rem;
    border: 1px solid var(--brand-border);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 128px;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card-kpi:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}
.stat-card-kpi .stat-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--brand-muted);
    line-height: 1.2;
}
.stat-card-kpi .stat-num {
    font-family: var(--font-display);
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    margin: 0.35rem 0;
}
.stat-card-kpi .stat-sub {
    font-size: 0.72rem;
    color: var(--brand-muted);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 2rem;">
        ⚠️ <?= sanitize($err) ?>
    </div>
<?php endif; ?>

<?php if ($selectedOrder): ?>
    <!-- ==================== DEDICATED SINGLE ORDER DETAILS & MANAGE OPTIONS ==================== -->
    <div style="margin-bottom:1.25rem;">
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline" style="font-size:0.85rem; font-weight:700; color:var(--brand-primary); border-color:var(--brand-border); background:#ffffff; display:inline-flex; align-items:center; gap:0.4rem; padding:0.45rem 1rem; border-radius:var(--radius-md); box-shadow:0 1px 2px rgba(0,0,0,0.05); text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to All Orders List
        </a>
    </div>

    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); padding:2rem 2.5rem; box-shadow:var(--shadow-md); margin-bottom:2.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <h2 class="heading-1" style="font-size:1.75rem; margin:0;"><?= sanitize($selectedOrder['order_reference']) ?></h2>
                    <?= getOrderStatusBadge($selectedOrder['order_status']) ?>
                </div>
                <div style="font-size:0.88rem; color:var(--brand-muted); margin-top:0.25rem;">
                    School: <strong><?= sanitize($selectedOrder['school_name'] ?: 'International School') ?></strong> &bull; Placed on: <?= date('d M Y, h:i A', strtotime($selectedOrder['created_at'])) ?>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; align-items:center;">
                <button type="button" class="btn btn-primary" onclick="openMessageModal(<?= htmlspecialchars(json_encode([
                    'order_id' => $selectedOrder['id'],
                    'ref' => $selectedOrder['order_reference'],
                    'parent_name' => $selectedOrder['parent_name'],
                    'student_name' => $selectedOrder['student_name'],
                    'mobile' => $selectedOrder['mobile'],
                    'school' => $selectedOrder['school_name'] ?: 'School',
                    'total' => formatCurrency($selectedOrder['total_amount']),
                    'status' => $selectedOrder['order_status'],
                    'address' => $selectedOrder['delivery_address']
                ])) ?>)">
                    Send Message to Parent
                </button>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 340px; gap:2rem; align-items:start;">
            <!-- Left Column: Items Table & Student Meta -->
            <div style="display:flex; flex-direction:column; gap:1.75rem;">
                <!-- Items Table -->
                <div>
                    <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.75rem; color:var(--brand-dark);">Purchased Uniform Items (<?= count($selectedItems) ?>)</h4>
                    <div style="border:1px solid var(--brand-border); border-radius:var(--radius-md); overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">
                            <thead>
                                <tr style="background:var(--brand-surface-alt); border-bottom:1px solid var(--brand-border); color:var(--brand-muted); font-size:0.78rem; text-transform:uppercase;">
                                    <th style="padding:0.75rem 1rem;">Item</th>
                                    <th style="padding:0.75rem 1rem;">Size</th>
                                    <th style="padding:0.75rem 1rem; text-align:center;">Qty</th>
                                    <th style="padding:0.75rem 1rem; text-align:right;">Price</th>
                                    <th style="padding:0.75rem 1rem; text-align:right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedItems as $item): ?>
                                    <tr style="border-bottom:1px solid var(--brand-border);">
                                        <td style="padding:0.85rem 1rem; font-weight:700; display:flex; align-items:center; gap:0.75rem;">
                                            <?php if (!empty($item['featured_image'])): ?>
                                                <img src="<?= BASE_URL ?>/<?= sanitize($item['featured_image']) ?>" alt="Uniform" style="width:36px; height:36px; object-fit:cover; border-radius:4px; border:1px solid var(--brand-border);">
                                            <?php endif; ?>
                                            <span><?= sanitize($item['product_name']) ?></span>
                                        </td>
                                        <td style="padding:0.85rem 1rem;"><span style="background:var(--brand-surface-alt); padding:0.2rem 0.5rem; border-radius:4px; font-weight:700;">Size <?= sanitize($item['size']) ?></span></td>
                                        <td style="padding:0.85rem 1rem; text-align:center; font-weight:800;"><?= $item['quantity'] ?></td>
                                        <td style="padding:0.85rem 1rem; text-align:right;"><?= formatCurrency($item['unit_price']) ?></td>
                                        <td style="padding:0.85rem 1rem; text-align:right; font-weight:800; color:var(--brand-dark);"><?= formatCurrency($item['total_price']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background:var(--brand-surface-alt); font-weight:800;">
                                    <td colspan="4" style="padding:0.85rem 1rem; text-align:right;">Grand Total Amount:</td>
                                    <td style="padding:0.85rem 1rem; text-align:right; color:var(--brand-primary); font-size:1.1rem;"><?= formatCurrency($selectedOrder['total_amount']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Student & Delivery Meta Box -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; background:var(--brand-surface-alt); padding:1.5rem; border-radius:var(--radius-lg); font-size:0.88rem; border:1px solid var(--brand-border);">
                    <div>
                        <div style="font-weight:800; color:var(--brand-dark); margin-bottom:0.4rem; text-transform:uppercase; font-size:0.75rem; letter-spacing:0.05em;">Student Details</div>
                        <div style="margin-bottom:0.25rem;"><strong>Student Name:</strong> <?= sanitize($selectedOrder['student_name']) ?></div>
                        <div style="margin-bottom:0.25rem;"><strong>Grade &amp; Section:</strong> <?= sanitize($selectedOrder['grade_class']) ?></div>
                        <div style="margin-bottom:0.25rem;"><strong>Parent / Guardian:</strong> <?= sanitize($selectedOrder['parent_name']) ?></div>
                        <div style="margin-bottom:0.25rem;"><strong>Contact Phone:</strong> <?= sanitize($selectedOrder['mobile']) ?></div>
                        <div><strong>Email:</strong> <?= sanitize($selectedOrder['email'] ?: 'Not provided') ?></div>
                    </div>
                    <div>
                        <div style="font-weight:800; color:var(--brand-dark); margin-bottom:0.4rem; text-transform:uppercase; font-size:0.75rem; letter-spacing:0.05em;">Payment &amp; Delivery</div>
                        <div style="margin-bottom:0.25rem;"><strong>Address:</strong> <?= sanitize($selectedOrder['delivery_address']) ?></div>
                        <div style="margin-bottom:0.25rem;"><strong>Pincode:</strong> <?= sanitize($selectedOrder['pincode']) ?> (<?= sanitize($selectedOrder['state']) ?>)</div>
                        <div style="margin-bottom:0.25rem;"><strong>Payment Method:</strong> <?= sanitize(formatDisplayPaymentMethod($selectedOrder['payment_method'])) ?></div>
                        <div style="margin-bottom:0.25rem;"><strong>Payment Status:</strong> <?= $selectedOrder['payment_status'] === 'Paid' ? '<span style="color:#15803d; font-weight:800; background:#dcfce7; padding:0.15rem 0.55rem; border-radius:9999px;">✓ PAID</span>' : '<span style="font-weight:700; color:var(--brand-primary);">' . sanitize($selectedOrder['payment_status']) . '</span>' ?></div>
                        <?php $selectedTxnId = !empty($selectedOrder['gateway_transaction_id']) ? $selectedOrder['gateway_transaction_id'] : (!empty($selectedPayment['transaction_ref']) ? $selectedPayment['transaction_ref'] : '—'); ?>
                        <div><strong>Gateway Transaction ID:</strong> <code style="font-weight:700; color:#0284C7; background:#EFF6FF; padding:2px 8px; border-radius:4px; font-size:0.85rem;"><?= sanitize($selectedTxnId) ?></code></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Update Purchasing Status & Message Log -->
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Status Update Box -->
                <div style="background:#ffffff; border:1px solid var(--brand-border); border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow-sm);">
                    <h4 style="font-weight:800; font-size:1.05rem; margin:0 0 1rem 0; color:var(--brand-dark);">Update Purchasing Status</h4>
                    <form action="" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $selectedOrder['id'] ?>">

                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:700; color:var(--brand-dark); margin-bottom:0.35rem;">Current Status</label>
                            <select name="order_status" class="form-control" style="width:100%; padding:0.5rem 0.75rem; font-weight:700; border-radius:var(--radius-md);">
                                <?php foreach ($allowedStatuses as $st): ?>
                                    <option value="<?= $st ?>" <?= $selectedOrder['order_status'] === $st ? 'selected' : '' ?>>
                                        <?= $st === 'Production' ? 'In Production' : $st ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:700; color:var(--brand-dark); margin-bottom:0.35rem;">Internal Production Notes</label>
                            <textarea name="admin_notes" rows="4" class="form-control" style="width:100%; font-size:0.82rem; resize:vertical; border-radius:var(--radius-md);" placeholder="Enter your Internal Production Notes"><?= sanitize($selectedOrder['admin_notes'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.65rem 1rem; font-weight:800; border-radius:var(--radius-md); box-shadow:0 3px 10px rgba(255,103,2,0.3);">
                            Save Status Change
                        </button>
                    </form>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.65rem; margin-top:0.85rem; padding-top:0.85rem; border-top:1px solid #F1F5F9;">
                        <button type="button" class="btn btn-sm" style="justify-content:center; background:#FEF2F2; color:#DC2626; border:1.5px solid #FCA5A5; font-weight:800; padding:0.55rem; border-radius:var(--radius-md); cursor:pointer;" onclick="openCancelOrderModal(<?= $selectedOrder['id'] ?>, '<?= htmlspecialchars($selectedOrder['order_reference']) ?>')">
                            ✕ Cancel Order
                        </button>
                        <button type="button" class="btn btn-sm" style="justify-content:center; background:#F5F3FF; color:#7C3AED; border:1.5px solid #DDD6FE; font-weight:800; padding:0.55rem; border-radius:var(--radius-md); cursor:pointer;" onclick="openRefundOrderModal(<?= $selectedOrder['id'] ?>, '<?= htmlspecialchars($selectedOrder['order_reference']) ?>', <?= (float)$selectedOrder['total_amount'] ?>)">
                            ↩ Issue Refund
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- ==================== ALL ORDERS OVERVIEW (KPI CARDS + REQUISITIONS TABLE) ==================== -->
    <!-- KPI Top Cards (Symmetrically Aligned & Easy to Identify) -->
    <div class="orders-kpi-grid">
        <div class="stat-card-kpi" style="border-top: 3.5px solid var(--brand-primary);">
            <span class="stat-label">Total Purchasing Value</span>
            <div class="stat-num" style="color:var(--brand-primary);"><?= formatCurrency($totalRevenue) ?></div>
            <div class="stat-sub" title="<?= htmlspecialchars($activeSchoolDisplay) ?>"><?= htmlspecialchars($activeSchoolDisplay) ?></div>
        </div>
        <div class="stat-card-kpi" style="border-top: 3.5px solid #0F172A;">
            <span class="stat-label">Total Parent Purchases</span>
            <div class="stat-num" style="color:#0F172A;"><?= $totalPurchases ?></div>
            <div class="stat-sub" title="<?= htmlspecialchars($activeSchoolDisplay) ?>"><?= htmlspecialchars($activeSchoolDisplay) ?></div>
        </div>
        <div class="stat-card-kpi" style="border-top: 3.5px solid #d97706;">
            <span class="stat-label">Confirmed Orders</span>
            <div class="stat-num" style="color:#d97706;"><?= $confirmedCount ?></div>
            <div class="stat-sub">Awaiting production</div>
        </div>
        <div class="stat-card-kpi" style="border-top: 3.5px solid #dc2626;">
            <span class="stat-label">Cancelled Orders</span>
            <div class="stat-num" style="color:#dc2626;"><?= $cancelledCount ?></div>
            <div class="stat-sub">Cancelled requisitions</div>
        </div>
        <div class="stat-card-kpi" style="border-top: 3.5px solid #9333ea;">
            <span class="stat-label">Refunded Orders</span>
            <div class="stat-num" style="color:#9333ea;"><?= $refundedCount ?></div>
            <div class="stat-sub">Returned / Reimbursed</div>
        </div>
        <div class="stat-card-kpi" style="border-top: 3.5px solid #16a34a;">
            <span class="stat-label">Dispatched</span>
            <div class="stat-num" style="color:#16a34a;"><?= $dispatchedCount ?></div>
            <div class="stat-sub">Courier / In-Hand Complete</div>
        </div>
    </div>

<!-- Orders List Header with Export Actions -->
<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:0.85rem; flex-wrap:wrap; gap:1rem; width:100%; box-sizing:border-box;">
    <div>
        <h3 class="heading-2" style="font-size:1.25rem; margin:0;">Parent Purchasing Requisitions (<?= count($ordersList) ?>)</h3>
        <p style="font-size:0.8rem; color:var(--brand-muted); margin:0.2rem 0 0 0;">Manage parent uniform purchases and send direct updates to parents.</p>
    </div>

    <!-- Quick Export Actions (Always visible, perfectly right-aligned) -->
    <div style="display:inline-flex; gap:0.4rem; align-items:center; flex-shrink:0;">
        <a href="<?= BASE_URL ?>/admin/create_order.php" class="btn btn-primary btn-sm" style="padding:0.4rem 0.85rem; font-size:0.8rem; font-weight:800; display:inline-flex; align-items:center; gap:5px; border-radius:var(--radius-md); box-shadow:0 1px 3px rgba(255,103,2,0.3); text-decoration:none;">
            <span>➕</span> New Walk-in Order
        </a>
        <button type="button" onclick="exportOrders('excel')" class="btn btn-outline btn-sm" style="padding:0.4rem 0.75rem; font-size:0.8rem; font-weight:700; color:#059669; border-color:#10B981; background:#ffffff; display:inline-flex; align-items:center; gap:5px; box-shadow:0 1px 2px rgba(0,0,0,0.04); cursor:pointer; white-space:nowrap; border-radius:var(--radius-md);" title="Export filtered orders to Excel spreadsheet (.xls)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>
            Excel
        </button>
        <button type="button" onclick="exportOrders('pdf')" class="btn btn-outline btn-sm" style="padding:0.4rem 0.75rem; font-size:0.8rem; font-weight:700; color:#DC2626; border-color:#EF4444; background:#ffffff; display:inline-flex; align-items:center; gap:5px; box-shadow:0 1px 2px rgba(0,0,0,0.04); cursor:pointer; white-space:nowrap; border-radius:var(--radius-md);" title="Export filtered orders to PDF report">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            PDF
        </button>
    </div>
</div>

<!-- ==================== DEDICATED SORT & FILTER TOOLBAR BOX ==================== -->
<div style="background:#ffffff; border:1px solid var(--brand-border); border-radius:var(--radius-lg); padding:0.75rem 1rem; margin-bottom:1.25rem; box-shadow:var(--shadow-sm); width:100%; box-sizing:border-box;">
    <form action="" method="GET" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; margin:0; width:100%;">
        <div style="display:inline-flex; align-items:center; gap:0.35rem; color:var(--brand-muted); font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            Filter:
        </div>

        <!-- Status Filter -->
        <select name="status" class="form-control" style="padding:0.4rem 0.65rem; font-size:0.8rem; width:140px; border-radius:var(--radius-md);" onchange="this.form.submit()">
            <option value="">Select Status</option>
            <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Main Schools Filter (shows only main schools) -->
        <select name="school_id" id="school_id_select" class="form-control" style="padding:0.4rem 0.65rem; font-size:0.8rem; width:190px; border-radius:var(--radius-md);" onchange="handleMainSchoolFilterChange(this)">
            <option value="">Select School</option>
            <?php foreach ($mainSchools as $s): ?>
                <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Under Schools Filter (shows all under schools) -->
        <select name="sub_school_id" id="sub_school_id_select" class="form-control" style="padding:0.4rem 0.65rem; font-size:0.8rem; width:190px; border-radius:var(--radius-md);" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?>>
            <option value=""><?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'No Under Schools' : 'Select Under School' ?></option>
            <?php foreach ($underSchools as $sub): ?>
                <?php 
                    if ($schoolFilter > 0 && (int)$sub['parent_id'] !== $schoolFilter) {
                        continue;
                    }
                ?>
                <option value="<?= $sub['id'] ?>" data-parent="<?= (int)$sub['parent_id'] ?>" <?= $subSchoolFilter === (int)$sub['id'] ? 'selected' : '' ?>>
                    <?= sanitize($sub['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Search Bar with Search Icon -->
        <div style="flex:1; min-width:180px; max-width:260px; position:relative;">
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Enter your Order Reference, Student, or Phone" class="form-control" style="width:100%; padding:0.4rem 0.65rem 0.4rem 2rem; font-size:0.8rem; border-radius:var(--radius-md);">
            <svg style="position:absolute; left:8px; top:50%; transform:translateY(-50%); width:13px; height:13px; color:var(--brand-muted); pointer-events:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>

        <button type="submit" class="btn btn-primary btn-sm" style="padding:0.4rem 0.85rem; font-size:0.8rem; font-weight:700; border-radius:var(--radius-md);">Filter</button>

        <?php if (!empty($statusFilter) || !empty($schoolFilter) || !empty($subSchoolFilter) || !empty($search)): ?>
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm" style="padding:0.4rem 0.75rem; font-size:0.8rem; font-weight:700; color:#DC2626; border-color:#FCA5A5; background:#FEF2F2; border-radius:var(--radius-md);" title="Clear all active filters">
                ✕ Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ==================== BULK STATUS ACTIONS TOOLBAR ==================== -->
<div id="bulk-status-toolbar" style="background:#FFF9F5; border:1.5px solid #FFEDD5; border-radius:var(--radius-xl); padding:0.85rem 1.25rem; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; box-shadow:0 2px 8px rgba(255,103,2,0.06); width:100%; box-sizing:border-box;">
    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
        <span style="background:#FF6702; color:#ffffff; font-size:0.78rem; font-weight:800; padding:0.25rem 0.75rem; border-radius:9999px; letter-spacing:0.02em;">
            <span id="bulk-selected-count">0</span> Orders Selected
        </span>
        <button type="button" class="btn btn-outline btn-sm" onclick="selectAllVisibleOrders(true)" style="padding:0.32rem 0.75rem; font-size:0.78rem; font-weight:700; background:#ffffff;">
            Select All Visible (<?= count($ordersList) ?>)
        </button>
        <button type="button" class="btn btn-outline btn-sm" id="btn-deselect-all" onclick="selectAllVisibleOrders(false)" style="padding:0.32rem 0.75rem; font-size:0.78rem; font-weight:700; background:#ffffff; display:none;">
            Clear Selection
        </button>
        <span style="font-size:0.82rem; color:var(--brand-muted);">Bulk update status:</span>
    </div>

    <div style="display:flex; align-items:center; gap:0.6rem; margin:0; flex-wrap:wrap;">
        <select id="bulk-new-status" class="form-control" style="padding:0.38rem 0.75rem; font-size:0.82rem; font-weight:700; width:auto; border-radius:var(--radius-md); border-color:#FFC499;" onchange="onBulkStatusSelectChange()">
            <option value="" disabled selected>Select Status</option>
            <?php foreach ($allowedStatuses as $st): ?>
                <option value="<?= $st ?>"><?= $st === 'Production' ? 'In Production' : $st ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="btn-apply-bulk-status" class="btn btn-primary btn-sm" onclick="applyBulkOrderStatus()" style="background:#FF6702; color:#ffffff; font-weight:800; padding:0.42rem 1.1rem; border-radius:var(--radius-md); box-shadow:0 2px 8px rgba(255,103,2,0.3); opacity:0.5; cursor:not-allowed;" disabled>
            Apply
        </button>
    </div>
</div>

<!-- ==================== ORDERS DATA TABLE (BOX ALIGNED) ==================== -->
<div class="orders-table-wrapper">
    <table class="orders-table">
        <thead>
            <tr>
                <th style="width:34px; text-align:center;">
                    <input type="checkbox" id="master-orders-checkbox" onchange="toggleMasterOrders(this.checked)" title="Select all orders" style="width:15px; height:15px; cursor:pointer; accent-color:#FF6702; vertical-align:middle; margin:0;">
                </th>
                <th style="white-space:nowrap;">Order Ref</th>
                <th style="max-width:125px;">School</th>
                <th style="max-width:115px;">Student &amp; Grade</th>
                <th style="max-width:115px;">Parent &amp; Phone</th>
                <th style="text-align:center; white-space:nowrap;">Items</th>
                <th style="text-align:center; white-space:nowrap;">Payment</th>
                <th style="text-align:right; white-space:nowrap;">Total Value</th>
                <th style="text-align:center; white-space:nowrap;">Status</th>
                <th style="text-align:center; white-space:nowrap;">Date</th>
                <th style="text-align:right; white-space:nowrap; padding-right:0.6rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ordersList)): ?>
                <tr><td colspan="11" style="text-align:center; padding:3rem 1.5rem; color:var(--brand-muted);">No purchasing records match the selected criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($ordersList as $o): ?>
                    <tr id="order-row-<?= $o['id'] ?>">
                        <td style="text-align:center;">
                            <input type="checkbox" class="order-row-checkbox" value="<?= $o['id'] ?>" onchange="syncOrderCheckboxes()" style="width:15px; height:15px; cursor:pointer; accent-color:#FF6702; vertical-align:middle; margin:0;">
                        </td>
                        <td style="font-weight:800; color:var(--brand-primary); white-space:nowrap;"><?= sanitize($o['order_reference']) ?></td>
                        <td style="font-weight:600; line-height:1.25; max-width:125px; word-break:break-word; font-size:0.78rem;"><?= sanitize($o['school_name'] ?: 'International School') ?></td>
                        <td style="line-height:1.25; max-width:115px; word-break:break-word;">
                            <div style="font-weight:700; color:#0F172A; font-size:0.78rem;"><?= sanitize($o['student_name']) ?></div>
                            <div style="font-size:0.72rem; color:var(--brand-muted);"><?= sanitize($o['grade_class']) ?></div>
                        </td>
                        <td style="line-height:1.25; max-width:115px; word-break:break-word;">
                            <div style="font-weight:600; color:#1E293B; font-size:0.78rem;"><?= sanitize($o['parent_name']) ?></div>
                            <div style="font-size:0.72rem; color:var(--brand-muted); font-weight:600;"><?= sanitize($o['mobile']) ?></div>
                        </td>
                        <td style="font-weight:700; text-align:center; white-space:nowrap; font-size:0.76rem;"><?= $o['total_items'] ?> pcs</td>
                        <td style="text-align:center; white-space:nowrap;">
                            <?php if ($o['payment_status'] === 'Paid'): ?>
                                <span style="background:#DCFCE7; color:#15803D; font-weight:800; font-size:0.7rem; padding:0.18rem 0.5rem; border-radius:9999px; display:inline-block;">✓ Paid</span>
                            <?php elseif ($o['payment_status'] === 'Failed'): ?>
                                <span style="background:#FEF2F2; color:#DC2626; font-weight:800; font-size:0.7rem; padding:0.18rem 0.5rem; border-radius:9999px; display:inline-block;">✕ Failed</span>
                            <?php else: ?>
                                <span style="background:#FEF3C7; color:#B45309; font-weight:800; font-size:0.7rem; padding:0.18rem 0.5rem; border-radius:9999px; display:inline-block;">Pending</span>
                            <?php endif; ?>
                            <div style="font-size:0.67rem; font-weight:700; color:#475569; margin-top:3px; max-width:145px; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars(formatDisplayPaymentMethod($o['payment_method'])) ?>">
                                <?= htmlspecialchars(formatDisplayPaymentMethod($o['payment_method'])) ?>
                            </div>
                            <?php 
                            $orderTxnId = !empty($o['gateway_transaction_id']) ? $o['gateway_transaction_id'] : ($o['payment_txn_ref'] ?? '');
                            if (!empty($orderTxnId)): ?>
                                <div style="font-size:0.65rem; color:#0284C7; font-family:monospace; font-weight:700; margin-top:2px; background:#F0F9FF; padding:1px 5px; border-radius:4px; display:inline-block; max-width:145px; overflow:hidden; text-overflow:ellipsis;" title="Gateway Transaction ID: <?= htmlspecialchars($orderTxnId) ?>">
                                    Txn: <?= htmlspecialchars($orderTxnId) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:800; color:var(--brand-dark); text-align:right; white-space:nowrap; font-size:0.82rem;"><?= formatCurrency($o['total_amount']) ?></td>
                        <td style="text-align:center; white-space:nowrap;">
                            <!-- Inline Status Dropdown (Update status each by each comfortably) -->
                            <div class="quick-status-wrap" style="position:relative; display:inline-flex; align-items:center;">
                                <select class="order-status-quick-select" 
                                        id="order-status-select-<?= $o['id'] ?>"
                                        data-order-id="<?= $o['id'] ?>" 
                                        data-current="<?= htmlspecialchars($o['order_status']) ?>"
                                        onchange="changeSingleOrderStatus(<?= $o['id'] ?>, this.value, this)" 
                                        style="padding:0.22rem 1.35rem 0.22rem 0.55rem; font-size:0.7rem; font-weight:800; border-radius:9999px; cursor:pointer; width:auto; text-transform:uppercase; letter-spacing:0.02em; appearance:none; -webkit-appearance:none; border-width:1.5px; border-style:solid; outline:none; transition:all 0.2s; <?= getOrderStatusSelectStyle($o['order_status']) ?>">
                                    <?php foreach ($allowedStatuses as $st): ?>
                                        <option value="<?= $st ?>" <?= $o['order_status'] === $st ? 'selected' : '' ?> style="background:#ffffff; color:#0F172A; font-weight:700;">
                                            <?= $st === 'Production' ? 'In Production' : $st ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <svg style="position:absolute; right:6px; pointer-events:none; width:9px; height:9px; opacity:0.7;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </div>
                        </td>
                        <td style="text-align:center; font-size:0.75rem; color:var(--brand-muted); white-space:nowrap;"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        <td style="text-align:right; white-space:nowrap; padding-right:0.6rem;">
                            <div style="display:inline-flex; gap:0.4rem; align-items:center; justify-content:flex-end;">
                                <button type="button" 
                                        class="btn btn-outline btn-sm" 
                                        style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; color:#2563EB; border-color:#BFDBFE; background:#F8FAFC; border-radius:var(--radius-md);" 
                                        title="View Order Details (Popup)" 
                                        onclick="openOrderViewModal(<?= $o['id'] ?>)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>

                                <a href="<?= BASE_URL ?>/admin/orders.php?view=<?= $o['id'] ?>" 
                                   class="btn btn-primary btn-sm" 
                                   style="padding:0.35rem 0.55rem; line-height:1; display:inline-flex; align-items:center; justify-content:center; background:var(--brand-primary); color:#ffffff; box-shadow:0 2px 6px rgba(255,103,2,0.25); border-radius:var(--radius-md);" 
                                   title="Edit &amp; Manage Order (Manage Page)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                </a>

                                <button type="button" 
                                        class="btn btn-outline btn-sm" 
                                        style="padding:0.35rem 0.55rem; line-height:1; color:#dc2626; border-color:rgba(220,38,38,0.3); background:#ffffff; display:inline-flex; align-items:center; justify-content:center; border-radius:var(--radius-md);" 
                                        title="Delete Order (Confirmation Popup)" 
                                        onclick="openDeleteOrderModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_reference']) ?>')">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ==================== SIMPLE CUSTOMER MESSAGING MODAL ==================== -->
<div class="order-modal-backdrop" id="customer-message-modal">
    <div class="order-modal-box">
        <div style="padding:1.25rem 1.75rem; border-bottom:1px solid var(--brand-border); display:flex; justify-content:space-between; align-items:center; background:var(--brand-surface-alt);">
            <div>
                <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--brand-dark);">Send Message to Parent</h3>
                <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.2rem;" id="modal-subtitle">Direct customer notification desk</div>
            </div>
            <button type="button" onclick="closeMessageModal()" style="background:none; border:none; font-size:1.6rem; line-height:1; color:var(--brand-muted); cursor:pointer;">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/admin/orders.php" method="POST" id="msg-form" style="padding:1.5rem 1.75rem;">
            <input type="hidden" name="action" value="log_message">
            <input type="hidden" name="order_id" id="modal-order-id" value="">
            <input type="hidden" name="parent_name" id="modal-parent-name" value="">
            <input type="hidden" name="parent_mobile" id="modal-parent-mobile" value="">

            <!-- Recipient Preview Card -->
            <div style="background:rgba(255,103,2,0.06); border:1px solid rgba(255,103,2,0.2); border-radius:var(--radius-md); padding:0.85rem 1rem; margin-bottom:1.25rem; font-size:0.85rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                    <span><strong>Parent:</strong> <span id="disp-parent"></span></span>
                    <span><strong>Order Ref:</strong> <span id="disp-ref" style="color:var(--brand-primary); font-weight:800;"></span></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span><strong>Student:</strong> <span id="disp-student"></span></span>
                    <span><strong>Mobile:</strong> <span id="disp-phone" style="font-weight:700;"></span></span>
                </div>
            </div>

            <!-- Quick Template Selector -->
            <div style="margin-bottom:1rem;">
                <label style="font-size:0.78rem; font-weight:800; color:var(--brand-muted); text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:0.4rem;">Quick Templates (Click to fill):</label>
                <div>
                    <span class="template-pill" onclick="applyTemplate('confirmed')">✓ Order Confirmed</span>
                    <span class="template-pill" onclick="applyTemplate('production')">✂️ In Production</span>
                    <span class="template-pill" onclick="applyTemplate('pickup')">📦 Ready for Pickup</span>
                    <span class="template-pill" onclick="applyTemplate('dispatched')">🚚 Dispatched</span>
                </div>
            </div>

            <!-- Message Textarea -->
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label">Message Content</label>
                <textarea name="message_text" id="modal-message-text" required class="form-control" style="min-height:110px; font-size:0.9rem; line-height:1.5;"></textarea>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeMessageModal()">Cancel</button>

                <button type="button" class="btn btn-secondary btn-sm" onclick="sendViaWhatsApp()" style="background:#25D366; color:#ffffff; border-color:#25D366; display:inline-flex; align-items:center; gap:0.4rem;">
                    <span>Open in WhatsApp</span> &rarr;
                </button>

                <button type="submit" class="btn btn-primary btn-sm">
                    Log to History
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== BULK CONFIRMATION POPUP MODAL ==================== -->
<div class="order-modal-backdrop" id="bulk-confirm-modal" style="z-index:999999;">
    <div class="order-modal-box" style="max-width:440px; border-radius:24px; border:1.5px solid #FFEDD5; box-shadow:0 25px 50px -12px rgba(255,103,2,0.28); overflow:hidden; text-align:center; padding:2.2rem 2.25rem; background:#ffffff;">
        <!-- Glowing Pulse Icon -->
        <div style="width:68px; height:68px; border-radius:50%; background:rgba(255,103,2,0.1); border:2px solid rgba(255,103,2,0.3); display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.25rem;">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#FF6702" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>

        <h3 style="margin:0 0 0.6rem 0; font-size:1.35rem; font-weight:800; color:#0F172A;">Update Selected Orders?</h3>
        
        <p style="font-size:0.92rem; color:#475569; margin:0 0 1.5rem 0; line-height:1.55;">
            Are you sure you want to change the status of <strong id="bulk-popup-count" style="color:#FF6702; font-weight:800;">0</strong> selected orders to <span id="bulk-popup-status" style="background:#DBEAFE; color:#1D4ED8; font-weight:800; padding:0.25rem 0.75rem; border-radius:9999px; text-transform:uppercase; font-size:0.8rem; display:inline-block; margin-top:0.35rem; border:1px solid #BFDBFE;">"CONFIRMED"</span>?
        </p>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
            <button type="button" onclick="closeBulkConfirmModal()" class="btn btn-outline" style="padding:0.7rem 1rem; font-weight:700; border-radius:12px; justify-content:center; font-size:0.9rem; background:#ffffff; color:#64748B; border-color:#CBD5E1; cursor:pointer;">
                Cancel
            </button>
            <button type="button" id="btn-popup-confirm-bulk" onclick="confirmAndExecuteBulkStatusUpdate()" class="btn btn-primary" style="padding:0.7rem 1rem; font-weight:800; border-radius:12px; justify-content:center; font-size:0.9rem; background:#FF6702; color:#ffffff; box-shadow:0 4px 14px rgba(255,103,2,0.35); cursor:pointer;">
                Yes, Update Status
            </button>
        </div>
    </div>
</div>

<!-- ==================== CANCEL ORDER CONFIRMATION MODAL (Requirement 2) ==================== -->
<div class="order-modal-backdrop" id="cancel-order-modal" style="z-index:999999;">
    <div class="order-modal-box" style="max-width:440px; border-radius:24px; border:1.5px solid #FECACA; box-shadow:0 25px 50px -12px rgba(220,38,38,0.25); overflow:hidden; text-align:center; padding:2rem 2.25rem; background:#ffffff;">
        <div style="width:64px; height:64px; border-radius:50%; background:#FEE2E2; border:2px solid #FCA5A5; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>

        <h3 style="margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:800; color:#0F172A;">Cancel Order?</h3>
        
        <p style="font-size:0.88rem; color:#475569; margin:0 0 1.25rem 0; line-height:1.5;">
            Are you sure you want to cancel Order <strong id="cancel-modal-ref" style="color:#DC2626; font-weight:800;"></strong>?
        </p>

        <form action="<?= BASE_URL ?>/admin/orders.php" method="POST" id="cancel-form">
            <input type="hidden" name="action" value="cancel_order">
            <input type="hidden" name="order_id" id="cancel-modal-order-id" value="">

            <div style="text-align:left; margin-bottom:1.25rem;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Reason for Cancellation (Optional)</label>
                <input type="text" name="cancel_reason" id="cancel-modal-reason" placeholder="Enter your Reason for Cancellation" class="form-control" style="width:100%; font-size:0.85rem; padding:0.5rem 0.75rem; border-radius:8px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <button type="button" onclick="closeCancelOrderModal()" class="btn btn-outline" style="padding:0.65rem 1rem; font-weight:700; border-radius:10px; justify-content:center; font-size:0.88rem; background:#ffffff; color:#64748B; border-color:#CBD5E1; cursor:pointer;">
                    No, Keep
                </button>
                <button type="submit" class="btn" style="padding:0.65rem 1rem; font-weight:800; border-radius:10px; justify-content:center; font-size:0.88rem; background:#DC2626; color:#ffffff; border:none; box-shadow:0 4px 14px rgba(220,38,38,0.3); cursor:pointer;">
                    Yes, Cancel Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== REFUND ORDER MODAL (Requirement 2) ==================== -->
<div class="order-modal-backdrop" id="refund-order-modal" style="z-index:999999;">
    <div class="order-modal-box" style="max-width:460px; border-radius:24px; border:1.5px solid #DDD6FE; box-shadow:0 25px 50px -12px rgba(124,58,237,0.25); overflow:hidden; text-align:center; padding:2rem 2.25rem; background:#ffffff;">
        <div style="width:64px; height:64px; border-radius:50%; background:#EDE9FE; border:2px solid #C4B5FD; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                <path d="M3 3v5h5"></path>
                <path d="M12 7v5l3 3"></path>
            </svg>
        </div>

        <h3 style="margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:800; color:#0F172A;">Process Order Refund</h3>
        
        <p style="font-size:0.88rem; color:#475569; margin:0 0 1.25rem 0; line-height:1.5;">
            Initiate refund for Order <strong id="refund-modal-ref" style="color:#7C3AED; font-weight:800;"></strong>.
        </p>

        <form action="<?= BASE_URL ?>/admin/orders.php" method="POST" id="refund-form">
            <input type="hidden" name="action" value="refund_order">
            <input type="hidden" name="order_id" id="refund-modal-order-id" value="">

            <div style="text-align:left; margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Refund Amount (₹) *</label>
                <input type="number" step="0.01" name="refund_amount" id="refund-modal-amount" required class="form-control" style="width:100%; font-size:0.95rem; font-weight:700; color:#7C3AED; padding:0.5rem 0.75rem; border-radius:8px;">
            </div>

            <div style="text-align:left; margin-bottom:1.25rem;">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Refund Notes / Transaction ID</label>
                <input type="text" name="refund_notes" id="refund-modal-notes" placeholder="Enter your Refund Notes / Transaction ID" class="form-control" style="width:100%; font-size:0.85rem; padding:0.5rem 0.75rem; border-radius:8px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <button type="button" onclick="closeRefundOrderModal()" class="btn btn-outline" style="padding:0.65rem 1rem; font-weight:700; border-radius:10px; justify-content:center; font-size:0.88rem; background:#ffffff; color:#64748B; border-color:#CBD5E1; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn" style="padding:0.65rem 1rem; font-weight:800; border-radius:10px; justify-content:center; font-size:0.88rem; background:#7C3AED; color:#ffffff; border:none; box-shadow:0 4px 14px rgba(124,58,237,0.3); cursor:pointer;">
                    Yes, Issue Refund
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== VIEW ORDER DETAILS MODAL (Details Only Popup) ==================== -->
<div class="order-modal-backdrop" id="order-view-modal" style="z-index:999999;">
    <div class="order-modal-box" style="max-width:680px; width:100%; border-radius:24px; border:1.5px solid #BFDBFE; box-shadow:0 25px 60px -12px rgba(37,99,235,0.25); overflow:hidden; background:#ffffff; text-align:left; animation:modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header -->
        <div style="background:linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); padding:1.25rem 1.75rem; border-bottom:1.5px solid #BFDBFE; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <span style="font-size:1.3rem;">📄</span>
                    <h3 id="ovm-ref" style="margin:0; font-size:1.25rem; font-weight:800; color:#1E3A8A;">Order Details</h3>
                    <span id="ovm-status-badge" style="font-size:0.75rem; font-weight:800; text-transform:uppercase; padding:0.2rem 0.65rem; border-radius:9999px;"></span>
                </div>
                <div style="font-size:0.78rem; color:#64748B; margin-top:0.25rem;" id="ovm-school-meta"></div>
            </div>
            <button type="button" onclick="closeOrderViewModal()" style="background:transparent; border:none; font-size:1.6rem; color:#64748B; cursor:pointer; line-height:1; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.2s;" onmouseover="this.style.background='#BFDBFE'; this.style.color='#1E3A8A';" onmouseout="this.style.background='transparent'; this.style.color='#64748B';">&times;</button>
        </div>

        <!-- Body -->
        <div style="padding:1.5rem 1.75rem; max-height:75vh; overflow-y:auto;">
            <!-- Student & Parent Details Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:1.25rem; margin-bottom:1.25rem; font-size:0.85rem;">
                <div>
                    <div style="font-weight:800; text-transform:uppercase; font-size:0.72rem; letter-spacing:0.05em; margin-bottom:0.5rem; color:#2563EB;">Student Details</div>
                    <div style="margin-bottom:0.3rem;"><strong>Student:</strong> <span id="ovm-student-name"></span></div>
                    <div style="margin-bottom:0.3rem;"><strong>Grade &amp; Section:</strong> <span id="ovm-grade"></span></div>
                    <div><strong>Date Placed:</strong> <span id="ovm-date"></span></div>
                </div>
                <div>
                    <div style="font-weight:800; text-transform:uppercase; font-size:0.72rem; letter-spacing:0.05em; margin-bottom:0.5rem; color:#2563EB;">Parent &amp; Delivery</div>
                    <div style="margin-bottom:0.3rem;"><strong>Parent Name:</strong> <span id="ovm-parent-name"></span></div>
                    <div style="margin-bottom:0.3rem;"><strong>Contact Phone:</strong> <span id="ovm-phone"></span></div>
                    <div><strong>Address:</strong> <span id="ovm-address"></span></div>
                </div>
            </div>

            <!-- Payment & Order Status Row -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#FFF7ED; border:1px solid #FFEDD5; border-radius:12px; padding:0.85rem 1.25rem; margin-bottom:1.25rem; font-size:0.85rem;">
                <div>
                    <div><strong>Payment Method:</strong> <span id="ovm-payment-method"></span> &bull; <strong>Status:</strong> <span id="ovm-payment-status"></span></div>
                    <div style="margin-top:0.35rem;"><strong>Gateway Transaction ID:</strong> <span id="ovm-txn-id" style="font-family:monospace; font-weight:700; color:#0284C7; background:#EFF6FF; padding:2px 7px; border-radius:4px;"></span></div>
                </div>
                <div style="font-size:1.1rem; font-weight:800; color:#FF6702;">
                    Total: <span id="ovm-total-amount"></span>
                </div>
            </div>

            <!-- Purchased Uniform Items -->
            <div>
                <h4 style="margin:0 0 0.65rem 0; font-size:0.95rem; font-weight:800; color:#0F172A;">Purchased Uniform Items</h4>
                <div style="border:1px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left;">
                        <thead>
                            <tr style="background:#F1F5F9; color:#475569; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em;">
                                <th style="padding:0.6rem 0.85rem;">Item</th>
                                <th style="padding:0.6rem 0.85rem;">Size</th>
                                <th style="padding:0.6rem 0.85rem; text-align:center;">Qty</th>
                                <th style="padding:0.6rem 0.85rem; text-align:right;">Price</th>
                                <th style="padding:0.6rem 0.85rem; text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="ovm-items-tbody">
                            <!-- Items populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:1rem 1.75rem; border-top:1px solid #E2E8F0; background:#F8FAFC; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:0.6rem; align-items:center;">
                <button type="button" id="ovm-print-receipt-btn" class="btn btn-outline btn-sm" style="font-weight:700; color:#FF6702; border-color:#FF6702; background:#ffffff; display:inline-flex; align-items:center; gap:5px; border-radius:var(--radius-md);">
                    <span>🖨️</span> Print Tax Receipt
                </button>
            </div>
            <button type="button" onclick="closeOrderViewModal()" class="btn btn-primary" style="background:#2563EB; border:none; padding:0.5rem 1.4rem; font-weight:800; border-radius:9999px; cursor:pointer;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ==================== DELETE ORDER CONFIRMATION MODAL ==================== -->
<div class="order-modal-backdrop" id="delete-order-modal" style="z-index:999999;">
    <div class="order-modal-box" style="max-width:440px; border-radius:24px; border:1.5px solid #FECACA; box-shadow:0 25px 50px -12px rgba(220,38,38,0.25); overflow:hidden; text-align:center; padding:2rem 2.25rem; background:#ffffff;">
        <div style="width:64px; height:64px; border-radius:50%; background:#FEE2E2; border:2px solid #FCA5A5; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
        </div>

        <h3 style="margin:0 0 0.5rem 0; font-size:1.3rem; font-weight:800; color:#0F172A;">Delete Order?</h3>
        
        <p style="font-size:0.88rem; color:#475569; margin:0 0 1.25rem 0; line-height:1.5;">
            Are you sure you want to permanently delete Order <strong id="delete-modal-ref" style="color:#DC2626; font-weight:800;"></strong>?<br>
            <span style="font-size:0.8rem; color:#EF4444; font-weight:600; display:inline-block; margin-top:0.35rem;">⚠️ This action cannot be undone. All items and records for this order will be permanently erased.</span>
        </p>

        <form action="<?= BASE_URL ?>/admin/orders.php" method="POST" id="delete-form">
            <input type="hidden" name="action" value="delete_order">
            <input type="hidden" name="order_id" id="delete-modal-order-id" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <button type="button" onclick="closeDeleteOrderModal()" class="btn btn-outline" style="padding:0.65rem 1rem; font-weight:700; border-radius:10px; justify-content:center; font-size:0.88rem; background:#ffffff; color:#64748B; border-color:#CBD5E1; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn" style="padding:0.65rem 1rem; font-weight:800; border-radius:10px; justify-content:center; font-size:0.88rem; background:#DC2626; color:#ffffff; border:none; box-shadow:0 4px 14px rgba(220,38,38,0.3); cursor:pointer;">
                    Yes, Delete Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentOrderData = {};

// Color map for status styling on instant change
const statusStyleMap = {
    'Pending': { bg: '#FEF3C7', text: '#B45309', border: '#FDE68A' },
    'Confirmed': { bg: '#DBEAFE', text: '#1D4ED8', border: '#BFDBFE' },
    'In progress': { bg: '#F3E8FF', text: '#7E22CE', border: '#D8B4FE' },
    'Production': { bg: '#F3E8FF', text: '#7E22CE', border: '#D8B4FE' },
    'Packed': { bg: '#FFEDD5', text: '#C2410C', border: '#FDBA74' },
    'Dispatched': { bg: '#E0F2FE', text: '#0369A1', border: '#7DD3FC' },
    'Dispatched by hand': { bg: '#FEF3C7', text: '#B45309', border: '#FDE68A' },
    'On the way': { bg: '#E0E7FF', text: '#4338CA', border: '#A5B4FC' },
    'Delivered': { bg: '#DCFCE7', text: '#15803D', border: '#86EFAC' },
    'Cancelled': { bg: '#FEE2E2', text: '#DC2626', border: '#FCA5A5' },
    'Cancel': { bg: '#FEE2E2', text: '#DC2626', border: '#FCA5A5' },
    'Refunded': { bg: '#EDE9FE', text: '#6D28D9', border: '#DDD6FE' },
    'Refund': { bg: '#EDE9FE', text: '#6D28D9', border: '#DDD6FE' }
};

function openCancelOrderModal(orderId, ref) {
    document.getElementById('cancel-modal-order-id').value = orderId;
    document.getElementById('cancel-modal-ref').textContent = '#' + ref;
    document.getElementById('cancel-modal-reason').value = '';
    document.getElementById('cancel-order-modal').classList.add('active');
}

function closeCancelOrderModal() {
    document.getElementById('cancel-order-modal').classList.remove('active');
}

function openRefundOrderModal(orderId, ref, amount) {
    document.getElementById('refund-modal-order-id').value = orderId;
    document.getElementById('refund-modal-ref').textContent = '#' + ref;
    document.getElementById('refund-modal-amount').value = parseFloat(amount || 0).toFixed(2);
    document.getElementById('refund-modal-notes').value = '';
    document.getElementById('refund-order-modal').classList.add('active');
}

function closeRefundOrderModal() {
    document.getElementById('refund-order-modal').classList.remove('active');
}

// ==================== VIEW ORDER DETAILS MODAL (Details Only Popup) ====================
function openOrderViewModal(orderId) {
    const modal = document.getElementById('order-view-modal');
    if (!modal) return;
    
    document.getElementById('ovm-ref').textContent = 'Loading...';
    document.getElementById('ovm-status-badge').textContent = '';
    document.getElementById('ovm-status-badge').style.backgroundColor = 'transparent';
    document.getElementById('ovm-status-badge').style.borderColor = 'transparent';
    document.getElementById('ovm-school-meta').textContent = '';
    document.getElementById('ovm-student-name').textContent = '...';
    document.getElementById('ovm-grade').textContent = '...';
    document.getElementById('ovm-date').textContent = '...';
    document.getElementById('ovm-parent-name').textContent = '...';
    document.getElementById('ovm-phone').textContent = '...';
    document.getElementById('ovm-address').textContent = '...';
    document.getElementById('ovm-payment-method').textContent = '...';
    document.getElementById('ovm-payment-status').textContent = '...';
    document.getElementById('ovm-total-amount').textContent = '...';
    document.getElementById('ovm-items-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:1.5rem; color:#64748B;">Loading items...</td></tr>';
    
    modal.classList.add('active');

    const printBtn = document.getElementById('ovm-print-receipt-btn');
    if (printBtn) {
        printBtn.onclick = () => {
            window.open('<?= BASE_URL ?>/admin/create_order.php?print_receipt=' + orderId, '_blank', 'width=800,height=900');
        };
    }

    fetch('<?= BASE_URL ?>/admin/orders.php?ajax_order_details=' + orderId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.order) {
                const o = data.order;
                document.getElementById('ovm-ref').textContent = o.order_reference;
                
                const sb = document.getElementById('ovm-status-badge');
                sb.textContent = o.order_status;
                const sc = statusStyleMap[o.order_status] || { bg: '#F1F5F9', text: '#475569', border: '#CBD5E1' };
                sb.style.backgroundColor = sc.bg;
                sb.style.color = sc.text;
                sb.style.border = '1px solid ' + sc.border;

                document.getElementById('ovm-school-meta').textContent = (o.school_name || 'Partner School') + (o.school_city ? ' (' + o.school_city + ')' : '');
                document.getElementById('ovm-student-name').textContent = o.student_name || 'N/A';
                document.getElementById('ovm-grade').textContent = o.grade_class || 'N/A';
                document.getElementById('ovm-date').textContent = data.date_formatted;
                document.getElementById('ovm-parent-name').textContent = o.parent_name || 'N/A';
                document.getElementById('ovm-phone').textContent = o.mobile || 'N/A';
                document.getElementById('ovm-address').textContent = (o.delivery_address || 'Not specified') + (o.pincode ? ' - ' + o.pincode : '');
                let rawMethod = (o.payment_method || 'UPI').replace('Transaction Mode: ', '');
                if (rawMethod.toLowerCase().includes('online / qr') || rawMethod.toLowerCase().includes('edviron')) rawMethod = 'UPI';
                document.getElementById('ovm-payment-method').textContent = rawMethod;
                document.getElementById('ovm-payment-status').innerHTML = o.payment_status === 'Paid' 
                    ? '<span style="color:#15803d; font-weight:800; background:#dcfce7; padding:2px 8px; border-radius:9999px;">✓ PAID</span>' 
                    : '<span style="color:#b45309; font-weight:800; background:#fef3c7; padding:2px 8px; border-radius:9999px;">Pending</span>';
                document.getElementById('ovm-txn-id').textContent = o.gateway_transaction_id || o.effective_txn_id || o.transaction_ref || '—';
                document.getElementById('ovm-total-amount').textContent = data.total_formatted;

                const tbody = document.getElementById('ovm-items-tbody');
                tbody.innerHTML = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach(it => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #E2E8F0';
                        const isAcc = /\b(belt|socks?|cap|tie|bag|badge|bottle|mask)\b/i.test(it.product_name || '');
                        const hasCustomSize = !isAcc && it.size && !['free', 'standard', 'n/a', 'none', '-'].includes(String(it.size).toLowerCase().trim());
                        const sizeDisplay = hasCustomSize 
                            ? `<span style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-weight:700; font-size:0.75rem;">Size ${it.size}</span>`
                            : `<span style="color:#94A3B8; font-weight:700;">—</span>`;
                        tr.innerHTML = `
                            <td style="padding:0.65rem 0.85rem; font-weight:700; color:#0F172A;">
                                ${it.product_name}
                            </td>
                            <td style="padding:0.65rem 0.85rem; color:#475569;">
                                ${sizeDisplay}
                            </td>
                            <td style="padding:0.65rem 0.85rem; text-align:center; font-weight:800; color:#0F172A;">
                                ${it.quantity}
                            </td>
                            <td style="padding:0.65rem 0.85rem; text-align:right; color:#475569;">
                                ₹${parseFloat(it.unit_price).toFixed(2)}
                            </td>
                            <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:800; color:#0F172A;">
                                ₹${parseFloat(it.total_price).toFixed(2)}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:1.25rem; color:#64748B;">No item breakdown available.</td></tr>';
                }
            } else {
                alert('Could not load order details: ' + (data.message || 'Unknown error'));
                closeOrderViewModal();
            }
        })
        .catch(err => {
            alert('Failed to fetch order details.');
            closeOrderViewModal();
        });
}

function closeOrderViewModal() {
    document.getElementById('order-view-modal')?.classList.remove('active');
}

// ==================== DELETE ORDER CONFIRMATION MODAL ====================
function openDeleteOrderModal(orderId, ref) {
    document.getElementById('delete-modal-order-id').value = orderId;
    document.getElementById('delete-modal-ref').textContent = '#' + ref;
    document.getElementById('delete-order-modal')?.classList.add('active');
}

function closeDeleteOrderModal() {
    document.getElementById('delete-order-modal')?.classList.remove('active');
}

function applySelectColors(selectEl, status) {
    const s = statusStyleMap[status] || { bg: '#F1F5F9', text: '#475569', border: '#CBD5E1' };
    selectEl.style.backgroundColor = s.bg;
    selectEl.style.color = s.text;
    selectEl.style.borderColor = s.border;
}

// Single Order Quick Status Update (Row-by-Row)
function changeSingleOrderStatus(orderId, newStatus, selectEl) {
    const prevStatus = selectEl.getAttribute('data-current');
    selectEl.style.opacity = '0.5';
    selectEl.disabled = true;

    const formData = new FormData();
    formData.append('action', 'quick_update_status');
    formData.append('order_id', orderId);
    formData.append('order_status', newStatus);
    formData.append('is_ajax', '1');

    fetch('<?= BASE_URL ?>/admin/orders.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        selectEl.style.opacity = '1';
        selectEl.disabled = false;
        if (data.success) {
            selectEl.setAttribute('data-current', newStatus);
            applySelectColors(selectEl, newStatus);
            showAdminToast(data.message || `Order status updated to ${newStatus}!`, 'success');
        } else {
            selectEl.value = prevStatus;
            applySelectColors(selectEl, prevStatus);
            showAdminToast(data.message || 'Failed to update order status', 'error');
        }
    })
    .catch(err => {
        selectEl.style.opacity = '1';
        selectEl.disabled = false;
        selectEl.value = prevStatus;
        applySelectColors(selectEl, prevStatus);
        showAdminToast('Network error while updating status', 'error');
    });
}

// Bulk Order Selection and Update (All at a time)
function toggleMasterOrders(checked) {
    document.querySelectorAll('.order-row-checkbox').forEach(cb => {
        cb.checked = checked;
    });
    syncOrderCheckboxes();
}

function selectAllVisibleOrders(select) {
    const master = document.getElementById('master-orders-checkbox');
    if (master) master.checked = select;
    document.querySelectorAll('.order-row-checkbox').forEach(cb => {
        cb.checked = select;
    });
    syncOrderCheckboxes();
}

function syncOrderCheckboxes() {
    const all = document.querySelectorAll('.order-row-checkbox');
    const checked = document.querySelectorAll('.order-row-checkbox:checked');
    const count = checked.length;
    
    const countBadge = document.getElementById('bulk-selected-count');
    if (countBadge) countBadge.textContent = count;

    const clearBtn = document.getElementById('btn-deselect-all');
    if (clearBtn) clearBtn.style.display = count > 0 ? 'inline-block' : 'none';

    const master = document.getElementById('master-orders-checkbox');
    if (master) master.checked = (all.length > 0 && count === all.length);

    checkBulkApplyButtonState();
}

function onBulkStatusSelectChange() {
    checkBulkApplyButtonState();
}

function checkBulkApplyButtonState() {
    const checked = document.querySelectorAll('.order-row-checkbox:checked');
    const statusSelect = document.getElementById('bulk-new-status');
    const applyBtn = document.getElementById('btn-apply-bulk-status');

    if (checked.length > 0 && statusSelect.value) {
        applyBtn.disabled = false;
        applyBtn.style.opacity = '1';
        applyBtn.style.cursor = 'pointer';
    } else {
        applyBtn.disabled = true;
        applyBtn.style.opacity = '0.5';
        applyBtn.style.cursor = 'not-allowed';
    }
}

let pendingBulkData = null;

function applyBulkOrderStatus() {
    const checkedBoxes = Array.from(document.querySelectorAll('.order-row-checkbox:checked'));
    const orderIds = checkedBoxes.map(cb => cb.value);
    const newStatus = document.getElementById('bulk-new-status').value;

    if (orderIds.length === 0 || !newStatus) return;

    pendingBulkData = { orderIds, newStatus };

    // Fill popup content
    document.getElementById('bulk-popup-count').textContent = orderIds.length;
    const statusPill = document.getElementById('bulk-popup-status');
    const displayStatusName = (newStatus === 'Production') ? 'In Production' : newStatus;
    statusPill.textContent = `"${displayStatusName.toUpperCase()}"`;
    
    // Apply status styling on popup pill
    const sStyle = statusStyleMap[newStatus] || { bg: '#DBEAFE', text: '#1D4ED8', border: '#BFDBFE' };
    statusPill.style.backgroundColor = sStyle.bg;
    statusPill.style.color = sStyle.text;
    statusPill.style.borderColor = sStyle.border;

    // Open sleek OnBolt popup modal
    document.getElementById('bulk-confirm-modal').classList.add('active');
}

function closeBulkConfirmModal() {
    const modal = document.getElementById('bulk-confirm-modal');
    if (modal) modal.classList.remove('active');
    pendingBulkData = null;
}

function confirmAndExecuteBulkStatusUpdate() {
    if (!pendingBulkData) return;

    const { orderIds, newStatus } = pendingBulkData;
    const confirmBtn = document.getElementById('btn-popup-confirm-bulk');
    const applyBtn = document.getElementById('btn-apply-bulk-status');

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Updating...';
    if (applyBtn) {
        applyBtn.disabled = true;
        applyBtn.textContent = 'Updating...';
    }

    const formData = new FormData();
    formData.append('action', 'bulk_update_status');
    orderIds.forEach(id => formData.append('order_ids[]', id));
    formData.append('order_status', newStatus);
    formData.append('is_ajax', '1');

    fetch('<?= BASE_URL ?>/admin/orders.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Yes, Update Status';
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        }
        closeBulkConfirmModal();

        if (data.success) {
            orderIds.forEach(id => {
                const sel = document.getElementById('order-status-select-' + id);
                if (sel) {
                    sel.value = newStatus;
                    sel.setAttribute('data-current', newStatus);
                    applySelectColors(sel, newStatus);
                }
            });

            selectAllVisibleOrders(false);
            showAdminToast(data.message, 'success');
        } else {
            showAdminToast(data.message || 'Bulk update failed', 'error');
        }
    })
    .catch(err => {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Yes, Update Status';
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        }
        closeBulkConfirmModal();
        showAdminToast('Network error during bulk status update', 'error');
    });
}

// Toast notification helper
function showAdminToast(msg, type = 'success') {
    let toast = document.getElementById('admin-quick-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'admin-quick-toast';
        toast.style.cssText = 'position:fixed; top:24px; right:24px; z-index:999999; padding:12px 20px; border-radius:12px; font-weight:800; font-size:14px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.2); display:flex; align-items:center; gap:8px; transition:all 0.3s ease; opacity:0; transform:translateY(-10px); pointer-events:none;';
        document.body.appendChild(toast);
    }

    if (type === 'success') {
        toast.style.background = '#0F172A';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #334155';
        toast.innerHTML = `<span style="color:#22C55E; font-size:16px;">✓</span> <span>${msg}</span>`;
    } else {
        toast.style.background = '#FEF2F2';
        toast.style.color = '#991B1B';
        toast.style.border = '1px solid #F87171';
        toast.innerHTML = `<span style="color:#EF4444; font-size:16px;">⚠️</span> <span>${msg}</span>`;
    }

    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';

    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
    }, 3500);
}

function openMessageModal(data) {
    currentOrderData = data;
    document.getElementById('modal-order-id').value = data.order_id;
    document.getElementById('modal-parent-name').value = data.parent_name;
    document.getElementById('modal-parent-mobile').value = data.mobile;

    document.getElementById('disp-parent').textContent = data.parent_name;
    document.getElementById('disp-student').textContent = data.student_name;
    document.getElementById('disp-ref').textContent = data.ref;
    document.getElementById('disp-phone').textContent = data.mobile;

    // Default template: Order Confirmed
    applyTemplate('confirmed');

    document.getElementById('customer-message-modal').classList.add('active');
}

function closeMessageModal() {
    document.getElementById('customer-message-modal').classList.remove('active');
}

function applyTemplate(type) {
    const d = currentOrderData;
    let txt = '';

    if (type === 'confirmed') {
        txt = `Hello ${d.parent_name}, your uniform order ${d.ref} for ${d.student_name} (${d.school}) has been confirmed by OnBolt. Total: ${d.total}. Thank you for ordering!`;
    } else if (type === 'production') {
        txt = `Hello ${d.parent_name}, the uniforms for ${d.student_name} (Order ${d.ref}) are now in production at our Tirupur textile facility. High quality combed cotton fabrics are being prepared.`;
    } else if (type === 'pickup') {
        txt = `Hello ${d.parent_name}, good news! Your uniform order ${d.ref} for ${d.student_name} is packed and ready for collection at the ${d.school} uniform distribution counter.`;
    } else if (type === 'dispatched') {
        txt = `Hello ${d.parent_name}, your uniform order ${d.ref} for ${d.student_name} has been dispatched for home delivery. We will keep you updated. Thank you!`;
    }

    document.getElementById('modal-message-text').value = txt;
}

function sendViaWhatsApp() {
    const rawPhone = currentOrderData.mobile || '';
    let clean = rawPhone.replace(/[^0-9]/g, '');
    if (clean.length === 10) clean = '91' + clean;
    const msg = document.getElementById('modal-message-text').value;

    if (!clean) {
        alert('Invalid or missing mobile number.');
        return;
    }

    const waUrl = `https://wa.me/${clean}?text=${encodeURIComponent(msg)}`;
    window.open(waUrl, '_blank');

    document.getElementById('msg-form').submit();
}

// Close on backdrop click
document.getElementById('customer-message-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'customer-message-modal') {
        closeMessageModal();
    }
});
document.getElementById('order-view-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'order-view-modal') {
        closeOrderViewModal();
    }
});
document.getElementById('delete-order-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'delete-order-modal') {
        closeDeleteOrderModal();
    }
});

const bulkModal = document.getElementById('bulk-confirm-modal');
if (bulkModal) {
    bulkModal.addEventListener('click', (e) => {
        if (e.target.id === 'bulk-confirm-modal') {
            closeBulkConfirmModal();
        }
    });
}

function handleMainSchoolFilterChange(mainSelect) {
    const subSelect = document.getElementById('sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}

// ==================== EXPORT REPORTS JAVASCRIPT ====================
function exportOrders(type) {
    const statusVal = document.querySelector('select[name="status"]')?.value || '';
    const schoolVal = document.querySelector('select[name="school_id"]')?.value || '';
    const subSchoolVal = document.querySelector('select[name="sub_school_id"]')?.value || '';
    const searchVal = document.querySelector('input[name="search"]')?.value || '';

    const params = new URLSearchParams();
    if (statusVal) params.set('status', statusVal);
    if (schoolVal) params.set('school_id', schoolVal);
    if (subSchoolVal) params.set('sub_school_id', subSchoolVal);
    if (searchVal) params.set('search', searchVal);

    if (type === 'excel') {
        params.set('export', 'excel');
        window.location.href = '<?= BASE_URL ?>/admin/orders.php?' + params.toString();
        return;
    }

    if (type === 'pdf') {
        if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined') {
            try {
                generateOrdersPdf();
                return;
            } catch (err) {
                console.warn('Direct jsPDF export error, falling back to print-to-PDF view:', err);
            }
        }
        params.set('export', 'pdf_print');
        window.open('<?= BASE_URL ?>/admin/orders.php?' + params.toString(), '_blank');
    }
}

function generateOrdersPdf() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4'
    });

    // Top Brand Orange Bar
    doc.setFillColor(255, 103, 2);
    doc.rect(0, 0, 297, 18, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.text('ONBOLT COMPLETE BRANDING SOLUTIONS', 14, 11);

    doc.setFontSize(8.5);
    doc.setFont('helvetica', 'normal');
    doc.text('Official Parent Uniform Purchasing Requisitions Report', 14, 15.5);

    // School Name & Order Status Header (Only selected School / Sub-School and Status)
    let headerText = '';
    if (window.REPORT_META.subSchool) {
        headerText = `School: ${window.REPORT_META.mainSchool ? window.REPORT_META.mainSchool + ' - ' : ''}${window.REPORT_META.subSchool}   |   Status: ${window.REPORT_META.status}`;
    } else {
        headerText = `School: ${window.REPORT_META.school}   |   Status: ${window.REPORT_META.status}`;
    }
    if (window.REPORT_META.search) {
        headerText += `   |   Search: "${window.REPORT_META.search}"`;
    }

    doc.setTextColor(15, 23, 42);
    doc.setFontSize(9.5);
    doc.setFont('helvetica', 'bold');
    doc.text(headerText, 14, 25);

    const summaryText = `Total Orders: ${window.REPORT_META.totalCount}  |  Total Items: ${window.REPORT_META.totalItems} pcs  |  Grand Total: ${window.REPORT_META.grandTotal}  |  Generated: ${window.REPORT_META.date}`;
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(100, 116, 139);
    doc.text(summaryText, 14, 30);

    const head = [['#', 'Order Ref', 'School Name', 'Student & Grade', 'Parent & Phone', 'Items', 'Payment', 'Total Value', 'Status', 'Date']];
    const body = window.REPORT_ORDERS_DATA || [];
    const foot = [['', '', '', '', 'GRAND TOTAL:', `${window.REPORT_META.totalItems} pcs`, '', window.REPORT_META.grandTotal, '', '']];

    doc.autoTable({
        head: head,
        body: body,
        foot: foot,
        showFoot: 'lastPage',
        startY: 33,
        theme: 'grid',
        headStyles: {
            fillColor: [255, 103, 2],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
            fontSize: 8,
            cellPadding: 2
        },
        bodyStyles: {
            fontSize: 7.5,
            textColor: [15, 23, 42],
            cellPadding: 1.8
        },
        alternateRowStyles: {
            fillColor: [248, 250, 252]
        },
        footStyles: {
            fillColor: [255, 247, 237],
            textColor: [194, 65, 12],
            fontStyle: 'bold',
            fontSize: 8,
            cellPadding: 2
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 30, fontStyle: 'bold', textColor: [234, 88, 12] },
            2: { cellWidth: 42 },
            3: { cellWidth: 40 },
            4: { cellWidth: 38 },
            5: { cellWidth: 16, halign: 'center', fontStyle: 'bold' },
            6: { cellWidth: 18, halign: 'center' },
            7: { cellWidth: 26, halign: 'right', fontStyle: 'bold' },
            8: { cellWidth: 25, halign: 'center', fontStyle: 'bold' },
            9: { cellWidth: 24, halign: 'center' }
        },
        margin: { left: 14, right: 14, top: 33, bottom: 12 },
        didDrawPage: function(data) {
            const str = 'Page ' + doc.internal.getNumberOfPages();
            doc.setFontSize(7.5);
            doc.setTextColor(148, 163, 184);
            doc.text(str, data.settings.margin.left, 203);
            doc.text('OnBolt Digital Platform - Official Institutional Records', 195, 203);
        }
    });

    const safeSchool = (window.REPORT_META.school || 'All').replace(/[^a-zA-Z0-9]/g, '_');
    doc.save(`OnBolt_Orders_Report_${safeSchool}_${new Date().toISOString().slice(0, 10)}.pdf`);
}
</script>

<?php
$exportJsRows = [];
foreach ($ordersList as $idx => $o) {
    $exportJsRows[] = [
        (string)($idx + 1),
        (string)$o['order_reference'],
        (string)($o['school_name'] ?: 'School'),
        $o['student_name'] . ($o['grade_class'] ? "\n(" . $o['grade_class'] . ")" : ''),
        $o['parent_name'] . ($o['mobile'] ? "\n" . $o['mobile'] : ''),
        $o['total_items'] . ' pcs',
        (string)$o['payment_status'],
        'Rs. ' . number_format((float)$o['total_amount'], 2),
        (string)$o['order_status'],
        date('d M Y', strtotime($o['created_at']))
    ];
}
?>
<!-- jsPDF & AutoTable for client-side vector PDF export (local bundled scripts) -->
<script src="<?= BASE_URL ?>/assets/js/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/jspdf.plugin.autotable.min.js"></script>
<script>
window.REPORT_ORDERS_DATA = <?= json_encode($exportJsRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.REPORT_META = {
    status: <?= json_encode($activeStatusLabel) ?>,
    school: <?= json_encode($activeSchoolDisplay) ?>,
    mainSchool: <?= json_encode($activeMainSchoolLabel) ?>,
    subSchool: <?= json_encode($activeSubSchoolLabel) ?>,
    search: <?= json_encode($activeSearchLabel) ?>,
    totalCount: <?= $totalOrdersCount ?>,
    totalItems: <?= $totalItemsCount ?>,
    grandTotal: <?= json_encode('Rs. ' . number_format($totalAmountSum, 2)) ?>,
    date: <?= json_encode(date('d M Y, h:i A')) ?>
};
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
