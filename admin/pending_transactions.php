<?php
/**
 * OnBolt Digital Experience Platform - Pending & Failed Transactions Management
 * Allows Staff, Admin, and Super Admin to search, monitor, and manually verify incomplete checkout attempts.
 * File: /admin/pending_transactions.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (!adminCan('pending_transactions_manage')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access Pending & Failed Transactions.');
}
$pdo = getDbConnection();

$adminActive = 'pending_transactions';
$pageTitle = 'Pending & Failed Attempts | OnBolt Admin';

$successMsg = '';
$errorMsg = '';

// Handle Mark as Paid POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_as_paid') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $errorMsg = 'Security validation token expired. Please reload and try again.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $payMethod = trim($_POST['payment_method'] ?? 'Cash (Counter / School Desk)');
        $payRef = trim($_POST['payment_reference'] ?? '');
        $adminNoteInput = trim($_POST['admin_notes'] ?? '');

        // Fetch the pending order
        $chkStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND (order_reference IS NULL OR order_reference = '') LIMIT 1");
        $chkStmt->execute([$orderId]);
        $pendingOrder = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendingOrder) {
            $errorMsg = 'Order not found or an official Order ID has already been assigned to this transaction.';
        } else {
            try {
                $pdo->beginTransaction();

                $schoolId = (int)$pendingOrder['school_id'];
                $reqId = $pendingOrder['payment_request_id'] ?? ('REQ-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT));

                // Generate Official Order ID (e.g. GIB822754, TIS140127)
                $newOrderRef = generateOfficialOrderReference($schoolId);

                // Admin Note update
                $adminName = $admin['name'] ?? ($admin['email'] ?? 'Admin Staff');
                $noteAppend = "\n[" . date('d M Y, h:i A') . "] Manually marked as PAID by {$adminName} via {$payMethod}." . (!empty($payRef) ? " Ref: {$payRef}." : "") . (!empty($adminNoteInput) ? " Notes: {$adminNoteInput}" : "");
                $combinedNotes = trim(($pendingOrder['admin_notes'] ?? '') . $noteAppend);

                // Update orders row to Paid & Confirmed with Official Order Reference
                $upStmt = $pdo->prepare("UPDATE orders SET 
                    order_reference = ?,
                    payment_status = 'Paid',
                    order_status = 'Confirmed',
                    gateway_transaction_id = ?,
                    payment_method = ?,
                    admin_notes = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                $upStmt->execute([
                    $newOrderRef,
                    $finalPayRef,
                    $payMethod,
                    $combinedNotes,
                    $orderId
                ]);

                // Update or Insert payment record
                $chkPay = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
                $chkPay->execute([$orderId]);
                $payId = $chkPay->fetchColumn();

                $finalPayRef = !empty($payRef) ? $payRef : ('MANUAL-' . strtoupper(bin2hex(random_bytes(4))));

                if ($payId) {
                    $upPay = $pdo->prepare("UPDATE payments SET 
                        status = 'Completed',
                        payment_method = ?,
                        transaction_ref = ?
                        WHERE id = ?");
                    $upPay->execute([$payMethod, $finalPayRef, $payId]);
                } else {
                    $insPay = $pdo->prepare("INSERT INTO payments (
                        order_id, transaction_ref, payment_method, amount, currency, status, created_at
                    ) VALUES (?, ?, ?, ?, 'INR', 'Completed', NOW())");
                    $insPay->execute([
                        $orderId,
                        $finalPayRef,
                        $payMethod,
                        $pendingOrder['total_amount']
                    ]);
                }

                // Add to status history
                $histStmt = $pdo->prepare("INSERT INTO order_status_history (
                    order_id, previous_status, new_status, comment, changed_by, created_at
                ) VALUES (?, 'Pending', 'Confirmed', ?, ?, NOW())");
                $histStmt->execute([
                    $orderId,
                    "Payment manually verified via {$payMethod} (Ref: {$finalPayRef}). Official Order Reference {$newOrderRef} generated.",
                    $adminName
                ]);

                // Log Activity
                logActivity('manual_payment_confirm', 'orders', $orderId, 'admin', "Order {$newOrderRef} confirmed manually via {$payMethod} by {$adminName}");

                $pdo->commit();
                clearFastCache();

                $successMsg = "Payment successfully confirmed! Official Order ID <strong>{$newOrderRef}</strong> generated. <a href='orders.php?search=" . urlencode($newOrderRef) . "' style='text-decoration:underline; font-weight:700; color:#065f46;'>View in Active Orders List →</a>";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMsg = 'Failed to confirm transaction: ' . $e->getMessage();
            }
        }
    }
}

// Search and Filter parameters
$searchMobile = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);
$sortDate = strtolower(trim($_GET['sort_date'] ?? 'desc'));
if (!in_array($sortDate, ['asc', 'desc'], true)) {
    $sortDate = 'desc';
}
$dateFilter = trim($_GET['date_filter'] ?? '');

// Base condition: Strictly records where official order_reference has NOT been generated yet
$whereSql = "WHERE (o.order_reference IS NULL OR o.order_reference = '')";

if (!empty($dateFilter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $quotedDate = $pdo->quote($dateFilter);
    $whereSql .= " AND DATE(o.created_at) = {$quotedDate}";
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'Failed') {
        $whereSql .= " AND o.payment_status = 'Failed'";
    } elseif ($statusFilter === 'Pending') {
        $whereSql .= " AND o.payment_status = 'Pending'";
    }
}

if ($subSchoolFilter > 0) {
    $whereSql .= " AND o.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $whereSql .= " AND (o.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}

if (!empty($searchMobile)) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $searchMobile);
    $quotedSearch = $pdo->quote("%{$searchMobile}%");
    if (!empty($cleanPhone) && strlen($cleanPhone) >= 4) {
        $quotedPhone = $pdo->quote("%{$cleanPhone}%");
        $whereSql .= " AND (o.mobile LIKE {$quotedPhone} OR o.alt_mobile LIKE {$quotedPhone} OR o.payment_request_id LIKE {$quotedSearch} OR o.gateway_transaction_id LIKE {$quotedSearch} OR o.student_name LIKE {$quotedSearch} OR o.parent_name LIKE {$quotedSearch})";
    } else {
        $whereSql .= " AND (o.payment_request_id LIKE {$quotedSearch} OR o.gateway_transaction_id LIKE {$quotedSearch} OR o.mobile LIKE {$quotedSearch} OR o.student_name LIKE {$quotedSearch} OR o.parent_name LIKE {$quotedSearch})";
    }
}

// KPI Metrics for Incomplete Attempts
$kpiScope = "";
if (!empty($dateFilter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $quotedDate = $pdo->quote($dateFilter);
    $kpiScope .= " AND DATE(o.created_at) = {$quotedDate}";
}
if ($subSchoolFilter > 0) {
    $kpiScope .= " AND o.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $kpiScope .= " AND (o.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}
if (!empty($searchMobile)) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $searchMobile);
    $quotedSearch = $pdo->quote("%{$searchMobile}%");
    if (!empty($cleanPhone) && strlen($cleanPhone) >= 4) {
        $quotedPhone = $pdo->quote("%{$cleanPhone}%");
        $kpiScope .= " AND (o.mobile LIKE {$quotedPhone} OR o.alt_mobile LIKE {$quotedPhone} OR o.payment_request_id LIKE {$quotedSearch} OR o.gateway_transaction_id LIKE {$quotedSearch} OR o.student_name LIKE {$quotedSearch} OR o.parent_name LIKE {$quotedSearch})";
    } else {
        $kpiScope .= " AND (o.payment_request_id LIKE {$quotedSearch} OR o.gateway_transaction_id LIKE {$quotedSearch} OR o.mobile LIKE {$quotedSearch} OR o.student_name LIKE {$quotedSearch} OR o.parent_name LIKE {$quotedSearch})";
    }
}

$totalIncompleteCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_reference IS NULL OR o.order_reference = '') {$kpiScope}")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_reference IS NULL OR o.order_reference = '') AND o.payment_status = 'Pending' {$kpiScope}")->fetchColumn();
$failedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_reference IS NULL OR o.order_reference = '') AND o.payment_status = 'Failed' {$kpiScope}")->fetchColumn();
$potentialValue = (float)$pdo->query("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o LEFT JOIN schools s ON o.school_id = s.id WHERE (o.order_reference IS NULL OR o.order_reference = '') {$kpiScope}")->fetchColumn();

// Fetch Pending/Failed records
$sortSql = ($sortDate === 'asc') ? 'ASC' : 'DESC';
$query = "SELECT o.*, s.name as school_name, s.code as school_code,
                 p.transaction_ref as gateway_txn_ref, p.payment_method as gateway_mode, p.gateway_response
          FROM orders o
          LEFT JOIN schools s ON o.school_id = s.id
          LEFT JOIN payments p ON p.order_id = o.id
          {$whereSql}
          ORDER BY o.created_at {$sortSql}, o.id {$sortSql}";

$pendingList = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for each pending order
$orderItemsMap = [];
$pendingDataMap = [];
if (!empty($pendingList)) {
    $orderIds = array_column($pendingList, 'id');
    $inClause = implode(',', array_map('intval', $orderIds));
    $itemsStmt = $pdo->query("SELECT order_id, product_name, size, quantity, unit_price, total_price FROM order_items WHERE order_id IN ({$inClause})");
    while ($it = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $orderItemsMap[$it['order_id']][] = $it;
    }

    foreach ($pendingList as $pRow) {
        $pId = (int)$pRow['id'];
        $dispReqId = !empty($pRow['payment_request_id']) ? $pRow['payment_request_id'] : ('REQ-' . str_pad((string)$pId, 6, '0', STR_PAD_LEFT));
        $pItems = $orderItemsMap[$pId] ?? [];
        $formattedItems = [];
        foreach ($pItems as $it) {
            $formattedItems[] = [
                'name' => $it['product_name'],
                'size' => $it['size'],
                'quantity' => (int)$it['quantity'],
                'unit_price' => (float)$it['unit_price'],
                'total_price' => (float)$it['total_price']
            ];
        }
        $cleanPhone = preg_replace('/[^0-9]/', '', $pRow['mobile'] ?? '');
        if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
        $waText = "Hello " . ($pRow['parent_name'] ?? 'Parent') . ", this is OnBolt Uniforms regarding your checkout attempt (" . $dispReqId . ") for " . ($pRow['student_name'] ?? 'your ward') . " at " . ($pRow['school_name'] ?? 'School') . ". Need assistance completing your order?";

        $pendingDataMap[$pId] = [
            'id' => $pId,
            'req_id' => $dispReqId,
            'created_at' => !empty($pRow['created_at']) ? date('d M Y, h:i A', strtotime($pRow['created_at'])) : '—',
            'status' => $pRow['payment_status'] ?? 'Pending',
            'school_name' => $pRow['school_name'] ?? '—',
            'school_code' => $pRow['school_code'] ?? '',
            'student_name' => $pRow['student_name'] ?? '—',
            'grade_class' => $pRow['grade_class'] ?? '—',
            'gender' => $pRow['gender'] ?? '',
            'parent_name' => $pRow['parent_name'] ?? 'Not Specified',
            'mobile' => $pRow['mobile'] ?? '',
            'alt_mobile' => $pRow['alt_mobile'] ?? '',
            'email' => $pRow['email'] ?? '',
            'address' => $pRow['delivery_address'] ?? '',
            'pincode' => $pRow['pincode'] ?? '',
            'total_amount' => (float)$pRow['total_amount'],
            'total_amount_formatted' => formatCurrency((float)$pRow['total_amount']),
            'payment_method' => formatDisplayPaymentMethod($pRow['payment_method'], $pRow['gateway_response'] ?? null),
            'txn_id' => !empty($pRow['gateway_transaction_id']) ? $pRow['gateway_transaction_id'] : (!empty($pRow['gateway_txn_ref']) ? $pRow['gateway_txn_ref'] : '—'),
            'items' => $formattedItems,
            'wa_url' => "https://wa.me/{$cleanPhone}?text=" . urlencode($waText)
        ];
    }
}

// Schools list for dropdown
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC")->fetchAll();
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.25rem;">
                <span class="badge" style="background: #FEF3C7; color: #D97706; font-weight: 800; font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                    ⚡ RECOVERY &amp; RECONCILIATION
                </span>
                <span style="color: #94A3B8; font-size: 0.82rem;">•</span>
                <span style="color: #64748B; font-size: 0.82rem;">Incomplete Gateway Sessions</span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #1E2530; margin: 0;">
                Pending &amp; Failed Transactions
            </h1>
            <p style="color: #64748B; font-size: 0.88rem; margin: 0.25rem 0 0;">
                Search parent checkout attempts by registered mobile number, track dropped gateway sessions, and manually confirm payments to generate official Order IDs.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn" style="background: #ffffff; color: #1E2530; border: 1px solid #E2E8F0; font-weight: 700; padding: 0.6rem 1.25rem; border-radius: 9999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>📦</span> Active Confirmed Orders
            </a>
        </div>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 1rem 1.25rem; border-radius: 12px; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1.3rem;">✓</span>
        <div><?= $successMsg ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 1rem 1.25rem; border-radius: 12px; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1.3rem;">⚠️</span>
        <div><?= htmlspecialchars($errorMsg) ?></div>
    </div>
<?php endif; ?>

<!-- KPI Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
    <div class="stat-card" style="border-left: 4px solid #FF6702;">
        <span class="stat-label">Total Incomplete Attempts</span>
        <span class="stat-num" style="color: #FF6702;"><?= number_format($totalIncompleteCount) ?></span>
        <span style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">Awaiting verification / dropped</span>
    </div>

    <div class="stat-card" style="border-left: 4px solid #F59E0B;">
        <span class="stat-label">Pending Verification</span>
        <span class="stat-num" style="color: #F59E0B;"><?= number_format($pendingCount) ?></span>
        <span style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">Gateway session initiated</span>
    </div>

    <div class="stat-card" style="border-left: 4px solid #EF4444;">
        <span class="stat-label">Failed / Dropped</span>
        <span class="stat-num" style="color: #EF4444;"><?= number_format($failedCount) ?></span>
        <span style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">Payment unsuccessful or cancelled</span>
    </div>

    <div class="stat-card" style="border-left: 4px solid #10B981;">
        <span class="stat-label">Potential Value</span>
        <span class="stat-num" style="color: #10B981;"><?= formatCurrency($potentialValue) ?></span>
        <span style="font-size: 0.75rem; color: #64748B; margin-top: 0.35rem;">Recoverable cart amounts</span>
    </div>
</div>

<!-- Search & Filter Card -->
<div style="background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);">
    <form method="GET" action="pending_transactions.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        
        <!-- Mobile / Search Input -->
        <div style="flex: 1 1 220px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Parent Registered Mobile / Request ID
            </label>
            <input type="text" name="search" value="<?= htmlspecialchars($searchMobile) ?>" 
                   placeholder="Enter your Mobile Number or Request ID" 
                   style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
        </div>

        <!-- Gateway Status Filter -->
        <div style="flex: 0 1 190px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Gateway Status
            </label>
            <select name="status" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="">Select your Gateway Status</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Failed" <?= $statusFilter === 'Failed' ? 'selected' : '' ?>>Failed / Dropped</option>
            </select>
        </div>

        <!-- School Filter -->
        <div style="flex: 1 1 180px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                School Filter
            </label>
            <select name="school_id" id="schoolSelect" onchange="updateSubSchools(this.value)" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="0">Select your School</option>
                <?php foreach ($mainSchools as $ms): ?>
                    <option value="<?= $ms['id'] ?>" <?= $schoolFilter === (int)$ms['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ms['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sub-School / Campus Filter -->
        <div style="flex: 1 1 180px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Sub-School / Campus
            </label>
            <select name="sub_school_id" id="subSchoolSelect" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="0">Select your Sub School</option>
                <?php foreach ($underSchools as $sub): 
                    $matchParent = ($schoolFilter === 0 || (int)$sub['parent_id'] === $schoolFilter);
                ?>
                    <option value="<?= $sub['id'] ?>" 
                            data-parent="<?= $sub['parent_id'] ?>" 
                            <?= $subSchoolFilter === (int)$sub['id'] ? 'selected' : '' ?>
                            style="<?= $matchParent ? '' : 'display:none;' ?>">
                        <?= htmlspecialchars($sub['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date Filter -->
        <div style="flex: 0 1 150px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Date Filter
            </label>
            <input type="date" name="date_filter" value="<?= htmlspecialchars($dateFilter) ?>" 
                   style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.88rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
        </div>

        <!-- Filter Action Buttons -->
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.4rem; font-weight: 700; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>Filter</span>
            </button>
            <?php if (!empty($searchMobile) || !empty($statusFilter) || $schoolFilter > 0 || $subSchoolFilter > 0 || !empty($dateFilter)): ?>
                <a href="pending_transactions.php" class="btn btn-outline" style="padding: 0.65rem 1rem; font-weight: 600; border-radius: 10px; text-decoration: none;">
                    Reset
                </a>
            <?php endif; ?>
        </div>

    </form>
</div>

<!-- Transactions Table Frame -->
<div class="table-scroll-frame" style="max-height: 640px; border-radius: 16px; border: 1px solid #E2E8F0; background: #ffffff;">
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 130px;">Request ID</th>
                <th style="width: 140px;">Date</th>
                <th style="min-width: 160px;">Parent Name</th>
                <th style="min-width: 160px;">Student Name</th>
                <th style="width: 140px;">Phone Number</th>
                <th style="width: 130px; text-align: center;">Status</th>
                <th style="width: 80px; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pendingList)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 4rem 2rem; color: #64748B;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
                        <div style="font-size: 1.15rem; font-weight: 800; color: #1E2530;">No Incomplete Attempts Found</div>
                        <p style="font-size: 0.88rem; margin-top: 0.25rem;">
                            <?= !empty($searchMobile) ? "No pending or failed transactions match '<strong>" . htmlspecialchars($searchMobile) . "</strong>'." : "All initiated checkouts are either reconciled or no dropped attempts currently exist." ?>
                        </p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pendingList as $row): 
                    $orderId = (int)$row['id'];
                    $displayReqId = !empty($row['payment_request_id']) ? $row['payment_request_id'] : ('REQ-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT));
                    $pStatus = $row['payment_status'] ?? 'Pending';
                ?>
                <tr>
                    <!-- Request ID -->
                    <td>
                        <span style="font-family: monospace; font-size: 0.88rem; font-weight: 800; color: #475569; background: #F1F5F9; padding: 4px 8px; border-radius: 6px; display: inline-block; white-space: nowrap;">
                            <?= htmlspecialchars($displayReqId) ?>
                        </span>
                    </td>

                    <!-- Date -->
                    <td style="white-space: nowrap;">
                        <div style="font-weight: 700; color: #1E2530; font-size: 0.85rem; white-space: nowrap;">
                            <?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—' ?>
                        </div>
                        <div style="font-size: 0.72rem; color: #64748B; margin-top: 2px; white-space: nowrap;">
                            <?= !empty($row['created_at']) ? date('h:i A', strtotime($row['created_at'])) : '' ?>
                        </div>
                    </td>

                    <!-- Parent Name -->
                    <td>
                        <div style="font-weight: 800; color: #1E2530; font-size: 0.9rem;">
                            <?= htmlspecialchars($row['parent_name'] ?: 'Not Specified') ?>
                        </div>
                    </td>

                    <!-- Student Name -->
                    <td>
                        <div style="font-weight: 700; color: #1E2530; font-size: 0.9rem;">
                            <?= htmlspecialchars($row['student_name'] ?: '—') ?>
                        </div>
                    </td>

                    <!-- Phone Number -->
                    <td style="white-space: nowrap;">
                        <a href="tel:<?= htmlspecialchars($row['mobile']) ?>" style="font-family: monospace; font-weight: 700; color: #0284C7; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                            <?= htmlspecialchars($row['mobile'] ?: '—') ?>
                        </a>
                    </td>

                    <!-- Status -->
                    <td style="text-align: center; white-space: nowrap;">
                        <?php if ($pStatus === 'Failed'): ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 9999px; font-size: 0.75rem; font-weight: 800; white-space: nowrap;">
                                FAILED
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; border-radius: 9999px; font-size: 0.75rem; font-weight: 800; white-space: nowrap;">
                                PENDING
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Action: Eye Icon Only -->
                    <td style="text-align: center;">
                        <button type="button" 
                                class="btn btn-outline btn-sm" 
                                style="padding: 0.45rem 0.65rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center; color: #2563EB; border-color: #BFDBFE; background: #F8FAFC; border-radius: 8px; cursor: pointer; transition: all 0.2s;" 
                                title="View Details" 
                                onclick="openPendingDetailsModal(<?= $orderId ?>)"
                                onmouseover="this.style.background='#EFF6FF'; this.style.borderColor='#2563EB';"
                                onmouseout="this.style.background='#F8FAFC'; this.style.borderColor='#BFDBFE';">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== TRANSACTION DETAILS VIEW POPUP MODAL ==================== -->
<div id="pendingDetailsModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 680px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #E2E8F0; overflow: hidden; animation: popIn 0.2s ease-out;">
        
        <!-- Header -->
        <div style="padding: 1.25rem 1.5rem; background: #0F172A; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #38BDF8;">Requisition Details</span>
                    <span id="pdm-status-badge" style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 800;"></span>
                </div>
                <h3 style="margin: 0.25rem 0 0; font-size: 1.25rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 8px;">
                    Request <span id="pdm-req-id" style="font-family: monospace; color: #F59E0B;"></span>
                </h3>
                <div style="font-size: 0.78rem; color: #94A3B8; margin-top: 3px;">
                    Attempt Date: <span id="pdm-date" style="color: #E2E8F0; font-weight: 600;"></span>
                </div>
            </div>
            <button type="button" onclick="closePendingDetailsModal()" style="background: transparent; border: none; color: #94A3B8; font-size: 1.6rem; cursor: pointer; line-height: 1; padding: 4px; border-radius: 6px;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#94A3B8'">&times;</button>
        </div>

        <!-- Scrollable Body -->
        <div style="padding: 1.5rem; overflow-y: auto; flex: 1;">
            
            <!-- Cards Grid: School & Student + Parent & Delivery -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                
                <!-- School & Student Card -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #2563EB; margin-bottom: 0.6rem;">
                        🏫 School &amp; Student
                    </div>
                    <div style="margin-bottom: 0.4rem;">
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">School:</span>
                        <strong style="color: #1E2530; font-size: 0.88rem;" id="pdm-school-name">—</strong>
                        <span id="pdm-school-code" style="font-size: 0.7rem; font-weight: 800; color: #FF6702; background: #FFF7ED; padding: 2px 6px; border-radius: 4px; margin-left: 4px;"></span>
                    </div>
                    <div style="margin-bottom: 0.4rem;">
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Student Name:</span>
                        <strong style="color: #1E2530; font-size: 0.88rem;" id="pdm-student-name">—</strong>
                    </div>
                    <div>
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Class / Grade:</span>
                        <strong style="color: #1E2530; font-size: 0.88rem;" id="pdm-grade-class">—</strong>
                        <span id="pdm-gender" style="font-size: 0.72rem; background: #E2E8F0; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 4px;"></span>
                    </div>
                </div>

                <!-- Parent & Delivery Card -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #2563EB; margin-bottom: 0.6rem;">
                        👨‍👩‍👧 Parent &amp; Contact
                    </div>
                    <div style="margin-bottom: 0.4rem;">
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Parent Name:</span>
                        <strong style="color: #1E2530; font-size: 0.88rem;" id="pdm-parent-name">—</strong>
                    </div>
                    <div style="margin-bottom: 0.4rem;">
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Phone Number:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: #0284C7; font-size: 0.88rem; font-family: monospace;" id="pdm-mobile">—</strong>
                            <a id="pdm-wa-link" href="#" target="_blank" class="btn btn-outline btn-sm" style="padding: 2px 6px; font-size: 0.72rem; color: #16A34A; border-color: #BBF7D0; background: #F0FDF4; border-radius: 4px; text-decoration: none;">
                                WhatsApp
                            </a>
                        </div>
                    </div>
                    <div style="margin-bottom: 0.4rem;" id="pdm-email-box">
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Email:</span>
                        <span style="color: #475569; font-size: 0.82rem;" id="pdm-email">—</span>
                    </div>
                    <div>
                        <span style="color: #64748B; font-size: 0.78rem; display: block;">Delivery Address:</span>
                        <span style="color: #475569; font-size: 0.82rem;" id="pdm-address">—</span>
                    </div>
                </div>
            </div>

            <!-- Payment Summary Banner -->
            <div style="display: flex; justify-content: space-between; align-items: center; background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <div style="font-size: 0.82rem; color: #475569;">
                        <strong>Payment Method:</strong> <span id="pdm-payment-method">UPI</span>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748B; margin-top: 2px;">
                        Gateway Reference: <span id="pdm-txn-id" style="font-family: monospace; font-weight: 700; color: #0284C7;">—</span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.75rem; color: #9A3412; font-weight: 700; text-transform: uppercase; display: block;">Total Payable</span>
                    <strong style="font-size: 1.25rem; font-weight: 800; color: #EA580C;" id="pdm-total-amount">₹0.00</strong>
                </div>
            </div>

            <!-- Items Table -->
            <div>
                <div style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #334155; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Uniform Requisition Items</span>
                    <span id="pdm-items-count" style="font-size: 0.75rem; color: #64748B; text-transform: none; font-weight: 600;">0 items</span>
                </div>
                <div style="border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                        <thead>
                            <tr style="background: #F1F5F9; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em;">
                                <th style="padding: 0.6rem 0.8rem; text-align: left;">Item Description</th>
                                <th style="padding: 0.6rem 0.8rem; text-align: center;">Size</th>
                                <th style="padding: 0.6rem 0.8rem; text-align: center;">Qty</th>
                                <th style="padding: 0.6rem 0.8rem; text-align: right;">Unit Price</th>
                                <th style="padding: 0.6rem 0.8rem; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="pdm-items-tbody">
                            <!-- Items populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div style="padding: 1rem 1.5rem; background: #F8FAFC; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <button type="button" id="pdm-mark-paid-btn" class="btn btn-primary" style="background: #10B981; border-color: #10B981; font-weight: 800; padding: 0.65rem 1.25rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px;">
                <span>✓</span> Mark as Paid &amp; Generate Order ID
            </button>
            <button type="button" onclick="closePendingDetailsModal()" class="btn btn-outline" style="padding: 0.65rem 1.25rem; font-weight: 700; border-radius: 10px;">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Modal: Mark as Paid & Generate Order ID -->
<div id="markPaidModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 520px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #E2E8F0; overflow: hidden; animation: popIn 0.2s ease-out;">
        
        <!-- Modal Header -->
        <div style="padding: 1.25rem 1.5rem; background: #0F172A; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #10B981;">Manual Payment Confirmation</span>
                <h3 style="margin: 0.15rem 0 0; font-size: 1.2rem; font-weight: 800; color: #ffffff;">
                    Mark as Paid &amp; Generate Order ID
                </h3>
            </div>
            <button type="button" onclick="closeMarkPaidModal()" style="background: none; border: none; color: #94A3B8; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <form method="POST" action="pending_transactions.php" data-no-confirm="true" style="padding: 1.5rem;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_as_paid">
            <input type="hidden" name="order_id" id="modalOrderId" value="">

            <!-- Transaction Summary Box -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: #64748B;">Request ID:</span>
                    <strong style="font-family: monospace; color: #1E2530;" id="modalReqId">—</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: #64748B;">Student:</span>
                    <strong style="color: #1E2530;" id="modalStudent">—</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: #64748B;">Parent &amp; Mobile:</span>
                    <strong style="color: #0284C7;" id="modalParentPhone">—</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 0.4rem; border-top: 1px dashed #CBD5E1;">
                    <span style="color: #1E2530; font-weight: 700;">Total Payable:</span>
                    <strong style="color: #FF6702; font-size: 1.1rem; font-weight: 800;" id="modalAmount">—</strong>
                </div>
            </div>

            <!-- Payment Method Select -->
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.4rem;">
                    Payment Method Received <span style="color: #DC2626;">*</span>
                </label>
                <select name="payment_method" required style="width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #ffffff;">
                    <option value="" disabled>Select Payment Method</option>
                    <option value="Cash">💵 Cash</option>
                    <option value="UPI" selected>📱 UPI</option>
                    <option value="Card">💳 Card</option>
                    <option value="Net Banking">🏦 Net Banking</option>
                    <option value="Online Gateway">⚡ Online Gateway</option>
                </select>
            </div>

            <!-- Transaction Reference (Optional) -->
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.4rem;">
                    Transaction Reference / UTR / Receipt No.
                </label>
                <input type="text" name="payment_reference" placeholder="Enter your Payment Reference / Transaction ID" 
                       style="width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; color: #1E2530;">
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.4rem;">
                    Admin Verification Note
                </label>
                <input type="text" name="admin_notes" placeholder="Enter your Internal Notes" 
                       style="width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; color: #1E2530;">
            </div>

            <!-- Notice Box -->
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.8rem; color: #065F46; line-height: 1.4;">
                ℹ <strong>What happens next:</strong> Upon clicking Confirm, the system will generate the official sequential School Order ID (e.g. <code>GIB...</code>, <code>TIS...</code>), update status to <strong>Paid / Confirmed</strong>, and automatically move this record to the active <strong>Parent Purchases</strong> list.
            </div>

            <!-- Modal Action Buttons -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" onclick="closeMarkPaidModal()" class="btn btn-outline" style="padding: 0.7rem 1.25rem; font-weight: 700; border-radius: 10px;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="background: #10B981; border-color: #10B981; padding: 0.7rem 1.5rem; font-weight: 800; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <span>✓</span> Confirm Payment &amp; Generate Order ID
                </button>
            </div>

        </form>
    </div>
</div>

<script>
window.pendingDataMap = <?= json_encode($pendingDataMap ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

window.openPendingDetailsModal = function(orderId) {
    const d = window.pendingDataMap ? window.pendingDataMap[orderId] : null;
    if (!d) return;

    document.getElementById('pdm-req-id').textContent = d.req_id;
    document.getElementById('pdm-date').textContent = d.created_at;

    const sb = document.getElementById('pdm-status-badge');
    if (d.status === 'Failed') {
        sb.textContent = 'FAILED';
        sb.style.background = '#FEF2F2';
        sb.style.color = '#DC2626';
        sb.style.border = '1px solid #FECACA';
    } else {
        sb.textContent = 'PENDING';
        sb.style.background = '#FFFBEB';
        sb.style.color = '#D97706';
        sb.style.border = '1px solid #FDE68A';
    }

    document.getElementById('pdm-school-name').textContent = d.school_name;
    const scEl = document.getElementById('pdm-school-code');
    if (d.school_code) {
        scEl.textContent = d.school_code;
        scEl.style.display = 'inline-block';
    } else {
        scEl.style.display = 'none';
    }

    document.getElementById('pdm-student-name').textContent = d.student_name;
    document.getElementById('pdm-grade-class').textContent = d.grade_class;
    const gEl = document.getElementById('pdm-gender');
    if (d.gender) {
        gEl.textContent = d.gender;
        gEl.style.display = 'inline-block';
    } else {
        gEl.style.display = 'none';
    }

    document.getElementById('pdm-parent-name').textContent = d.parent_name;
    document.getElementById('pdm-mobile').textContent = d.mobile;
    const waLink = document.getElementById('pdm-wa-link');
    if (d.wa_url) {
        waLink.href = d.wa_url;
        waLink.style.display = 'inline-block';
    } else {
        waLink.style.display = 'none';
    }

    const emailBox = document.getElementById('pdm-email-box');
    if (d.email) {
        document.getElementById('pdm-email').textContent = d.email;
        emailBox.style.display = 'block';
    } else {
        emailBox.style.display = 'none';
    }

    const fullAddr = (d.address || 'Not specified') + (d.pincode ? ' - ' + d.pincode : '');
    document.getElementById('pdm-address').textContent = fullAddr;

    document.getElementById('pdm-payment-method').textContent = d.payment_method;
    document.getElementById('pdm-txn-id').textContent = d.txn_id || '—';
    document.getElementById('pdm-total-amount').textContent = d.total_amount_formatted;

    document.getElementById('pdm-items-count').textContent = d.items.length + (d.items.length === 1 ? ' item' : ' items');

    const tbody = document.getElementById('pdm-items-tbody');
    tbody.innerHTML = '';
    if (d.items && d.items.length > 0) {
        d.items.forEach(it => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #E2E8F0';
            const isAcc = /\b(belt|socks?|cap|tie|bag|badge|bottle|mask)\b/i.test(it.name || '');
            const hasCustomSize = !isAcc && it.size && !['free', 'standard', 'n/a', 'none', '-'].includes(String(it.size).toLowerCase().trim());
            const sizeDisplay = hasCustomSize 
                ? `<span style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-weight:700; font-size:0.75rem;">Size ${it.size}</span>`
                : `<span style="color:#94A3B8; font-weight:700;">—</span>`;
            tr.innerHTML = `
                <td style="padding: 0.65rem 0.8rem; font-weight: 700; color: #1E2530;">
                    ${it.name}
                </td>
                <td style="padding: 0.65rem 0.8rem; text-align: center;">
                    ${sizeDisplay}
                </td>
                <td style="padding: 0.65rem 0.8rem; text-align: center; font-weight: 700;">
                    ${it.quantity}
                </td>
                <td style="padding: 0.65rem 0.8rem; text-align: right; color: #475569;">
                    ₹${it.unit_price.toFixed(2)}
                </td>
                <td style="padding: 0.65rem 0.8rem; text-align: right; font-weight: 800; color: #1E2530;">
                    ₹${it.total_price.toFixed(2)}
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 1.5rem; color: #64748B;">No items registered in this attempt.</td></tr>';
    }

    const markPaidBtn = document.getElementById('pdm-mark-paid-btn');
    if (markPaidBtn) {
        markPaidBtn.onclick = function() {
            closePendingDetailsModal();
            document.getElementById('modalOrderId').value = d.id;
            document.getElementById('modalReqId').textContent = d.req_id;
            document.getElementById('modalStudent').textContent = d.student_name + (d.school_name ? ' (' + d.school_name + ')' : '');
            document.getElementById('modalParentPhone').textContent = d.parent_name + ' • ' + d.mobile;
            document.getElementById('modalAmount').textContent = d.total_amount_formatted;
            document.getElementById('markPaidModal').style.display = 'flex';
        };
    }

    const modal = document.getElementById('pendingDetailsModal');
    if (modal) modal.style.display = 'flex';
};

window.closePendingDetailsModal = function() {
    const modal = document.getElementById('pendingDetailsModal');
    if (modal) modal.style.display = 'none';
};

window.updateSubSchools = function(parentId) {
    const subSelect = document.getElementById('subSchoolSelect');
    if (!subSelect) return;
    const parentNum = parseInt(parentId) || 0;
    const options = subSelect.querySelectorAll('option');
    let hasVisibleSelected = false;

    options.forEach((opt, idx) => {
        if (idx === 0) return; // Keep "Select" always visible
        const p = parseInt(opt.getAttribute('data-parent')) || 0;
        if (parentNum === 0 || p === parentNum) {
            opt.style.display = '';
            if (opt.selected) hasVisibleSelected = true;
        } else {
            opt.style.display = 'none';
            if (opt.selected) opt.selected = false;
        }
    });

    if (!hasVisibleSelected) {
        subSelect.value = "0";
    }
};

window.openMarkPaidModal = function(btn) {
    const modal = document.getElementById('markPaidModal');
    if (!modal) return;
    document.getElementById('modalOrderId').value = btn.dataset.orderId || '';
    document.getElementById('modalReqId').textContent = btn.dataset.reqId || '';
    document.getElementById('modalStudent').textContent = (btn.dataset.student || '') + (btn.dataset.school ? ' (' + btn.dataset.school + ')' : '');
    document.getElementById('modalParentPhone').textContent = (btn.dataset.parent || '') + ' • ' + (btn.dataset.mobile || '');
    document.getElementById('modalAmount').textContent = btn.dataset.amount || '';
    modal.style.display = 'flex';
};

window.closeMarkPaidModal = function() {
    const modal = document.getElementById('markPaidModal');
    if (modal) modal.style.display = 'none';
};

document.addEventListener('DOMContentLoaded', () => {
    const mModal = document.getElementById('markPaidModal');
    if (mModal) {
        mModal.addEventListener('click', (e) => {
            if (e.target === mModal) {
                closeMarkPaidModal();
            }
        });
    }

    const pdModal = document.getElementById('pendingDetailsModal');
    if (pdModal) {
        pdModal.addEventListener('click', (e) => {
            if (e.target === pdModal) {
                closePendingDetailsModal();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
