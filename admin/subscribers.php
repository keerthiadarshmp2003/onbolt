<?php
/**
 * OnBolt Digital Experience Platform - Newsletter Subscribers
 * URL: /admin/subscribers.php
 */

declare(strict_types=1);

$adminActive = 'subscribers';
$adminTitle = 'Newsletter Subscriber Directory';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$subscribers = $pdo->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC")->fetchAll();
?>

<div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm); max-width:800px;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--brand-border); font-weight:800;">
        Registered Subscribers (<?= count($subscribers) ?>)
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Subscriber Email</th>
                <th>Subscribed Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subscribers as $sub): ?>
                <tr>
                    <td style="font-weight:700; color:var(--brand-muted);">#<?= $sub['id'] ?></td>
                    <td style="font-weight:800; color:var(--brand-dark);"><?= sanitize($sub['email']) ?></td>
                    <td style="font-size:0.85rem; color:var(--brand-muted);"><?= date('d M Y, h:i A', strtotime($sub['created_at'])) ?></td>
                    <td><span style="color:#15803d; font-weight:700; font-size:0.8rem;">● Active</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
