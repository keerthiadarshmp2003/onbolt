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

// Base condition: Strictly records where official order_reference has NOT been generated yet
$whereSql = "WHERE (o.order_reference IS NULL OR o.order_reference = '')";

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
$query = "SELECT o.*, s.name as school_name, s.code as school_code,
                 p.transaction_ref as gateway_txn_ref, p.payment_method as gateway_mode, p.gateway_response
          FROM orders o
          LEFT JOIN schools s ON o.school_id = s.id
          LEFT JOIN payments p ON p.order_id = o.id
          {$whereSql}
          ORDER BY o.id DESC";

$pendingList = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for each pending order
$orderItemsMap = [];
if (!empty($pendingList)) {
    $orderIds = array_column($pendingList, 'id');
    $inClause = implode(',', array_map('intval', $orderIds));
    $itemsStmt = $pdo->query("SELECT order_id, product_name, size, quantity, unit_price, total_price FROM order_items WHERE order_id IN ({$inClause})");
    while ($it = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $orderItemsMap[$it['order_id']][] = $it;
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
        <div style="flex: 1 1 240px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                🔍 Parent Registered Mobile / Request ID
            </label>
            <div style="position: relative;">
                <input type="text" name="search" value="<?= htmlspecialchars($searchMobile) ?>" 
                       placeholder="Select" 
                       style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.4rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 1rem;">📱</span>
            </div>
        </div>

        <!-- Status Filter -->
        <div style="flex: 0 1 150px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Gateway Status
            </label>
            <select name="status" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="">Select</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="Failed" <?= $statusFilter === 'Failed' ? 'selected' : '' ?>>✕ Failed / Dropped</option>
            </select>
        </div>

        <!-- School Filter -->
        <div style="flex: 1 1 200px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                School Filter
            </label>
            <select name="school_id" id="schoolSelect" onchange="updateSubSchools(this.value)" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="0">Select</option>
                <?php foreach ($mainSchools as $ms): ?>
                    <option value="<?= $ms['id'] ?>" <?= $schoolFilter === (int)$ms['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ms['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sub-School / Campus Filter -->
        <div style="flex: 1 1 200px;">
            <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 0.35rem;">
                Sub-School / Campus
            </label>
            <select name="sub_school_id" id="subSchoolSelect" style="width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #F8FAFC;">
                <option value="0">Select</option>
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

        <!-- Filter Action Buttons -->
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.4rem; font-weight: 700; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>Filter</span>
            </button>
            <?php if (!empty($searchMobile) || !empty($statusFilter) || $schoolFilter > 0 || $subSchoolFilter > 0): ?>
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
                <th style="width: 140px;">Request ID</th>
                <th style="min-width: 160px;">Parent Details</th>
                <th style="min-width: 150px;">Student &amp; Class</th>
                <th style="min-width: 150px;">School</th>
                <th style="min-width: 140px;">Items</th>
                <th style="width: 110px; text-align: right;">Amount</th>
                <th style="width: 130px; text-align: center;">Status</th>
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
                    $items = $orderItemsMap[$orderId] ?? [];
                    $phoneClean = preg_replace('/[^0-9]/', '', $row['mobile'] ?? '');
                    if (strlen($phoneClean) === 10) {
                        $phoneClean = '91' . $phoneClean;
                    }
                    $waText = "Hello " . ($row['parent_name'] ?? 'Parent') . ", this is OnBolt Uniforms regarding your checkout attempt (" . $displayReqId . ") for " . ($row['student_name'] ?? 'your ward') . " at " . ($row['school_name'] ?? 'School') . ". Need assistance completing your order?";
                    $waUrl = "https://wa.me/{$phoneClean}?text=" . urlencode($waText);
                ?>
                <tr>
                    <!-- Request ID -->
                    <td>
                        <span style="font-family: monospace; font-size: 0.88rem; font-weight: 800; color: #475569; background: #F1F5F9; padding: 4px 8px; border-radius: 6px; display: inline-block;">
                            <?= htmlspecialchars($displayReqId) ?>
                        </span>
                    </td>

                    <!-- Parent Details -->
                    <td>
                        <div style="font-weight: 800; color: #1E2530;">
                            <?= htmlspecialchars($row['parent_name'] ?: 'Not Specified') ?>
                        </div>
                        <div style="margin-top: 3px;">
                            <span style="font-weight: 700; color: #0284C7; font-size: 0.85rem;">
                                <?= htmlspecialchars($row['mobile']) ?>
                            </span>
                        </div>
                        <?php if (!empty($row['email'])): ?>
                            <div style="font-size: 0.72rem; color: #64748B; margin-top: 2px;">
                                <?= htmlspecialchars($row['email']) ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- Student & Class -->
                    <td>
                        <div style="font-weight: 700; color: #1E2530;">
                            <?= htmlspecialchars($row['student_name'] ?: '—') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #64748B;">
                            Class: <strong><?= htmlspecialchars($row['grade_class'] ?: '—') ?></strong>
                            <?php if (!empty($row['gender'])): ?>
                                <span style="margin-left: 4px; font-size: 0.7rem; background: #F1F5F9; padding: 2px 5px; border-radius: 4px;"><?= htmlspecialchars($row['gender']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- School -->
                    <td>
                        <div style="font-weight: 700; color: #334155; font-size: 0.85rem;">
                            <?= htmlspecialchars($row['school_name'] ?? '—') ?>
                        </div>
                        <?php if (!empty($row['school_code'])): ?>
                            <span style="font-size: 0.68rem; font-weight: 800; color: #FF6702; background: #FFF7ED; padding: 2px 6px; border-radius: 4px;">
                                <?= htmlspecialchars($row['school_code']) ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Items Summary -->
                    <td>
                        <div style="font-size: 0.85rem; color: #1E2530; font-weight: 700;">
                            <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
                        </div>
                    </td>

                    <!-- Payable Amount -->
                    <td style="text-align: right;">
                        <div style="font-weight: 800; color: #1E2530; font-size: 0.95rem;">
                            <?= formatCurrency((float)$row['total_amount']) ?>
                        </div>
                        <div style="font-size: 0.72rem; font-weight: 600; color: #64748B;">
                            <?= htmlspecialchars(formatDisplayPaymentMethod($row['payment_method'], $row['gateway_response'] ?? null)) ?>
                        </div>
                    </td>

                    <!-- Status Badge -->
                    <td style="text-align: center;">
                        <?php if ($pStatus === 'Failed'): ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 9999px; font-size: 0.75rem; font-weight: 800;">
                                ✕ FAILED
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 4px 10px; background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; border-radius: 9999px; font-size: 0.75rem; font-weight: 800;">
                                ⏳ PENDING
                            </span>
                        <?php endif; ?>
                        <?php 
                        $displayTxnId = !empty($row['gateway_transaction_id']) ? $row['gateway_transaction_id'] : ($row['gateway_txn_ref'] ?? '');
                        if (!empty($displayTxnId)): ?>
                            <div style="font-size: 0.68rem; color: #0284C7; font-weight: 700; margin-top: 4px; font-family: monospace; background: #F0F9FF; padding: 2px 6px; border-radius: 4px; display: inline-block; word-break: break-all;" title="Gateway Transaction ID: <?= htmlspecialchars($displayTxnId) ?>">
                                Txn: <?= htmlspecialchars($displayTxnId) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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

            <!-- Payment Method Radio / Select -->
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.4rem;">
                    Payment Method Received <span style="color: #DC2626;">*</span>
                </label>
                <select name="payment_method" required style="width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; font-weight: 600; color: #1E2530; background: #ffffff;">
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
                <input type="text" name="payment_reference" placeholder="e.g. UTR12345678, Cash Receipt #88..." 
                       style="width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.9rem; color: #1E2530;">
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.4rem;">
                    Admin Verification Note
                </label>
                <input type="text" name="admin_notes" placeholder="Optional notes for internal tracking..." 
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
    const modal = document.getElementById('markPaidModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeMarkPaidModal();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
