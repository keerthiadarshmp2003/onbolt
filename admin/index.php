<?php
/**
 * OnBolt Digital Experience Platform - Admin Dashboard Overview
 * URL: /admin/
 */

declare(strict_types=1);

$adminActive = 'dashboard';
$adminTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

if (!adminCan('dashboard_view')) {
    http_response_code(403);
    die('Access Denied: You do not have permission to access the Dashboard.');
}

$pdo = getDbConnection();

// Compute Stat Metrics
$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE parent_id IS NULL OR parent_id = 0")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();
$inProgressOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('In progress', 'Production')")->fetchColumn();
$deliveredOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Delivered', 'Dispatched', 'Dispatched by hand')")->fetchColumn();
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status NOT IN ('Cancelled', 'Refunded')")->fetchColumn();
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
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1rem;">
    <a href="<?= BASE_URL ?>/admin/orders.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(255,103,2,0.1); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            📦
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Orders</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/create_order.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1.5px solid #FED7AA; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(255,103,2,0.12); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            🛍️
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">New Order</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/schools.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(37,99,235,0.1); color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            🏫
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Schools</div>
    </a>

    <a href="<?= BASE_URL ?>/admin/products.php" style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem; text-decoration:none; color:inherit; transition:transform 0.2s ease;">
        <div style="width:48px; height:48px; border-radius:50%; background:rgba(22,163,74,0.1); color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
            👕
        </div>
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark);">Products</div>
    </a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
