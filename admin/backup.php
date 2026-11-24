<?php
/**
 * OnBolt Digital Experience Platform - Database Backup & Restoration
 * URL: /admin/backup.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth(1); // Super Admin Only

if (isset($_GET['download']) && $_GET['download'] === '1') {
    $sqlFile = ROOT_PATH . '/database/onbolt.sql';
    if (file_exists($sqlFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="onbolt_backup_' . date('Y-m-d_His') . '.sql"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($sqlFile));
        readfile($sqlFile);
        exit;
    }
}

$adminActive = 'backup';
$adminTitle = 'Relational Database Backup & Disaster Recovery';
require_once __DIR__ . '/header.php';

$sqlFile = ROOT_PATH . '/database/onbolt.sql';
$size = file_exists($sqlFile) ? round(filesize($sqlFile) / 1024, 2) . ' KB' : 'Not generated';
$lastModified = file_exists($sqlFile) ? date('d M Y, h:i A', filemtime($sqlFile)) : 'N/A';
?>

<div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm); max-width:800px;">
    <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1rem;">Production MySQL Database Snapshot</h3>
    <p style="color:var(--brand-muted); line-height:1.6; margin-bottom:2rem;">
        Generate and securely download full relational schema and verified business data snapshots compatible with phpMyAdmin, MySQL Workbench, and CLI MariaDB/MySQL.
    </p>

    <div style="background:var(--brand-surface-alt); border-radius:var(--radius-lg); padding:1.75rem; border:1px solid var(--brand-border); margin-bottom:2rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; font-size:0.95rem;">
            <span style="color:var(--brand-muted);">Active Database:</span>
            <strong style="color:var(--brand-primary); font-family:monospace;"><?= DB_NAME ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; font-size:0.95rem;">
            <span style="color:var(--brand-muted);">Backup Archive File:</span>
            <strong>database/onbolt.sql</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; font-size:0.95rem;">
            <span style="color:var(--brand-muted);">Archive Size:</span>
            <strong><?= $size ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.95rem;">
            <span style="color:var(--brand-muted);">Last Snapshot Time:</span>
            <strong><?= $lastModified ?></strong>
        </div>
    </div>

    <div style="display:flex; gap:1rem;">
        <a href="<?= BASE_URL ?>/admin/backup.php?download=1" class="btn btn-primary btn-lg">
            <span>Download SQL Backup Archive</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
