<?php
/**
 * OnBolt Digital Experience Platform - Activity & Audit Logs
 * URL: /admin/activity_logs.php
 */

declare(strict_types=1);

$adminActive = 'activity_logs';
$adminTitle = 'System & Security Audit Trail';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$logs = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50")->fetchAll();
?>

<div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--brand-border); display:flex; justify-content:space-between; align-items:center;">
        <span style="font-weight:800;">Recent Security &amp; Operational Events</span>
        <span style="font-size:0.85rem; color:var(--brand-muted);">Displaying latest 50 logged transactions</span>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User Type &amp; ID</th>
                <th>Module</th>
                <th>Action Performed</th>
                <th>Event Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td style="font-size:0.8rem; color:var(--brand-muted); white-space:nowrap;"><?= date('d M Y, h:i:s A', strtotime($l['created_at'])) ?></td>
                    <td>
                        <span style="font-weight:700; text-transform:uppercase; font-size:0.75rem; background:var(--brand-surface-alt); padding:2px 6px; border-radius:3px;"><?= sanitize($l['user_type']) ?></span>
                        <span style="font-size:0.8rem; color:var(--brand-muted);">#<?= $l['user_id'] ?: 'Guest' ?></span>
                    </td>
                    <td style="font-weight:700; color:var(--brand-primary);"><?= sanitize($l['module']) ?></td>
                    <td style="font-weight:800; font-size:0.88rem;"><?= sanitize($l['action']) ?></td>
                    <td style="font-size:0.85rem; color:var(--brand-dark-soft);"><?= sanitize($l['details']) ?></td>
                    <td style="font-size:0.8rem; color:var(--brand-muted); font-family:monospace;"><?= sanitize($l['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
