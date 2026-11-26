<?php
/**
 * OnBolt Digital Experience Platform - School Catalog Overview (View Only)
 * Read-only overview of items school-wise with Total MRP, Product Rate, and Vendor Commission
 * URL: /admin/catalog_overview.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (!adminCan('catalog_overview_view')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to view Catalog Overview.');
}
$adminName = sanitize($admin['full_name']);
$adminRole = sanitize($admin['role_name']);

$pdo = getDbConnection();

// Filters
$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);
$catFilter = (int)($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Main schools list (only main schools: standalone single schools and multi-school groups)
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE (parent_id IS NULL OR parent_id = 0) ORDER BY name ASC")->fetchAll();

// Under schools list (only sub-schools / campuses with their parent reference)
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

// Categories
$categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

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

// Build query
$whereClauses = ["p.status = 1"];
$params = [];

if ($subSchoolFilter > 0) {
    $whereClauses[] = "sp.school_id = ?";
    $params[] = $subSchoolFilter;
} elseif ($schoolFilter > 0) {
    $childIds = $pdo->query("SELECT id FROM schools WHERE parent_id = {$schoolFilter}")->fetchAll(PDO::FETCH_COLUMN);
    $inScope = array_merge([$schoolFilter], array_map('intval', $childIds));
    $inClause = implode(',', $inScope);
    $whereClauses[] = "sp.school_id IN ({$inClause})";
}

if ($catFilter > 0) {
    $whereClauses[] = "p.category_id = ?";
    $params[] = $catFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(p.name LIKE ? OR p.sku LIKE ? OR s.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = implode(' AND ', $whereClauses);

$sql = "SELECT 
            sp.id AS sp_id,
            sp.school_id,
            s.name AS school_name,
            s.parent_id,
            p_school.name AS parent_school_name,
            p.id AS product_id,
            p.name AS item_name,
            p.sku,
            p.description,
            p.short_description,
            p.featured_image,
            p.available_sizes,
            p.is_kit,
            COALESCE(c.name, 'General Uniform') AS category_name,
            COALESCE(sp.actual_price, p.actual_price, p.vendor_price, ROUND(COALESCE(sp.custom_price, p.price) * 0.70, 2)) AS product_rate,
            COALESCE(sp.vendor_commission, p.vendor_commission, ROUND(COALESCE(sp.custom_price, p.price) - COALESCE(sp.actual_price, p.actual_price, p.vendor_price, p.price * 0.70), 2)) AS vendor_commission,
            COALESCE(sp.custom_price, p.price) AS total_mrp,
            sp.is_mandatory
        FROM school_products sp
        JOIN products p ON sp.product_id = p.id
        JOIN schools s ON sp.school_id = s.id
        LEFT JOIN schools p_school ON s.parent_id = p_school.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE {$whereSql}
        ORDER BY s.name ASC, p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$catalogRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$totalItems = count($catalogRows);
$distinctSchools = [];
$totalProductRate = 0.0;
$totalCommission = 0.0;
$totalMRP = 0.0;

foreach ($catalogRows as $cr) {
    $distinctSchools[$cr['school_id']] = true;
    $totalProductRate += (float)$cr['product_rate'];
    $totalCommission += (float)$cr['vendor_commission'];
    $totalMRP += (float)$cr['total_mrp'];
}

$schoolsCoveredCount = count($distinctSchools);
$avgProductRate = $totalItems > 0 ? $totalProductRate / $totalItems : 0.0;
$avgCommission = $totalItems > 0 ? $totalCommission / $totalItems : 0.0;

// Build export query string
$exportParams = [];
if ($schoolFilter > 0) $exportParams['school_id'] = $schoolFilter;
if ($subSchoolFilter > 0) $exportParams['sub_school_id'] = $subSchoolFilter;
if ($catFilter > 0) $exportParams['category_id'] = $catFilter;
if (!empty($search)) $exportParams['search'] = $search;
$exportQueryString = !empty($exportParams) ? '&' . http_build_query($exportParams) : '';

// Excel / CSV Export Handler
if (isset($_GET['export']) && (strtolower($_GET['export']) === 'excel' || strtolower($_GET['export']) === 'csv')) {
    if (ob_get_level()) ob_end_clean();
    $safeSchool = preg_replace('/[^a-zA-Z0-9_-]/', '_', $activeSchoolDisplay);
    $filename = "OnBolt_Catalog_Overview_{$safeSchool}_" . date('Y-m-d') . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($out, ['ONBOLT COMPLETE BRANDING SOLUTIONS - School Uniform Catalog Overview']);
    fputcsv($out, [
        'School Scope: ' . $activeSchoolDisplay,
        'Category: ' . (!empty($catFilter) ? 'Filtered Category' : 'All Categories'),
        'Generated: ' . date('d M Y, h:i A')
    ]);
    fputcsv($out, []); // Blank separator row

    fputcsv($out, ['Sl No', 'Item Name', 'School Name', 'Product Rate (INR)', 'Vendor Commission (INR)', 'Total MRP (INR)']);

    $idx = 1;
    foreach ($catalogRows as $row) {
        fputcsv($out, [
            $idx++,
            $row['item_name'],
            $row['school_name'] ?? '',
            number_format((float)$row['product_rate'], 2, '.', ''),
            number_format((float)$row['vendor_commission'], 2, '.', ''),
            number_format((float)$row['total_mrp'], 2, '.', '')
        ]);
    }

    fputcsv($out, []); // Blank row before summary
    fputcsv($out, [
        '',
        'CATALOG TOTAL SUMMARY (' . $totalItems . ' Items)',
        '',
        number_format($totalProductRate, 2, '.', ''),
        number_format($totalCommission, 2, '.', ''),
        number_format($totalMRP, 2, '.', '')
    ]);

    fclose($out);
    exit;
}

$adminActive = 'catalog_overview';
$adminTitle = 'School Catalog Overview';
require_once __DIR__ . '/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto;">

    <!-- KPI Metric Cards Grid -->
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1.25rem; margin-bottom:1.5rem;">
        <div class="stat-card" style="border-top: 3.5px solid var(--brand-primary); border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:var(--brand-primary);">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Total Catalog Items</span>
            <div class="stat-num" style="color:var(--brand-primary); font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= $totalItems ?> Items</div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($activeSchoolDisplay) ?>"><?= htmlspecialchars($activeSchoolDisplay) ?></div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #059669; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#059669;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Total Catalog Value</span>
            <div class="stat-num" style="color:#059669; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= formatCurrency($totalMRP) ?></div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Combined Total Selling MRP</div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #2563eb; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#2563eb;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Avg. Product Rate</span>
            <div class="stat-num" style="color:#2563eb; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= formatCurrency($avgProductRate) ?></div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Actual / Vendor Cost Rate</div>
        </div>
        <div class="stat-card" style="border-top: 3.5px solid #d97706; border-radius:12px; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #E2E8F0; border-top-width:3.5px; border-top-color:#d97706;">
            <span class="stat-label" style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; display:block;">Avg. Vendor Commission</span>
            <div class="stat-num" style="color:#d97706; font-size:1.8rem; font-weight:900; margin:0.4rem 0 0.2rem 0;"><?= formatCurrency($avgCommission) ?></div>
            <div style="font-size:0.75rem; color:#64748B; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">Partner Margin per Unit</div>
        </div>
    </div>

    <!-- Section Header Title -->
    <div style="margin-bottom:0.85rem; display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h3 class="heading-2" style="font-size:1.35rem; margin:0 0 0.2rem 0; color:var(--brand-dark); display:flex; align-items:center; gap:0.5rem;">
                <span>📋</span> School Catalog Overview
                <span style="font-size:0.72rem; background:#E0F2FE; color:#0369A1; padding:0.2rem 0.55rem; border-radius:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">View Only</span>
            </h3>
            <p style="font-size:0.82rem; color:var(--brand-muted); margin:0;">
                Read-only overview of uniforms and apparel with itemized Product Rates, Vendor Commissions, and Total Selling MRPs for <strong style="color:var(--brand-dark);"><?= htmlspecialchars($activeSchoolDisplay) ?></strong>.
            </p>
        </div>
    </div>

    <!-- Unified Single-Line Sorting & Filter Toolbar -->
    <div style="background:#ffffff; border:1px solid var(--brand-border); border-radius:var(--radius-lg); padding:0.65rem 1rem; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; gap:0.6rem; box-shadow:var(--shadow-sm); flex-wrap:nowrap; overflow:visible; position:relative;">
        <form id="catalog-filter-form" action="" method="GET" style="margin:0; display:inline-flex; align-items:center; gap:0.5rem; flex-wrap:nowrap; flex-shrink:0;">
            <!-- Main Schools Filter -->
            <select name="school_id" id="co_school_id_select" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem; border-radius:var(--radius-md); width:160px; font-weight:600;" onchange="handleMainSchoolChange(this)" title="Filter by Main School">
                <option value="">Select School</option>
                <?php foreach ($mainSchools as $s): ?>
                    <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= sanitize($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Under Schools Filter -->
            <select name="sub_school_id" id="co_sub_school_id_select" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem; border-radius:var(--radius-md); width:165px; font-weight:600;" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?> title="Filter by Under School">
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

            <!-- Category Filter -->
            <select name="category_id" id="co_category_id_select" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem; border-radius:var(--radius-md); width:150px; font-weight:600;" onchange="this.form.submit()" title="Filter by Category">
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Search Input -->
            <div style="position:relative; display:inline-flex; align-items:center;">
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Enter your Uniform Name or SKU" class="form-control" style="font-size:0.83rem; height:38px; padding:0.42rem 0.65rem 0.42rem 1.85rem; border-radius:var(--radius-md); width:185px;">
                <span style="position:absolute; left:0.6rem; color:#94A3B8; font-size:0.85rem; pointer-events:none;">🔍</span>
            </div>

            <button type="submit" class="btn btn-primary btn-sm" style="height:38px; padding:0.42rem 0.75rem; font-size:0.8rem; font-weight:700;">Filter</button>

            <?php if ($schoolFilter > 0 || $subSchoolFilter > 0 || $catFilter > 0 || !empty($search)): ?>
                <a href="<?= BASE_URL ?>/admin/catalog_overview.php" class="btn btn-outline btn-sm" style="height:38px; padding:0.42rem 0.65rem; font-size:0.8rem; font-weight:700; display:inline-flex; align-items:center; white-space:nowrap;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Right Action Buttons in Same Line -->
        <div style="display:inline-flex; align-items:center; gap:0.5rem; flex-shrink:0;">
            <a href="<?= BASE_URL ?>/admin/catalog_overview.php?export=excel<?= $exportQueryString ?>" class="btn btn-outline btn-sm" style="height:38px; color:#059669; border-color:#10B981; background:#ffffff; font-weight:700; padding:0.42rem 0.85rem; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;" title="Export view-only catalog to Excel (.csv)">
                📊 Excel Export
            </a>
            <button type="button" onclick="exportCatalogPdf()" class="btn btn-outline btn-sm" style="height:38px; color:#DC2626; border-color:#EF4444; background:#ffffff; font-weight:700; padding:0.42rem 0.85rem; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;" title="Export catalog breakdown to PDF">
                📄 PDF Export
            </button>
        </div>
    </div>

    <!-- ==================== VIEW-ONLY CATALOG TABLE ==================== -->
    <div class="table-scroll-frame" style="max-height: 540px; overflow-y: auto; overflow-x: hidden; position: relative; background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
        <table id="catalog-overview-table" class="admin-table" style="width:100%; margin:0; border:none; border-radius:0; border-collapse:separate; border-spacing:0;">
            <thead>
                <tr style="border-bottom:2px solid var(--brand-border);">
                    <th style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); width:60px; text-align:center;">
                        IMAGE
                    </th>
                    <th onclick="sortCatalogTable('name', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1.25rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); cursor:pointer; user-select:none;" title="Click to sort by Item Name">
                        <div style="display:inline-flex; align-items:center; gap:6px;">
                            <span>UNIFORM ITEM</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortCatalogTable('rate', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1.25rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); text-align:right; width:150px; cursor:pointer; user-select:none;" title="Click to sort by Product Rate (Vendor Cost)">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>PRODUCT RATE</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortCatalogTable('commission', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1.25rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); text-align:right; width:170px; cursor:pointer; user-select:none;" title="Click to sort by Vendor Commission">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>VENDOR COMMISSION</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th onclick="sortCatalogTable('mrp', this)" style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1.25rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); text-align:right; width:150px; cursor:pointer; user-select:none;" title="Click to sort by Total MRP">
                        <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; width:100%;">
                            <span>TOTAL MRP</span>
                            <span class="sort-icon" style="font-size:0.85rem; color:#94A3B8;">↕</span>
                        </div>
                    </th>
                    <th style="position:sticky; top:0; z-index:20; background:var(--brand-surface-alt); padding:0.95rem 1rem; font-size:0.78rem; font-weight:800; color:var(--brand-dark); text-align:center; width:95px;">
                        ACTION
                    </th>
                </tr>
            </thead>
            <tbody id="catalog-table-body">
                <?php if (empty($catalogRows)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:3.5rem 1.5rem; color:var(--brand-muted);">
                            <div style="font-size:2.5rem; margin-bottom:0.5rem;">👕</div>
                            <div style="font-weight:700; font-size:1rem; color:#334155;">No catalog items found matching your filters.</div>
                            <div style="font-size:0.82rem; color:#94A3B8; margin-top:0.3rem;">Try selecting another school or clear your search filters.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($catalogRows as $row): ?>
                        <?php
                            $fullSchoolDisplay = !empty($row['parent_school_name']) 
                                ? $row['parent_school_name'] . ' • ' . $row['school_name'] 
                                : $row['school_name'];

                            $imgSrc = !empty($row['featured_image']) ? BASE_URL . '/' . ltrim($row['featured_image'], '/') : '';
                            
                            // JSON data for view-only modal
                            $isAccessory = ($row['uniform_group'] ?? '') === 'accessories' || preg_match('/\b(belt|socks?|cap|tie|bag|badge|bottle|mask)\b/i', $row['item_name']);
                            $sizesDisplay = $isAccessory ? '—' : (!empty($row['available_sizes']) ? $row['available_sizes'] : '—');
                            $modalData = htmlspecialchars(json_encode([
                                'name' => $row['item_name'],
                                'sku' => $row['sku'],
                                'category' => $row['category_name'],
                                'school' => $fullSchoolDisplay,
                                'product_rate' => number_format((float)$row['product_rate'], 2),
                                'vendor_commission' => number_format((float)$row['vendor_commission'], 2),
                                'total_mrp' => number_format((float)$row['total_mrp'], 2),
                                'sizes' => $sizesDisplay,
                                'description' => $row['description'] ?? $row['short_description'] ?? 'No additional description provided.',
                                'image' => $imgSrc,
                                'is_mandatory' => (bool)$row['is_mandatory'],
                                'is_kit' => (bool)$row['is_kit']
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="catalog-data-row"
                            data-name="<?= htmlspecialchars(strtolower($row['item_name'])) ?>"
                            data-rate="<?= (float)$row['product_rate'] ?>"
                            data-commission="<?= (float)$row['vendor_commission'] ?>"
                            data-mrp="<?= (float)$row['total_mrp'] ?>">
                            <!-- Image Thumbnail -->
                            <td style="padding:0.75rem 1rem; text-align:center; vertical-align:middle;">
                                <?php if (!empty($imgSrc)): ?>
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($row['item_name']) ?>" style="width:36px; height:36px; object-fit:contain; border-radius:6px; border:1px solid #E2E8F0; background:#FAFAFA;" onerror="this.onerror=null; this.src=''; this.parentNode.innerHTML='<span style=\'font-size:1.4rem;\'>👕</span>';">
                                <?php else: ?>
                                    <span style="font-size:1.4rem;">👕</span>
                                <?php endif; ?>
                            </td>

                            <!-- Uniform Item (Clean Name Only) -->
                            <td style="padding:0.75rem 1.25rem; vertical-align:middle;">
                                <div style="font-weight:700; color:#0F172A; font-size:0.9rem; line-height:1.3;">
                                    <?= htmlspecialchars($row['item_name']) ?>
                                </div>
                            </td>

                            <!-- Product Rate (Cost Price) -->
                            <td style="padding:0.75rem 1.25rem; text-align:right; vertical-align:middle; font-weight:700; color:#334155; font-size:0.9rem;">
                                ₹<?= number_format((float)$row['product_rate'], 2) ?>
                            </td>

                            <!-- Vendor Commission -->
                            <td style="padding:0.75rem 1.25rem; text-align:right; vertical-align:middle; font-weight:700; color:#059669; font-size:0.9rem;">
                                ₹<?= number_format((float)$row['vendor_commission'], 2) ?>
                            </td>

                            <!-- Total MRP (Selling Price) -->
                            <td style="padding:0.75rem 1.25rem; text-align:right; vertical-align:middle; font-weight:900; color:var(--brand-primary); font-size:0.98rem;">
                                ₹<?= number_format((float)$row['total_mrp'], 2) ?>
                            </td>

                            <!-- View Only Action Button (Icon Only) -->
                            <td style="padding:0.75rem 1rem; text-align:center; vertical-align:middle;">
                                <button type="button" 
                                        onclick="openCatalogItemModal(this)" 
                                        data-item='<?= $modalData ?>'
                                        class="btn btn-outline btn-sm" 
                                        style="width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center; color:#2563EB; border-color:#BFDBFE; background:#F8FAFC; border-radius:6px; cursor:pointer;"
                                        title="View Item Details">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($catalogRows)): ?>
                <tfoot>
                    <tr class="sticky-bottom-row" style="background:#FFF7ED; font-weight:800;">
                        <td colspan="2" style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; font-size:0.9rem; color:var(--brand-dark); text-transform:uppercase; letter-spacing:0.04em; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            GRAND TOTAL SUMMARY (<?= $totalItems ?> ITEMS)
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:#334155; font-size:0.98rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            ₹<?= number_format($totalProductRate, 2) ?>
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:#059669; font-size:0.98rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            ₹<?= number_format($totalCommission, 2) ?>
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1.25rem; text-align:right; color:var(--brand-primary); font-size:1.1rem; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">
                            <?= formatCurrency($totalMRP) ?>
                        </td>
                        <td style="position:sticky; bottom:0; z-index:20; background:#FFF7ED; padding:1.1rem 1rem; text-align:center; color:#94A3B8; border-top:2.5px solid var(--brand-primary); box-shadow:0 -2px 6px rgba(0,0,0,0.06);">-</td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>

</div>

<!-- ==================== VIEW-ONLY DETAILS MODAL POPUP ==================== -->
<div id="catalog-view-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:1.5rem;" onclick="closeCatalogItemModal(event)">
    <div style="background:#ffffff; border-radius:16px; max-width:620px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid #E2E8F0; position:relative;" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E2E8F0; display:flex; align-items:center; justify-content:space-between; background:#F8FAFC; border-radius:16px 16px 0 0;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.2rem;">👕</span>
                <div>
                    <h4 id="modal-item-name" style="margin:0; font-size:1.05rem; font-weight:800; color:#0F172A;">Uniform Item Details</h4>
                    <span id="modal-item-sku" style="font-size:0.75rem; color:#64748B; font-family:monospace;">SKU: -</span>
                </div>
            </div>
            <button type="button" onclick="closeCatalogItemModalDirect()" style="background:#E2E8F0; border:none; width:30px; height:30px; border-radius:50%; font-weight:900; color:#475569; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; padding:0; transition:background 0.15s;">✕</button>
        </div>

        <!-- Modal Body -->
        <div style="padding:1.5rem;">
            <!-- Top Card: Image + Overview -->
            <div style="display:flex; gap:1.25rem; align-items:flex-start; margin-bottom:1.25rem;">
                <div id="modal-img-container" style="width:110px; height:110px; flex-shrink:0; border-radius:12px; border:1px solid #E2E8F0; background:#FAFAFA; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                    <img id="modal-item-img" src="" alt="Uniform" style="width:100%; height:100%; object-fit:contain;">
                </div>
                <div style="flex-grow:1;">
                    <div style="margin-bottom:0.5rem;">
                        <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748B; letter-spacing:0.04em; display:block;">Assigned School</span>
                        <div id="modal-item-school" style="font-size:0.9rem; font-weight:700; color:#0F172A; margin-top:2px;">-</div>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <div>
                            <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748B; letter-spacing:0.04em; display:block;">Category</span>
                            <div id="modal-item-category" style="font-size:0.82rem; font-weight:600; color:#334155; margin-top:2px;">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Price Breakdown Grid: 3 Boxes -->
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.75rem; margin-bottom:1.25rem; background:#F8FAFC; padding:1rem; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="text-align:center; padding:0.5rem; background:#ffffff; border-radius:8px; border:1px solid #E2E8F0;">
                    <span style="font-size:0.7rem; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.04em; display:block;">Product Rate</span>
                    <div id="modal-product-rate" style="font-size:1.15rem; font-weight:900; color:#334155; margin-top:3px;">₹0.00</div>
                    <span style="font-size:0.68rem; color:#94A3B8;">Vendor Cost</span>
                </div>
                <div style="text-align:center; padding:0.5rem; background:#ffffff; border-radius:8px; border:1px solid #E2E8F0;">
                    <span style="font-size:0.7rem; font-weight:800; color:#059669; text-transform:uppercase; letter-spacing:0.04em; display:block;">Vendor Commission</span>
                    <div id="modal-vendor-commission" style="font-size:1.15rem; font-weight:900; color:#059669; margin-top:3px;">₹0.00</div>
                    <span style="font-size:0.68rem; color:#94A3B8;">Partner Margin</span>
                </div>
                <div style="text-align:center; padding:0.5rem; background:#FFF7ED; border-radius:8px; border:1px solid #FED7AA;">
                    <span style="font-size:0.7rem; font-weight:800; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.04em; display:block;">Total MRP</span>
                    <div id="modal-total-mrp" style="font-size:1.2rem; font-weight:900; color:var(--brand-primary); margin-top:3px;">₹0.00</div>
                    <span style="font-size:0.68rem; color:#EA580C;">Selling Price</span>
                </div>
            </div>

            <!-- Available Sizes -->
            <div style="margin-bottom:1.25rem;">
                <span style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:#64748B; letter-spacing:0.04em; display:block; margin-bottom:0.4rem;">Available Sizes / Variants</span>
                <div id="modal-item-sizes" style="font-size:0.83rem; color:#1E293B; background:#F1F5F9; padding:0.5rem 0.75rem; border-radius:8px; font-weight:600; line-height:1.4;">-</div>
            </div>

            <!-- Description -->
            <div>
                <span style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:#64748B; letter-spacing:0.04em; display:block; margin-bottom:0.4rem;">Item Description</span>
                <div id="modal-item-desc" style="font-size:0.82rem; color:#475569; background:#FAFAFA; padding:0.75rem; border-radius:8px; border:1px solid #E2E8F0; line-height:1.5;">-</div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding:1rem 1.5rem; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; background:#F8FAFC; border-radius:0 0 16px 16px;">
            <span style="font-size:0.72rem; font-weight:700; color:#64748B; display:inline-flex; align-items:center; gap:4px;">
                <span>🔒</span> Read-Only Catalog Overview (Changes disabled)
            </span>
            <button type="button" onclick="closeCatalogItemModalDirect()" class="btn btn-outline btn-sm" style="padding:0.4rem 1rem; font-size:0.8rem; font-weight:700; border-radius:6px;">Close</button>
        </div>
    </div>
</div>

<script>
function handleMainSchoolChange(mainSelect) {
    const subSelect = document.getElementById('co_sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}

// Modal View Handler
function openCatalogItemModal(btn) {
    const data = JSON.parse(btn.getAttribute('data-item') || '{}');
    document.getElementById('modal-item-name').textContent = data.name || 'Uniform Item';
    document.getElementById('modal-item-sku').textContent = 'SKU: ' + (data.sku || 'N/A');
    document.getElementById('modal-item-school').textContent = data.school || '-';
    document.getElementById('modal-item-category').textContent = data.category || '-';
    document.getElementById('modal-product-rate').textContent = '₹' + (data.product_rate || '0.00');
    document.getElementById('modal-vendor-commission').textContent = '₹' + (data.vendor_commission || '0.00');
    document.getElementById('modal-total-mrp').textContent = '₹' + (data.total_mrp || '0.00');
    document.getElementById('modal-item-sizes').textContent = data.sizes || 'N/A';
    document.getElementById('modal-item-desc').textContent = data.description || 'No additional description.';

    const imgEl = document.getElementById('modal-item-img');
    const imgContainer = document.getElementById('modal-img-container');
    if (data.image) {
        imgEl.src = data.image;
        imgEl.style.display = 'block';
    } else {
        imgEl.style.display = 'none';
        imgContainer.innerHTML = '<span style="font-size:2.5rem;">👕</span>';
    }

    const modal = document.getElementById('catalog-view-modal');
    modal.style.display = 'flex';
}

function closeCatalogItemModalDirect() {
    const modal = document.getElementById('catalog-view-modal');
    if (modal) modal.style.display = 'none';
}

function closeCatalogItemModal(e) {
    if (e.target.id === 'catalog-view-modal') {
        closeCatalogItemModalDirect();
    }
}

// Client-side Column Sorting
let currentSortColumn = 'name';
let currentSortDirection = 'asc';

function sortCatalogTable(column, thElement) {
    const tbody = document.getElementById('catalog-table-body');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr.catalog-data-row'));
    if (rows.length === 0) return;

    if (currentSortColumn === column) {
        currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
    } else {
        currentSortColumn = column;
        currentSortDirection = (column === 'rate' || column === 'commission' || column === 'mrp') ? 'desc' : 'asc';
    }

    // Update sort icons
    document.querySelectorAll('#catalog-overview-table thead .sort-icon').forEach(icon => {
        icon.textContent = '↕';
        icon.style.color = '#94A3B8';
    });

    const activeIcon = thElement.querySelector('.sort-icon');
    if (activeIcon) {
        activeIcon.textContent = (currentSortDirection === 'asc') ? '▲' : '▼';
        activeIcon.style.color = 'var(--brand-primary)';
    }

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

    rows.forEach(r => tbody.appendChild(r));
}
</script>

<!-- jsPDF & AutoTable for client-side vector PDF export (local bundled scripts) -->
<script src="<?= BASE_URL ?>/assets/js/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/jspdf.plugin.autotable.min.js"></script>
<script>
<?php
$catalogJsRows = [];
foreach ($catalogRows as $idx => $r) {
    $catalogJsRows[] = [
        (string)($idx + 1),
        (string)$r['item_name'],
        (string)($r['school_name'] ?? ''),
        'Rs. ' . number_format((float)$r['product_rate'], 2),
        'Rs. ' . number_format((float)$r['vendor_commission'], 2),
        'Rs. ' . number_format((float)$r['total_mrp'], 2)
    ];
}
?>
window.CATALOG_REPORT_DATA = <?= json_encode($catalogJsRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.CATALOG_REPORT_META = {
    school: <?= json_encode($activeSchoolDisplay) ?>,
    totalItems: <?= (int)$totalItems ?>,
    totalRate: <?= json_encode('Rs. ' . number_format($totalProductRate, 2)) ?>,
    totalCommission: <?= json_encode('Rs. ' . number_format($totalCommission, 2)) ?>,
    totalMRP: <?= json_encode('Rs. ' . number_format($totalMRP, 2)) ?>,
    date: <?= json_encode(date('d M Y, h:i A')) ?>
};

function exportCatalogPdf() {
    if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
        window.print();
        return;
    }
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Top Brand Orange Bar
        doc.setFillColor(255, 103, 2);
        doc.rect(0, 0, 210, 18, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(13);
        doc.text('ONBOLT COMPLETE BRANDING SOLUTIONS', 14, 11);

        doc.setFontSize(8);
        doc.setFont('helvetica', 'normal');
        doc.text('Uniform Catalog Overview (View-Only Breakdown)', 14, 15.5);

        // Subtitle / Scope
        doc.setTextColor(15, 23, 42);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text(`School Scope: ${window.CATALOG_REPORT_META.school}`, 14, 25);

        const summaryText = `Total Items: ${window.CATALOG_REPORT_META.totalItems}  |  Product Rate: ${window.CATALOG_REPORT_META.totalRate}  |  Commission: ${window.CATALOG_REPORT_META.totalCommission}  |  Total MRP: ${window.CATALOG_REPORT_META.totalMRP}`;
        doc.setFontSize(7.5);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100, 116, 139);
        doc.text(summaryText, 14, 29.5);

        const head = [['#', 'Item Name', 'School Name', 'Product Rate', 'Commission', 'Total MRP']];
        const body = window.CATALOG_REPORT_DATA || [];
        const foot = [['', 'CATALOG TOTAL SUMMARY', '', window.CATALOG_REPORT_META.totalRate, window.CATALOG_REPORT_META.totalCommission, window.CATALOG_REPORT_META.totalMRP]];

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
                cellPadding: 2
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
                1: { cellWidth: 52 },
                2: { cellWidth: 42 },
                3: { cellWidth: 26, halign: 'right' },
                4: { cellWidth: 26, halign: 'right', fontStyle: 'bold', textColor: [16, 185, 129] },
                5: { cellWidth: 26, halign: 'right', fontStyle: 'bold' }
            },
            margin: { left: 14, right: 14, top: 33, bottom: 12 },
            didDrawPage: function(data) {
                const str = 'Page ' + doc.internal.getNumberOfPages();
                doc.setFontSize(7.5);
                doc.setTextColor(148, 163, 184);
                doc.text(str, data.settings.margin.left, 287);
                doc.text('OnBolt Digital Platform - Official Institutional Records', 130, 287);
            }
        });

        const safeSchool = (window.CATALOG_REPORT_META.school || 'All').replace(/[^a-zA-Z0-9]/g, '_');
        doc.save(`OnBolt_Catalog_Overview_${safeSchool}_${new Date().toISOString().slice(0, 10)}.pdf`);
    } catch (e) {
        console.error('PDF export error:', e);
        window.print();
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
