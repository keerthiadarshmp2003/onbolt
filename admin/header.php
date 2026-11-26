<?php
/**
 * OnBolt Digital Experience Platform - Admin Header
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
$adminName = sanitize($admin['full_name']);
$adminRole = sanitize($admin['role_name']);

$siteLogo = BASE_URL . '/assets/images/logo/onbolt-actual-logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($adminTitle) ? sanitize($adminTitle) . ' | OnBolt Control Center' : 'OnBolt Admin Dashboard' ?></title>
    
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/logo/cropped-favicon-1-96x96.png" type="image/png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=3.0">
    
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #F8FAFC;
            max-width: 100vw;
            position: relative;
        }
        .admin-sidebar {
            background: #0B111E;
            color: #ffffff;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.08);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
            z-index: 100;
            box-sizing: border-box;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            color: #94A3B8;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
            margin-bottom: 0.25rem;
        }
        .admin-nav-item:hover, .admin-nav-item.active {
            background: rgba(255, 103, 2, 0.15);
            color: #ffffff;
        }
        .admin-nav-item.active {
            border-left: 3px solid var(--brand-primary);
            color: var(--brand-primary);
            font-weight: 700;
        }
        .admin-nav-group-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748B;
            padding: 1.25rem 1rem 0.5rem;
            font-weight: 800;
        }
        .admin-main {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-width: 0;
            padding: 2rem 2.5rem;
            box-sizing: border-box;
            background: #F8FAFC;
            min-height: 100vh;
        }

        /* Global Custom Scrollbars for Frames & Containers */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #F1F5F9;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        /* Table Scroll Frame - All tables scroll inside themselves with scroll bar */
        .table-scroll-frame {
            max-height: 520px;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
            border-radius: var(--radius-xl);
            border: 1px solid var(--brand-border);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }
        .table-scroll-frame .admin-table {
            overflow: visible !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            border: none;
            border-radius: 0;
            margin: 0;
        }
        .table-scroll-frame thead th {
            position: sticky !important;
            top: 0 !important;
            z-index: 20 !important;
            background: var(--brand-surface-alt) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06) !important;
        }
        .table-scroll-frame .sticky-bottom-row td,
        .table-scroll-frame tfoot td {
            position: sticky !important;
            bottom: 0 !important;
            z-index: 20 !important;
            background: #FFF7ED !important;
            box-shadow: 0 -2px 6px rgba(0,0,0,0.06) !important;
        }
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--brand-border);
        }
        .admin-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--brand-border);
            box-shadow: var(--shadow-sm);
        }
        .admin-table th {
            background: var(--brand-surface-alt);
            padding: 1rem 1.25rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-muted);
            text-align: left;
            border-bottom: 1px solid var(--brand-border);
        }
        .admin-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--brand-border);
            font-size: 0.9rem;
            vertical-align: middle;
        }
        .admin-table tr:hover td {
            background: #fbfcfe;
        }
        .stat-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--brand-border);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
        }
        .stat-num {
            font-family: var(--font-display);
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--brand-primary);
            line-height: 1;
            margin-top: 0.5rem;
        }
        .stat-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--brand-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <div style="padding: 0.5rem 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; background: #ffffff; border-radius: var(--radius-md);">
            <a href="<?= BASE_URL ?>/admin/">
                <img src="<?= $siteLogo ?>" alt="OnBolt" style="height: 36px; width: auto; object-fit: contain;">
            </a>
            <div style="font-size: 0.68rem; color: var(--brand-primary); font-weight: 800; margin-top: 0.4rem; letter-spacing: 0.08em; text-transform: uppercase;">
                Complete Branding Solutions
            </div>
        </div>

        <div class="admin-nav-group-title">Core Management</div>
        <?php if (adminCan('dashboard_view')): ?>
        <a href="<?= BASE_URL ?>/admin/" class="admin-nav-item <?= empty($adminActive) || $adminActive === 'dashboard' ? 'active' : '' ?>">
            Dashboard
        </a>
        <?php endif; ?>

        <?php if (adminCan('orders_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="admin-nav-item <?= ($adminActive ?? '') === 'orders' ? 'active' : '' ?>">
            Orders
        </a>
        <?php endif; ?>

        <?php if (adminCan('pending_transactions_manage')): ?>
        <?php
        $headerPdo = getDbConnection();
        $navPendingCount = 0;
        try {
            $navPendingCount = (int)$headerPdo->query("SELECT COUNT(*) FROM orders WHERE (order_reference IS NULL OR order_reference = '') AND payment_status IN ('Pending', 'Failed')")->fetchColumn();
        } catch (Throwable $e) {}
        ?>
        <a href="<?= BASE_URL ?>/admin/pending_transactions.php" class="admin-nav-item <?= ($adminActive ?? '') === 'pending_transactions' ? 'active' : '' ?>">
            Issues
            <?php if ($navPendingCount > 0): ?>
                <span style="margin-left: auto; background: #EF4444; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 2px 7px; border-radius: 9999px; line-height: 1.2;"><?= $navPendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (adminCan('create_order_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/create_order.php" class="admin-nav-item <?= ($adminActive ?? '') === 'create_order' ? 'active' : '' ?>">
            New Order
        </a>
        <?php endif; ?>

        <?php if (adminCan('partner_percentage_view')): ?>
        <a href="<?= BASE_URL ?>/admin/partner_percentage.php" class="admin-nav-item <?= ($adminActive ?? '') === 'partner_percentage' ? 'active' : '' ?>">
            Partner Percentage
        </a>
        <?php endif; ?>

        <?php if (adminCan('schools_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/schools.php" class="admin-nav-item <?= ($adminActive ?? '') === 'schools' ? 'active' : '' ?>">
            Schools
        </a>
        <?php endif; ?>

        <?php if (adminCan('products_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/products.php" class="admin-nav-item <?= ($adminActive ?? '') === 'products' ? 'active' : '' ?>">
            Products
        </a>
        <?php endif; ?>

        <?php if (adminCan('catalog_overview_view')): ?>
        <a href="<?= BASE_URL ?>/admin/catalog_overview.php" class="admin-nav-item <?= ($adminActive ?? '') === 'catalog_overview' ? 'active' : '' ?>">
            Catalog
        </a>
        <?php endif; ?>

        <?php if (($admin['role_slug'] ?? '') === 'superadmin' || ($admin['role_id'] ?? 0) === 1 || adminCan('users_manage') || adminCan('privileges_manage')): ?>
        <div class="admin-nav-group-title">Admin Settings</div>
        <?php if (($admin['role_slug'] ?? '') === 'superadmin' || ($admin['role_id'] ?? 0) === 1 || adminCan('users_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/users.php" class="admin-nav-item <?= ($adminActive ?? '') === 'users' ? 'active' : '' ?>">
            User Management
        </a>
        <?php endif; ?>
        <?php if (($admin['role_slug'] ?? '') === 'superadmin' || ($admin['role_id'] ?? 0) === 1 || adminCan('privileges_manage')): ?>
        <a href="<?= BASE_URL ?>/admin/privileges.php" class="admin-nav-item <?= ($adminActive ?? '') === 'privileges' ? 'active' : '' ?>">
            Privilege Settings
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1);">
            <div style="font-size: 0.85rem; font-weight: 700; color: #ffffff;"><?= $adminName ?></div>
            <div style="font-size: 0.75rem; color: var(--brand-primary); margin-bottom: 0.75rem;"><?= $adminRole ?></div>
            <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-outline btn-sm" style="width: 100%; color: #fff; background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); font-size: 0.8rem; padding: 0.4rem;">
                Sign Out
            </a>
        </div>
    </aside>

    <!-- Content Area -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <span class="text-eyebrow" style="font-size: 0.72rem; padding: 0.2rem 0.6rem;">Administration Desk</span>
                <h2 class="heading-1" style="margin-top: 0.25rem; font-size: 1.7rem;"><?= $adminTitle ?? 'Operations Command' ?></h2>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center;">
                <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-outline btn-sm">
                    <span>View Public Website</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
            </div>
        </div>
