<?php
/**
 * OnBolt Digital Experience Platform - Admin Dashboard Overview
 * URL: /admin/
 */

declare(strict_types=1);

$adminActive = 'dashboard';
$adminTitle = 'Operations & Parent Purchases Overview';
require_once __DIR__ . '/header.php';

if (!adminCan('dashboard_view')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access the Dashboard.');
}

$pdo = getDbConnection();

$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);

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

// Compute Stat Metrics (scoped to school/sub-school filter if applied)
$schoolCondition = "";
if ($subSchoolFilter > 0) {
    $schoolCondition = " WHERE school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $schoolCondition = " WHERE (school_id = {$schoolFilter} OR school_id IN (SELECT id FROM schools WHERE parent_id = {$schoolFilter}))";
}

$orderSchoolCondition = "";
if ($subSchoolFilter > 0) {
    $orderSchoolCondition = " AND school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $orderSchoolCondition = " AND (school_id = {$schoolFilter} OR school_id IN (SELECT id FROM schools WHERE parent_id = {$schoolFilter}))";
}

$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE parent_id IS NULL OR parent_id = 0")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders" . ($schoolCondition ?: ""))->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'" . $orderSchoolCondition)->fetchColumn();
$inProgressOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('In progress', 'Production')" . $orderSchoolCondition)->fetchColumn();
$deliveredOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Delivered', 'Dispatched', 'Dispatched by hand')" . $orderSchoolCondition)->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status NOT IN ('Cancelled', 'Refunded')" . $orderSchoolCondition)->fetchColumn();

// Recent Parent Purchases
$allowedStatuses = ['Pending', 'Confirmed', 'In progress', 'Packed', 'Dispatched', 'Dispatched by hand', 'Delivered', 'Cancelled', 'Refunded'];
$recentOrdersSql = "SELECT o.*, s.name as school_name 
                    FROM orders o 
                    LEFT JOIN schools s ON o.school_id = s.id 
                    WHERE 1=1";
if ($subSchoolFilter > 0) {
    $recentOrdersSql .= " AND o.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $recentOrdersSql .= " AND (o.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}
$recentOrdersSql .= " ORDER BY o.id DESC LIMIT 6";
$recentOrders = $pdo->query($recentOrdersSql)->fetchAll();
?>

<!-- KPI Stat Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="stat-card">
        <span class="stat-label">Total Purchasing Value</span>
        <div class="stat-num" style="color:var(--brand-primary);"><?= formatCurrency($totalRevenue) ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">Across all partner school orders</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Parent Purchases</span>
        <div class="stat-num"><?= $totalOrders ?></div>
        <div style="font-size:0.75rem; color:#b45309; margin-top:0.4rem; font-weight:700;"><?= $pendingOrders ?> Pending Verification</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Partner Schools</span>
        <div class="stat-num"><?= $totalSchools ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">With uploaded logos &amp; banners</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Uniform Catalog</span>
        <div class="stat-num"><?= $totalProducts ?></div>
        <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:0.4rem;">Tailored school garments &amp; kits</div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:2.5rem;">
    <a href="<?= BASE_URL ?>/admin/orders.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:50px; height:50px; border-radius:50%; background:rgba(255,103,2,0.1); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;">
            📦
        </div>
        <div>
            <div style="font-weight:800; font-size:1.05rem; color:var(--brand-dark);">Manage Purchases &amp; Messages</div>
            <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.2rem;">View items, order status, and send WhatsApp/SMS updates</div>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/create_order.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1.5px solid #FED7AA; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:50px; height:50px; border-radius:50%; background:rgba(255,103,2,0.12); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;">
            🛍️
        </div>
        <div>
            <div style="font-weight:800; font-size:1.05rem; color:var(--brand-dark);">Create Walk-in Order</div>
            <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.2rem;">Counter desk for parents ordering or paying in person</div>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/schools.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:50px; height:50px; border-radius:50%; background:rgba(37,99,235,0.1); color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;">
            🏫
        </div>
        <div>
            <div style="font-weight:800; font-size:1.05rem; color:var(--brand-dark);">Upload Partner School</div>
            <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.2rem;">Register school with logo &amp; banner (Max 500 KB limit)</div>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/products.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:50px; height:50px; border-radius:50%; background:rgba(22,163,74,0.1); color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;">
            👕
        </div>
        <div>
            <div style="font-weight:800; font-size:1.05rem; color:var(--brand-dark);">Upload Uniform Product</div>
            <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.2rem;">Add uniforms, sizes, and photography (Max 500 KB limit)</div>
        </div>
    </a>
</div>

<!-- Recent Parent Purchases Table -->
<div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h3 class="heading-2" style="font-size:1.3rem; margin:0;">Recent Parent Purchasing Orders</h3>
            <p style="font-size:0.82rem; color:var(--brand-muted); margin:0.2rem 0 0 0;">Latest uniform orders placed by parents across schools.</p>
        </div>
        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            <form action="" method="GET" style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; margin:0;">
                <!-- Main Schools Filter -->
                <select name="school_id" id="dash_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:140px;" onchange="handleMainSchoolDashChange(this)">
                    <option value="">Select School</option>
                    <?php foreach ($mainSchools as $s): ?>
                        <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= sanitize($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Under Schools Filter -->
                <select name="sub_school_id" id="dash_sub_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:145px;" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?>>
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

                <?php if ($schoolFilter > 0 || $subSchoolFilter > 0): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">Reset</a>
                <?php endif; ?>
            </form>
            <a href="<?= BASE_URL ?>/admin/orders.php<?= $schoolFilter > 0 ? '?school_id=' . $schoolFilter : '' ?><?= $subSchoolFilter > 0 ? ($schoolFilter > 0 ? '&' : '?') . 'sub_school_id=' . $subSchoolFilter : '' ?>" class="btn btn-outline btn-sm">View All Orders &rarr;</a>
        </div>
    </div>

    <div class="table-scroll-frame" style="max-height:520px; overflow-y:auto; overflow-x:auto; position:relative; border-radius:0; border:none; border-top:1px solid #F1F5F9;">
        <table class="admin-table" style="margin:0; border:none; border-radius:0;">
            <thead>
                <tr>
                    <th style="white-space:nowrap; width:140px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Order Ref</th>
                    <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">School</th>
                    <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Student Name</th>
                    <th style="position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Parent &amp; Phone</th>
                    <th style="text-align:center; white-space:nowrap; width:90px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Items</th>
                    <th style="text-align:right; white-space:nowrap; width:130px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Total Amount</th>
                    <th style="text-align:center; min-width:140px; white-space:nowrap; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Status</th>
                    <th style="text-align:right; white-space:nowrap; width:170px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:2rem; color:var(--brand-muted);">No purchasing orders recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ro): ?>
                        <tr>
                            <td style="font-weight:800; color:var(--brand-primary); white-space:nowrap;"><?= sanitize($ro['order_reference']) ?></td>
                            <td style="font-weight:600;"><?= sanitize($ro['school_name'] ?: 'International School') ?></td>
                            <td>
                                <div style="font-weight:700;"><?= sanitize($ro['student_name']) ?></div>
                                <div style="font-size:0.78rem; color:var(--brand-muted);"><?= sanitize($ro['grade_class']) ?></div>
                            </td>
                            <td>
                                <div><?= sanitize($ro['parent_name']) ?></div>
                                <div style="font-size:0.8rem; color:var(--brand-muted); font-weight:600;"><?= sanitize($ro['mobile']) ?></div>
                            </td>
                            <td style="font-weight:700; text-align:center; white-space:nowrap;"><?= $ro['total_items'] ?> pcs</td>
                            <td style="font-weight:800; text-align:right; white-space:nowrap;"><?= formatCurrency($ro['total_amount']) ?></td>
                            <td style="text-align:center; white-space:nowrap;">
                                <!-- Single Order Status Dropdown (Update status each by each) -->
                                <div class="quick-status-wrap" style="position:relative; display:inline-flex; align-items:center;">
                                    <select class="order-status-quick-select" 
                                            id="dash-order-status-<?= $ro['id'] ?>"
                                            data-order-id="<?= $ro['id'] ?>" 
                                            data-current="<?= htmlspecialchars($ro['order_status']) ?>"
                                            onchange="changeDashOrderStatus(<?= $ro['id'] ?>, this.value, this)" 
                                            style="padding:0.32rem 1.6rem 0.32rem 0.75rem; font-size:0.75rem; font-weight:800; border-radius:9999px; cursor:pointer; width:auto; text-transform:uppercase; letter-spacing:0.03em; appearance:none; -webkit-appearance:none; border-width:1.5px; border-style:solid; outline:none; transition:all 0.2s; <?= getOrderStatusSelectStyle($ro['order_status']) ?>">
                                        <?php foreach ($allowedStatuses as $st): ?>
                                            <option value="<?= $st ?>" <?= $ro['order_status'] === $st ? 'selected' : '' ?> style="background:#ffffff; color:#0F172A; font-weight:700;">
                                                <?= $st === 'Production' ? 'In Production' : $st ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <svg style="position:absolute; right:8px; pointer-events:none; width:11px; height:11px; opacity:0.7;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <div style="display:inline-flex; gap:0.4rem; justify-content:flex-end; align-items:center;">
                                    <a href="<?= BASE_URL ?>/admin/orders.php?view=<?= $ro['id'] ?>" class="btn btn-outline btn-sm" style="padding:0.35rem 0.65rem; font-size:0.75rem; font-weight:700;">
                                        Inspect
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/orders.php?view=<?= $ro['id'] ?>#message" class="btn btn-primary btn-sm" style="padding:0.35rem 0.65rem; font-size:0.75rem; font-weight:700;">
                                        Message
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function handleMainSchoolDashChange(mainSelect) {
    const subSelect = document.getElementById('dash_sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}

const statusStyleMap = {
    'Pending': { bg: '#FEF3C7', text: '#B45309', border: '#FDE68A' },
    'Confirmed': { bg: '#DBEAFE', text: '#1D4ED8', border: '#BFDBFE' },
    'In progress': { bg: '#F3E8FF', text: '#7E22CE', border: '#D8B4FE' },
    'Production': { bg: '#F3E8FF', text: '#7E22CE', border: '#D8B4FE' },
    'Packed': { bg: '#FFEDD5', text: '#C2410C', border: '#FDBA74' },
    'Dispatched': { bg: '#E0F2FE', text: '#0369A1', border: '#7DD3FC' },
    'Dispatched by hand': { bg: '#E0E7FF', text: '#4338CA', border: '#A5B4FC' },
    'On the way': { bg: '#E0E7FF', text: '#4338CA', border: '#A5B4FC' },
    'Delivered': { bg: '#DCFCE7', text: '#15803D', border: '#86EFAC' },
    'Cancelled': { bg: '#FEE2E2', text: '#DC2626', border: '#FCA5A5' },
    'Refunded': { bg: '#FAF5FF', text: '#9333EA', border: '#E9D5FF' }
};

function applySelectColors(selectEl, status) {
    const s = statusStyleMap[status] || { bg: '#F1F5F9', text: '#475569', border: '#CBD5E1' };
    selectEl.style.backgroundColor = s.bg;
    selectEl.style.color = s.text;
    selectEl.style.borderColor = s.border;
}

function changeDashOrderStatus(orderId, newStatus, selectEl) {
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
            showDashToast(data.message || `Order status updated to ${newStatus}!`, 'success');
        } else {
            selectEl.value = prevStatus;
            applySelectColors(selectEl, prevStatus);
            showDashToast(data.message || 'Failed to update order status', 'error');
        }
    })
    .catch(err => {
        selectEl.style.opacity = '1';
        selectEl.disabled = false;
        selectEl.value = prevStatus;
        applySelectColors(selectEl, prevStatus);
        showDashToast('Network error while updating status', 'error');
    });
}

function showDashToast(msg, type = 'success') {
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
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
