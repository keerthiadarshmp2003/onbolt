<?php
/**
 * OnBolt Digital Experience Platform - Partner Percentage Module
 * Requirement 4: Dedicated module with exact columns:
 * Item | Amount | Quantity | Total MRP | Vendor Commission
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (!adminCan('partner_percentage_view')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to view Partner Percentage.');
}
$pdo = getDbConnection();

$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);
$durationFilter = trim($_GET['duration'] ?? 'all');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
$activeDurationLabel = 'Select duration';

// Normalize if from_date or to_date are explicitly provided
if (!empty($fromDate) || !empty($toDate)) {
    if ($durationFilter !== 'today' && $durationFilter !== 'yesterday') {
        $durationFilter = 'custom';
    }
}

$dateOrderCond = '';

switch ($durationFilter) {
    case 'today':
        $dateOrderCond = " AND DATE(o.created_at) = CURRENT_DATE()";
        $activeDurationLabel = 'Today (' . date('d M Y') . ')';
        $fromDate = date('Y-m-d');
        $toDate = date('Y-m-d');
        break;
    case 'yesterday':
        $dateOrderCond = " AND DATE(o.created_at) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
        $activeDurationLabel = 'Yesterday (' . date('d M Y', strtotime('-1 day')) . ')';
        $fromDate = date('Y-m-d', strtotime('-1 day'));
        $toDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'custom':
        if (!empty($fromDate) && !empty($toDate)) {
            $safeFrom = preg_replace('/[^0-9\-]/', '', $fromDate);
            $safeTo = preg_replace('/[^0-9\-]/', '', $toDate);
            $dateOrderCond = " AND o.created_at >= '{$safeFrom} 00:00:00' AND o.created_at <= '{$safeTo} 23:59:59'";
            if ($safeFrom === $safeTo) {
                if ($safeFrom === date('Y-m-d')) {
                    $activeDurationLabel = 'Today (' . date('d M Y') . ')';
                    $durationFilter = 'today';
                } elseif ($safeFrom === date('Y-m-d', strtotime('-1 day'))) {
                    $activeDurationLabel = 'Yesterday (' . date('d M Y', strtotime('-1 day')) . ')';
                    $durationFilter = 'yesterday';
                } else {
                    $activeDurationLabel = date('d M Y', strtotime($safeFrom));
                }
            } else {
                $activeDurationLabel = date('d M Y', strtotime($safeFrom)) . ' - ' . date('d M Y', strtotime($safeTo));
            }
        } elseif (!empty($fromDate)) {
            $safeFrom = preg_replace('/[^0-9\-]/', '', $fromDate);
            $dateOrderCond = " AND o.created_at >= '{$safeFrom} 00:00:00'";
            $activeDurationLabel = 'From ' . date('d M Y', strtotime($safeFrom));
        } elseif (!empty($toDate)) {
            $safeTo = preg_replace('/[^0-9\-]/', '', $toDate);
            $dateOrderCond = " AND o.created_at <= '{$safeTo} 23:59:59'";
            $activeDurationLabel = 'Until ' . date('d M Y', strtotime($safeTo));
        } else {
            $activeDurationLabel = 'Select duration';
            $durationFilter = 'all';
        }
        break;
    default:
        $durationFilter = 'all';
        $activeDurationLabel = 'Select duration';
        $dateOrderCond = '';
        break;
}

// Build export query string
$exportParams = [];
if ($schoolFilter > 0) $exportParams['school_id'] = $schoolFilter;
if ($subSchoolFilter > 0) $exportParams['sub_school_id'] = $subSchoolFilter;
if ($durationFilter !== 'all') $exportParams['duration'] = $durationFilter;
if (!empty($fromDate)) $exportParams['from_date'] = $fromDate;
if (!empty($toDate)) $exportParams['to_date'] = $toDate;
$exportQueryString = !empty($exportParams) ? '&' . http_build_query($exportParams) : '';

// Main schools list (only main schools: standalone single schools and multi-school groups)
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC")->fetchAll();

// Under schools list (only sub-schools / campuses with their parent reference)
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

// Active school labels
$activeMainSchoolLabel = '';
$activeSubSchoolLabel = '';
$selectedMainHasSubs = false;

if ($schoolFilter > 0) {
    foreach ($mainSchools as $ms) {
        if ((int)$ms['id'] === $schoolFilter) {
            $activeMainSchoolLabel = $ms['name'];
            $selectedMainHasSubs = !empty($ms['has_sub_schools']);
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

if (!empty($activeSubSchoolLabel)) {
    $activeSchoolDisplay = (!empty($activeMainSchoolLabel) ? $activeMainSchoolLabel . ' - ' : '') . $activeSubSchoolLabel;
} elseif (!empty($activeMainSchoolLabel)) {
    $activeSchoolDisplay = $activeMainSchoolLabel;
} else {
    $activeSchoolDisplay = 'All Partner Schools';
}

// Determine school IDs in scope
$schoolIdsInScope = [];
if ($subSchoolFilter > 0) {
    $schoolIdsInScope = [$subSchoolFilter];
} elseif ($schoolFilter > 0) {
    $schoolIdsInScope = [$schoolFilter];
    $childIds = $pdo->query("SELECT id FROM schools WHERE parent_id = {$schoolFilter}")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($childIds as $cid) {
        $schoolIdsInScope[] = (int)$cid;
    }
}

$inClause = !empty($schoolIdsInScope) ? implode(',', array_map('intval', $schoolIdsInScope)) : '';

// Base query for Partner Percentage aggregated report
if (!empty($inClause)) {
    $sql = "SELECT 
                p.id AS product_id,
                p.name AS item_name,
                COALESCE(p.price, 0) AS amount,
                COALESCE(p.actual_price, p.vendor_price, ROUND(p.price * 0.70, 2)) AS actual_price,
                COALESCE(p.vendor_commission, ROUND(p.price - COALESCE(p.actual_price, p.vendor_price, p.price * 0.70), 2)) AS unit_commission,
                COALESCE(sales.total_qty, 0) AS total_quantity,
                COALESCE(sales.total_qty * p.price, 0) AS total_mrp,
                COALESCE(sales.total_qty * COALESCE(p.vendor_commission, ROUND(p.price - COALESCE(p.actual_price, p.vendor_price, p.price * 0.70), 2)), 0) AS total_vendor_commission
            FROM products p
            LEFT JOIN (
                SELECT 
                    oi.product_id,
                    SUM(oi.quantity) AS total_qty
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.order_status NOT IN ('Cancelled', 'Cancel')
                  AND o.school_id IN ({$inClause})
                  {$dateOrderCond}
                GROUP BY oi.product_id
            ) sales ON sales.product_id = p.id
            WHERE p.status = 1 
              AND (
                  p.id IN (SELECT product_id FROM school_products WHERE school_id IN ({$inClause}))
                  OR sales.total_qty > 0
              )
            ORDER BY total_mrp DESC, total_quantity DESC, p.name ASC";
} else {
    $sql = "SELECT 
                p.id AS product_id,
                p.name AS item_name,
                COALESCE(p.price, 0) AS amount,
                COALESCE(p.actual_price, p.vendor_price, ROUND(p.price * 0.70, 2)) AS actual_price,
                COALESCE(p.vendor_commission, ROUND(p.price - COALESCE(p.actual_price, p.vendor_price, p.price * 0.70), 2)) AS unit_commission,
                COALESCE(sales.total_qty, 0) AS total_quantity,
                COALESCE(sales.total_qty * p.price, 0) AS total_mrp,
                COALESCE(sales.total_qty * COALESCE(p.vendor_commission, ROUND(p.price - COALESCE(p.actual_price, p.vendor_price, p.price * 0.70), 2)), 0) AS total_vendor_commission
            FROM products p
            LEFT JOIN (
                SELECT 
                    oi.product_id,
                    SUM(oi.quantity) AS total_qty
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.order_status NOT IN ('Cancelled', 'Cancel')
                  {$dateOrderCond}
                GROUP BY oi.product_id
            ) sales ON sales.product_id = p.id
            WHERE p.status = 1
            ORDER BY total_mrp DESC, total_quantity DESC, p.name ASC";
}

$itemsList = $pdo->query($sql)->fetchAll();

// Also check for any order items that might have been ordered without a linked product
$unlinkedSql = "SELECT 
                    NULL AS product_id,
                    oi.product_name AS item_name,
                    oi.unit_price AS amount,
                    ROUND(oi.unit_price * 0.70, 2) AS actual_price,
                    ROUND(oi.unit_price * 0.30, 2) AS unit_commission,
                    SUM(oi.quantity) AS total_quantity,
                    SUM(oi.total_price) AS total_mrp,
                    SUM(oi.quantity * ROUND(oi.unit_price * 0.30, 2)) AS total_vendor_commission
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id AND o.order_status NOT IN ('Cancelled', 'Cancel')
                WHERE (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT id FROM products))";

if (!empty($inClause)) {
    $unlinkedSql .= " AND o.school_id IN ({$inClause})";
}

$unlinkedSql .= " {$dateOrderCond} GROUP BY oi.product_name, oi.unit_price";
$unlinkedItems = $pdo->query($unlinkedSql)->fetchAll();

$reportRows = array_merge($itemsList, $unlinkedItems);

// Calculate Totals
$grandTotalQuantity = 0;
$grandTotalMRP = 0.0;
$grandTotalCommission = 0.0;

foreach ($reportRows as $r) {
    $grandTotalQuantity += (int)$r['total_quantity'];
    $grandTotalMRP += (float)$r['total_mrp'];
    $grandTotalCommission += (float)$r['total_vendor_commission'];
}

// Handle Excel Export
if (isset($_GET['export']) && strtolower($_GET['export']) === 'excel') {
    if (ob_get_level()) ob_end_clean();
    $filename = "OnBolt_Partner_Percentage_Report_" . date('Y-m-d') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
            .title { font-size: 16pt; font-weight: bold; color: #FF6702; padding: 10px 0 4px; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #FF6702; color: #FFFFFF; font-weight: bold; border: 1px solid #EA580C; padding: 8px 10px; text-align: left; }
            td { border: 1px solid #CBD5E1; padding: 6px 10px; }
            .num { text-align: right; }
            .center { text-align: center; }
            .total { background-color: #FFF7ED; font-weight: bold; border-top: 2px solid #FF6702; }
        </style>
    </head>
    <body>
        <div class="title">ONBOLT - Partner Percentage &amp; Vendor Commission Report</div>
        <p>School: <b><?= htmlspecialchars($activeSchoolDisplay) ?></b> &nbsp;|&nbsp; Generated on <?= date('d M Y, h:i A') ?></p>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Amount (₹)</th>
                    <th class="center">Quantity</th>
                    <th class="num">Total MRP (₹)</th>
                    <th class="num">Vendor Commission (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="num">₹<?= number_format((float)$row['amount'], 2) ?></td>
                        <td class="center"><?= (int)$row['total_quantity'] ?></td>
                        <td class="num">₹<?= number_format((float)$row['total_mrp'], 2) ?></td>
                        <td class="num">₹<?= number_format((float)$row['total_vendor_commission'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td>TOTAL SUMMARY</td>
                    <td class="num">-</td>
                    <td class="center"><?= $grandTotalQuantity ?> pcs</td>
                    <td class="num">₹<?= number_format($grandTotalMRP, 2) ?></td>
                    <td class="num">₹<?= number_format($grandTotalCommission, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

$adminActive = 'partner_percentage';
$adminTitle = 'Partner Percentage & Commission Splits';
require_once __DIR__ . '/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto;">

    <!-- KPI Metric Cards Grid -->
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.25rem; margin-bottom:1.5rem;">
        <div class="stat-card" style="border-top: 3.5px solid var(--brand-primary); border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:var(--brand-primary);">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Total MRP Revenue</span>
            <div class="stat-num" style="color:var(--brand-primary); font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= formatCurrency($grandTotalMRP) ?></div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?>"><?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?></div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #059669; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#059669;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Total Vendor Commission</span>
            <div class="stat-num" style="color:#059669; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= formatCurrency($grandTotalCommission) ?></div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?>"><?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?></div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #2563eb; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#2563eb;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Total Units Sold</span>
            <div class="stat-num" style="color:#2563eb; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= $grandTotalQuantity ?> pcs</div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?>"><?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?></div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #d97706; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#d97706;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Catalog Products</span>
            <div class="stat-num" style="color:#d97706; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= count($reportRows) ?> Items</div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?>"><?= htmlspecialchars($activeSchoolDisplay . ' • ' . $activeDurationLabel) ?></div>
        </div>
    </div>

    <!-- Section Header Title -->
    <div style="margin-bottom:0.85rem;">
        <h3 class="heading-2" style="font-size:1.35rem; margin:0 0 0.2rem 0; color:var(--brand-dark);">
            💼 Partner Percentage Breakdown
        </h3>
        <p style="font-size:0.82rem; color:var(--brand-muted); margin:0;">
            Itemized pricing, sales volume, total MRP, and calculated vendor commissions for <strong style="color:var(--brand-dark);"><?= htmlspecialchars($activeSchoolDisplay) ?></strong> (<?= htmlspecialchars($activeDurationLabel) ?>).
        </p>
    </div>

    <style>
    /* Interactive Calendar Range Picker Styles */
    .cal-picker-wrap {
        position: relative;
        display: inline-block;
    }
    .cal-trigger-btn {
        height: 38px;
        min-width: 195px;
        max-width: 260px;
        font-size: 0.83rem;
        padding: 0.42rem 0.75rem;
        border-radius: var(--radius-md);
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        background: #ffffff;
        border: 1px solid var(--brand-border);
        color: var(--brand-dark);
        cursor: pointer;
        box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .cal-trigger-btn:hover {
        border-color: var(--brand-primary);
    }
    .cal-popover-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        z-index: 1050;
        background: #ffffff;
        border: 1px solid #CBD5E1;
        border-radius: 12px;
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.18), 0 6px 16px rgba(0, 0, 0, 0.08);
        width: 310px;
        padding: 12px;
        box-sizing: border-box;
        user-select: none;
    }
    .cal-quick-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #F1F5F9;
    }
    .cal-quick-btn {
        padding: 0.28rem 0.65rem;
        font-size: 0.74rem;
        font-weight: 700;
        border-radius: 6px;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s;
    }
    .cal-quick-btn:hover {
        border-color: var(--brand-primary);
        color: var(--brand-primary);
        background: #FFF7ED;
    }
    .cal-quick-btn.active {
        border-color: var(--brand-primary);
        background: #FFF7ED;
        color: var(--brand-primary);
    }
    .cal-month-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .cal-nav-btn {
        background: #F1F5F9;
        border: none;
        border-radius: 6px;
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        color: #334155;
        cursor: pointer;
        transition: background 0.15s;
        padding: 0;
    }
    .cal-nav-btn:hover {
        background: #E2E8F0;
    }
    .cal-month-label {
        font-size: 0.88rem;
        font-weight: 800;
        color: #0F172A;
    }
    .cal-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        text-align: center;
        margin-bottom: 4px;
    }
    .cal-weekday {
        font-size: 0.7rem;
        font-weight: 800;
        color: #94A3B8;
        padding: 2px 0;
    }
    .cal-days-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
    }
    .cal-day-cell {
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 6px;
        color: #1E293B;
        transition: background 0.12s, color 0.12s;
        position: relative;
    }
    .cal-day-cell:hover:not(.cal-other-month):not(.cal-selected-single):not(.cal-selected-start):not(.cal-selected-end) {
        background: #F1F5F9;
    }
    .cal-day-cell.cal-other-month {
        color: #CBD5E1;
        cursor: default;
        pointer-events: none;
    }
    .cal-day-cell.cal-today::after {
        content: '';
        position: absolute;
        bottom: 3px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--brand-primary);
    }
    .cal-day-cell.cal-selected-single {
        background: var(--brand-primary) !important;
        color: #ffffff !important;
        font-weight: 800;
        border-radius: 8px !important;
    }
    .cal-day-cell.cal-selected-start {
        background: var(--brand-primary) !important;
        color: #ffffff !important;
        font-weight: 800;
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        border-top-left-radius: 8px !important;
        border-bottom-left-radius: 8px !important;
    }
    .cal-day-cell.cal-selected-end {
        background: var(--brand-primary) !important;
        color: #ffffff !important;
        font-weight: 800;
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        border-top-right-radius: 8px !important;
        border-bottom-right-radius: 8px !important;
    }
    .cal-day-cell.cal-in-range {
        background: #FFF7ED !important;
        color: #C2410C !important;
        font-weight: 700;
        border-radius: 0 !important;
    }
    .cal-bottom-actions {
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px solid #F1F5F9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    </style>

    <!-- Unified Single-Line Sorting & Filter Toolbar -->
    <div style="background:#ffffff; border:1px solid var(--brand-border); border-radius:var(--radius-lg); padding:0.65rem 1rem; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; gap:0.6rem; box-shadow:var(--shadow-sm); flex-wrap:nowrap; overflow:visible; position:relative;">
        <form id="pp-filter-form" action="" method="GET" style="margin:0; display:inline-flex; align-items:center; gap:0.5rem; flex-wrap:nowrap; flex-shrink:0;">
            <!-- Main Schools Filter -->
            <select name="school_id" id="pp_school_id_select" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem; border-radius:var(--radius-md); width:160px; font-weight:600;" onchange="handleMainSchoolFilterChange(this)" title="Filter by Main School">
                <option value="">All Schools</option>
                <?php foreach ($mainSchools as $s): ?>
                    <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= sanitize($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Under Schools Filter -->
            <select name="sub_school_id" id="pp_sub_school_id_select" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem; border-radius:var(--radius-md); width:165px; font-weight:600;" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?> title="Filter by Under School">
                <option value=""><?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'No Under Schools' : 'All Under Schools' ?></option>
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

            <!-- Calendar Mode Range Picker Trigger & Popover -->
            <div class="cal-picker-wrap">
                <button type="button" id="cal-trigger-btn" onclick="toggleCalendarPopover(event)" class="cal-trigger-btn" title="Click to open calendar range picker">
                    <span style="display:inline-flex; align-items:center; gap:0.35rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <span style="font-size:0.95rem;">📅</span>
                        <span id="cal-trigger-text" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($activeDurationLabel) ?></span>
                    </span>
                    <span style="font-size:0.75rem; color:#94A3B8; flex-shrink:0;">▾</span>
                </button>

                <!-- Hidden form inputs submitted with GET -->
                <input type="hidden" name="duration" id="cal_duration_input" value="<?= htmlspecialchars($durationFilter) ?>">
                <input type="hidden" name="from_date" id="cal_from_date_input" value="<?= htmlspecialchars($fromDate) ?>">
                <input type="hidden" name="to_date" id="cal_to_date_input" value="<?= htmlspecialchars($toDate) ?>">

                <!-- Calendar Popover Box -->
                <div id="cal-popover" class="cal-popover-dropdown">
                    <!-- Quick Presets Row: Today & Yesterday -->
                    <div class="cal-quick-bar">
                        <span style="font-size:0.72rem; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Quick Sort:</span>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="cal-quick-btn <?= $durationFilter === 'today' ? 'active' : '' ?>" onclick="selectQuickDuration('today')">Today</button>
                            <button type="button" class="cal-quick-btn <?= $durationFilter === 'yesterday' ? 'active' : '' ?>" onclick="selectQuickDuration('yesterday')">Yesterday</button>
                        </div>
                    </div>

                    <!-- Month/Year Header & Nav (< Month Year >) -->
                    <div class="cal-month-nav">
                        <button type="button" onclick="navigateCalMonth(-1)" class="cal-nav-btn" title="Previous Month">‹</button>
                        <span id="cal-month-label" class="cal-month-label">November 2026</span>
                        <button type="button" onclick="navigateCalMonth(1)" class="cal-nav-btn" title="Next Month">›</button>
                    </div>

                    <!-- 7-day grid headers -->
                    <div class="cal-weekdays">
                        <div class="cal-weekday">Su</div>
                        <div class="cal-weekday">Mo</div>
                        <div class="cal-weekday">Tu</div>
                        <div class="cal-weekday">We</div>
                        <div class="cal-weekday">Th</div>
                        <div class="cal-weekday">Fr</div>
                        <div class="cal-weekday">Sa</div>
                    </div>

                    <!-- Days cells with multi-select drag/click range selection -->
                    <div id="cal-days-grid" class="cal-days-grid">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <!-- Footer with Clear and Apply buttons -->
                    <div class="cal-bottom-actions">
                        <button type="button" onclick="clearCalFilter()" style="padding:0.35rem 0.65rem; font-size:0.75rem; font-weight:700; border-radius:6px; border:1px solid #CBD5E1; background:#ffffff; color:#64748B; cursor:pointer;" title="Clear date filter to show all dates">
                            Clear
                        </button>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span id="cal-selection-preview" style="font-size:0.72rem; color:#64748B; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:right;"></span>
                            <button type="button" id="cal-apply-btn" onclick="applyCalSelection()" class="btn btn-primary btn-sm" style="padding:0.35rem 0.75rem; font-size:0.75rem; font-weight:700; border-radius:6px; cursor:pointer;">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($schoolFilter > 0 || $subSchoolFilter > 0 || ($durationFilter !== 'all' && !empty($durationFilter))): ?>
                <a href="<?= BASE_URL ?>/admin/partner_percentage.php" class="btn btn-outline btn-sm" style="height:38px; padding:0.42rem 0.65rem; font-size:0.8rem; font-weight:700; display:inline-flex; align-items:center; white-space:nowrap;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Right Action Buttons in Same Line -->
        <div style="display:inline-flex; align-items:center; gap:0.5rem; flex-shrink:0;">
            <a href="<?= BASE_URL ?>/admin/partner_percentage.php?export=excel<?= $exportQueryString ?>" class="btn btn-outline btn-sm" style="height:38px; color:#059669; border-color:#10B981; background:#ffffff; font-weight:700; padding:0.42rem 0.85rem; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;" title="Export filtered report to Excel">
                📊 Excel Export
            </a>
            <button type="button" onclick="window.print()" class="btn btn-outline btn-sm" style="height:38px; color:#DC2626; border-color:#EF4444; background:#ffffff; font-weight:700; padding:0.42rem 0.85rem; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;" title="Print or save as PDF">
                📄 Print / PDF
            </button>
        </div>
    </div>

    <!-- ==================== PARTNER PERCENTAGE TABLE (Exact Columns: Item | Amount | Quantity | Total MRP | Vendor Commission) ==================== -->
    <div class="table-scroll-frame" style="max-height: 520px; overflow-y: auto; overflow-x: auto; position: relative; background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
        <table id="partner-percentage-table" class="admin-table" style="margin:0; border:none; border-radius:0; border-collapse:separate; border-spacing:0;">
            <thead>
                <tr style="border-bottom:2px solid var(--brand-border);">
                    <th onclick="sortReportTable('name', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:1rem 1.25rem; font-size:0.8rem; font-weight:800; color:var(--brand-dark); box-shadow:0 1px 2px rgba(0,0,0,0.06); cursor:pointer; user-select:none;" title="Click to sort by Item Name">
                        <div style="display:inline-flex; align-items:center; gap:6px;">
                            <span>Item</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortReportTable('amount', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:1rem 1.25rem; font-size:0.8rem; font-weight:800; color:var(--brand-dark); text-align:right; box-shadow:0 1px 2px rgba(0,0,0,0.06); cursor:pointer; user-select:none;" title="Click to sort by Unit Price">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>Amount</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortReportTable('qty', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:1rem 1.25rem; font-size:0.8rem; font-weight:800; color:var(--brand-dark); text-align:center; box-shadow:0 1px 2px rgba(0,0,0,0.06); cursor:pointer; user-select:none;" title="Click to sort by Quantity Sold">
                        <div style="display:inline-flex; align-items:center; justify-content:center; gap:6px; width:100%;">
                            <span>Quantity</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortReportTable('mrp', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:1rem 1.25rem; font-size:0.8rem; font-weight:800; color:var(--brand-dark); text-align:right; box-shadow:0 1px 2px rgba(0,0,0,0.06); cursor:pointer; user-select:none;" title="Click to sort by Total MRP">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>Total MRP</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortReportTable('commission', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:1rem 1.25rem; font-size:0.8rem; font-weight:800; color:var(--brand-dark); text-align:right; box-shadow:0 1px 2px rgba(0,0,0,0.06); cursor:pointer; user-select:none;" title="Click to sort by Vendor Commission">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>Vendor Commission</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="pp-table-body">
                <?php if (empty($reportRows)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:3rem 1.5rem; color:var(--brand-muted);">
                            <div style="font-size:2rem; margin-bottom:0.5rem;">📦</div>
                            <div style="font-weight:700; font-size:0.95rem; color:#475569;">No uniform apparel records found for <?= htmlspecialchars($activeSchoolDisplay) ?>.</div>
                            <div style="font-size:0.82rem; color:#94A3B8; margin-top:0.25rem;">Try choosing another partner school or reset to All Schools.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportRows as $row): ?>
                        <tr class="pp-data-row" 
                            data-name="<?= htmlspecialchars(strtolower($row['item_name'])) ?>" 
                            data-amount="<?= (float)$row['amount'] ?>" 
                            data-qty="<?= (int)$row['total_quantity'] ?>" 
                            data-mrp="<?= (float)$row['total_mrp'] ?>" 
                            data-commission="<?= (float)$row['total_vendor_commission'] ?>">
                            <td style="padding:1rem 1.25rem; font-weight:700; color:#0F172A; border-bottom:1px solid var(--brand-border);">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <span style="font-size:1.1rem;">👕</span>
                                    <span><?= sanitize($row['item_name']) ?></span>
                                </div>
                            </td>
                            <td style="padding:1rem 1.25rem; text-align:right; font-weight:700; color:#475569; border-bottom:1px solid var(--brand-border);">
                                <?= formatCurrency((float)$row['amount']) ?>
                            </td>
                            <td style="padding:1rem 1.25rem; text-align:center; font-weight:800; color:#0F172A; border-bottom:1px solid var(--brand-border);">
                                <span style="background:rgba(255,103,2,0.08); color:var(--brand-primary); padding:0.25rem 0.65rem; border-radius:9999px; font-size:0.85rem;">
                                    <?= (int)$row['total_quantity'] ?> pcs
                                </span>
                            </td>
                            <td style="padding:1rem 1.25rem; text-align:right; font-weight:800; color:#0F172A; font-size:0.95rem; border-bottom:1px solid var(--brand-border);">
                                <?= formatCurrency((float)$row['total_mrp']) ?>
                            </td>
                            <td style="padding:1rem 1.25rem; text-align:right; font-weight:800; color:#059669; font-size:0.95rem; border-bottom:1px solid var(--brand-border);">
                                <?= formatCurrency((float)$row['total_vendor_commission']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($reportRows)): ?>
                <tfoot>
                    <tr class="sticky-bottom-row" style="background:#FFF7ED; font-weight:800;">
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; font-size:0.95rem; color:var(--brand-dark); text-transform:uppercase; letter-spacing:0.04em; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            GRAND TOTAL SUMMARY
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:var(--brand-muted); border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">-</td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:center; color:var(--brand-primary); font-size:1rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            <?= $grandTotalQuantity ?> pcs
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:var(--brand-primary); font-size:1.1rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            <?= formatCurrency($grandTotalMRP) ?>
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:#059669; font-size:1.1rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            <?= formatCurrency($grandTotalCommission) ?>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function handleMainSchoolFilterChange(mainSelect) {
    const subSelect = document.getElementById('pp_sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}

// ==================== INTERACTIVE CALENDAR RANGE PICKER ====================
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const shortMonthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const todayStr = '<?= date("Y-m-d") ?>';
const yesterdayStr = '<?= date("Y-m-d", strtotime("-1 day")) ?>';

let calStartDate = '<?= !empty($fromDate) ? $fromDate : "" ?>';
let calEndDate = '<?= !empty($toDate) ? $toDate : "" ?>';
let calCurrentYear = 2026;
let calCurrentMonth = 10; // Default November (0-based: 10)

// Initialize view to active date's month if available
if (calStartDate) {
    const parts = calStartDate.split('-');
    if (parts.length === 3) {
        calCurrentYear = parseInt(parts[0], 10);
        calCurrentMonth = parseInt(parts[1], 10) - 1;
    }
} else {
    const dNow = new Date();
    calCurrentYear = dNow.getFullYear();
    calCurrentMonth = dNow.getMonth();
}

let isDragging = false;
let dragAnchorDate = null;
let awaitingSecondClick = false;

function toggleCalendarPopover(e) {
    if (e) e.stopPropagation();
    const pop = document.getElementById('cal-popover');
    if (!pop) return;
    if (pop.style.display === 'block') {
        pop.style.display = 'none';
    } else {
        pop.style.display = 'block';
        renderCalendarGrid();
    }
}

function navigateCalMonth(direction) {
    calCurrentMonth += direction;
    if (calCurrentMonth < 0) {
        calCurrentMonth = 11;
        calCurrentYear--;
    } else if (calCurrentMonth > 11) {
        calCurrentMonth = 0;
        calCurrentYear++;
    }
    renderCalendarGrid();
}

function formatDisplayDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const y = parts[0];
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    return `${d} ${shortMonthNames[m]} ${y}`;
}

function updateVisualSelection() {
    const cells = document.querySelectorAll('.cal-day-cell:not(.cal-other-month)');
    let start = calStartDate;
    let end = calEndDate;

    if (start && end && start > end) {
        const tmp = start; start = end; end = tmp;
    }

    cells.forEach(cell => {
        const d = cell.dataset.date;
        cell.classList.remove('cal-selected-single', 'cal-selected-start', 'cal-selected-end', 'cal-in-range');

        if (start && end && start === end && d === start) {
            cell.classList.add('cal-selected-single');
        } else if (start && end) {
            if (d === start) cell.classList.add('cal-selected-start');
            else if (d === end) cell.classList.add('cal-selected-end');
            else if (d > start && d < end) cell.classList.add('cal-in-range');
        } else if (start && !end && d === start) {
            cell.classList.add('cal-selected-single');
        }
    });

    // Update bottom preview
    const previewEl = document.getElementById('cal-selection-preview');
    if (previewEl) {
        if (start && end && start !== end) {
            previewEl.textContent = `${formatDisplayDate(start)} - ${formatDisplayDate(end)}`;
            previewEl.title = previewEl.textContent;
        } else if (start) {
            previewEl.textContent = formatDisplayDate(start);
            previewEl.title = previewEl.textContent;
        } else {
            previewEl.textContent = 'Select range';
            previewEl.title = '';
        }
    }
}

function renderCalendarGrid() {
    const grid = document.getElementById('cal-days-grid');
    const monthLabel = document.getElementById('cal-month-label');
    if (!grid || !monthLabel) return;

    monthLabel.textContent = `${monthNames[calCurrentMonth]} ${calCurrentYear}`;
    grid.innerHTML = '';

    const firstDayIndex = new Date(calCurrentYear, calCurrentMonth, 1).getDay();
    const daysInMonth = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();
    const prevMonthDays = new Date(calCurrentYear, calCurrentMonth, 0).getDate();

    // Fill days of previous month
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell cal-other-month';
        cell.textContent = prevMonthDays - i;
        grid.appendChild(cell);
    }

    // Fill days of current month
    for (let d = 1; d <= daysInMonth; d++) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell';
        cell.textContent = d;

        const mm = String(calCurrentMonth + 1).padStart(2, '0');
        const dd = String(d).padStart(2, '0');
        const dateStr = `${calCurrentYear}-${mm}-${dd}`;
        cell.dataset.date = dateStr;

        if (dateStr === todayStr) {
            cell.classList.add('cal-today');
            cell.title = 'Today';
        }

        // Drag & Click Selection Events
        cell.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isDragging = true;
            dragAnchorDate = dateStr;
            calStartDate = dateStr;
            calEndDate = dateStr;
            awaitingSecondClick = false;
            updateVisualSelection();
        });

        cell.addEventListener('mouseenter', function() {
            if (isDragging && dragAnchorDate) {
                if (dateStr < dragAnchorDate) {
                    calStartDate = dateStr;
                    calEndDate = dragAnchorDate;
                } else {
                    calStartDate = dragAnchorDate;
                    calEndDate = dateStr;
                }
                updateVisualSelection();
            } else if (awaitingSecondClick && calStartDate) {
                let start = calStartDate;
                let end = dateStr;
                if (end < start) { const tmp = start; start = end; end = tmp; }
                const cells = document.querySelectorAll('.cal-day-cell:not(.cal-other-month)');
                cells.forEach(c => {
                    const cd = c.dataset.date;
                    c.classList.remove('cal-selected-single', 'cal-selected-start', 'cal-selected-end', 'cal-in-range');
                    if (cd === start && cd === end) c.classList.add('cal-selected-single');
                    else if (cd === start) c.classList.add('cal-selected-start');
                    else if (cd === end) c.classList.add('cal-selected-end');
                    else if (cd > start && cd < end) c.classList.add('cal-in-range');
                });
            }
        });

        cell.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!isDragging) {
                if (!awaitingSecondClick || !calStartDate) {
                    calStartDate = dateStr;
                    calEndDate = dateStr;
                    awaitingSecondClick = true;
                } else {
                    if (dateStr < calStartDate) {
                        calEndDate = calStartDate;
                        calStartDate = dateStr;
                    } else {
                        calEndDate = dateStr;
                    }
                    awaitingSecondClick = false;
                }
                updateVisualSelection();
            }
        });

        grid.appendChild(cell);
    }

    // Fill remaining days of the week for grid balance
    const totalFilled = firstDayIndex + daysInMonth;
    const remaining = (totalFilled % 7 === 0) ? 0 : 7 - (totalFilled % 7);
    for (let j = 1; j <= remaining; j++) {
        const cell = document.createElement('div');
        cell.className = 'cal-day-cell cal-other-month';
        cell.textContent = j;
        grid.appendChild(cell);
    }

    updateVisualSelection();
}

// Global Mouse Up Listener for Drag-Selection
document.addEventListener('mouseup', function() {
    if (isDragging) {
        isDragging = false;
        dragAnchorDate = null;
        updateVisualSelection();
    }
});

// Close Popover when clicking outside
document.addEventListener('mousedown', function(e) {
    const pop = document.getElementById('cal-popover');
    const trigger = document.getElementById('cal-trigger-btn');
    if (pop && pop.style.display === 'block') {
        if (!pop.contains(e.target) && !trigger.contains(e.target)) {
            pop.style.display = 'none';
        }
    }
});

function selectQuickDuration(type) {
    const durInput = document.getElementById('cal_duration_input');
    const fromInput = document.getElementById('cal_from_date_input');
    const toInput = document.getElementById('cal_to_date_input');
    const form = document.getElementById('pp-filter-form');

    if (type === 'today') {
        durInput.value = 'today';
        fromInput.value = todayStr;
        toInput.value = todayStr;
    } else if (type === 'yesterday') {
        durInput.value = 'yesterday';
        fromInput.value = yesterdayStr;
        toInput.value = yesterdayStr;
    }
    form.submit();
}

function clearCalFilter() {
    document.getElementById('cal_duration_input').value = 'all';
    document.getElementById('cal_from_date_input').value = '';
    document.getElementById('cal_to_date_input').value = '';
    document.getElementById('pp-filter-form').submit();
}

function applyCalSelection() {
    if (!calStartDate) {
        clearCalFilter();
        return;
    }
    let start = calStartDate;
    let end = calEndDate || calStartDate;
    if (start > end) {
        const tmp = start; start = end; end = tmp;
    }

    document.getElementById('cal_duration_input').value = 'custom';
    document.getElementById('cal_from_date_input').value = start;
    document.getElementById('cal_to_date_input').value = end;
    document.getElementById('pp-filter-form').submit();
}

// Client-side Column Sorting
let currentSortColumn = 'mrp';
let currentSortDirection = 'desc';

function sortReportTable(column, thElement) {
    const tbody = document.getElementById('pp-table-body');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr.pp-data-row'));
    if (rows.length === 0) return;

    // Toggle direction if clicking same column, else default desc for numbers, asc for name
    if (currentSortColumn === column) {
        currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
    } else {
        currentSortColumn = column;
        currentSortDirection = (column === 'name') ? 'asc' : 'desc';
    }

    // Update all sort icon indicators
    document.querySelectorAll('#partner-percentage-table thead .sort-icon').forEach(icon => {
        icon.textContent = '↕';
        icon.style.color = '#94A3B8';
    });

    const activeIcon = thElement.querySelector('.sort-icon');
    if (activeIcon) {
        activeIcon.textContent = (currentSortDirection === 'asc') ? '▲' : '▼';
        activeIcon.style.color = 'var(--brand-primary)';
    }

    // Sort rows
    rows.sort((a, b) => {
        let valA, valB;
        if (column === 'name') {
            valA = a.dataset.name || '';
            valB = b.dataset.name || '';
            return (currentSortDirection === 'asc') ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else {
            valA = parseFloat(a.dataset[column] || 0);
            valB = parseFloat(b.dataset[column] || 0);
            return (currentSortDirection === 'asc') ? (valA - valB) : (valB - valA);
        }
    });

    // Re-append sorted rows to tbody
    rows.forEach(r => tbody.appendChild(r));
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
