<?php
/**
 * OnBolt Digital Experience Platform - System Settings
 * URL: /admin/settings.php
 */

declare(strict_types=1);

$adminActive = 'settings';
$adminTitle = 'System & Application Configuration';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'whatsapp_order_phone', 'office_address', 'orders_notification_email'];
    try {
        $stmt = $pdo->prepare("INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                $stmt->execute([$k, trim((string)$_POST[$k])]);
            }
        }
        $msg = 'System settings updated successfully!';
        logActivity('update_settings', 'settings', (int)$admin['id'], 'admin', "Updated platform settings");
    } catch (Exception $e) {
        $err = 'Error: ' . $e->getMessage();
    }
}

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM website_settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
?>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm); max-width:850px;">
    <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.5rem;">Core Platform Settings</h3>

    <form action="" method="POST">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">Platform Title</label>
                <input type="text" name="site_name" value="<?= sanitize($settings['site_name'] ?? 'OnBolt') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Platform Tagline</label>
                <input type="text" name="site_tagline" value="<?= sanitize($settings['site_tagline'] ?? '') ?>" class="form-control">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">Customer Support Phone</label>
                <input type="tel" name="contact_phone" value="<?= sanitize($settings['contact_phone'] ?? '+91 9946925888') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">WhatsApp Order Desk (Format: 91XXXXXXXXXX)</label>
                <input type="text" name="whatsapp_order_phone" value="<?= sanitize($settings['whatsapp_order_phone'] ?? '918848670739') ?>" class="form-control">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">General Contact Email</label>
                <input type="email" name="contact_email" value="<?= sanitize($settings['contact_email'] ?? 'onboltconsumerindia@gmail.com') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Internal Order Notifications Email</label>
                <input type="email" name="orders_notification_email" value="<?= sanitize($settings['orders_notification_email'] ?? 'orders@onbolt.in') ?>" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Manufacturing Facility &amp; Registered Office</label>
            <textarea name="office_address" class="form-control" style="min-height:90px;"><?= sanitize($settings['office_address'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:1rem;">
            Save Platform Settings
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
