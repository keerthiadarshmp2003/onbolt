<?php
/**
 * OnBolt Digital Experience Platform - Website Content Management (CMS)
 * URL: /admin/content.php
 */

declare(strict_types=1);

$adminActive = 'content';
$adminTitle = 'Website Dynamic CMS & Sections';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settingsToUpdate = [
        'site_name'               => $_POST['site_name'] ?? 'OnBolt',
        'site_tagline'            => $_POST['site_tagline'] ?? '',
        'contact_email'           => $_POST['contact_email'] ?? '',
        'contact_phone'           => $_POST['contact_phone'] ?? '',
        'whatsapp_order_phone'    => $_POST['whatsapp_order_phone'] ?? '',
        'office_address'          => $_POST['office_address'] ?? '',
        'currency_symbol'         => $_POST['currency_symbol'] ?? '₹',
    ];

    try {
        $uStmt = $pdo->prepare("INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settingsToUpdate as $k => $v) {
            $uStmt->execute([$k, trim((string)$v)]);
        }
        $msg = 'Website content and configuration saved successfully!';
        logActivity('update_cms', 'cms', (int)$admin['id'], 'admin', "Updated website settings and CMS content");
    } catch (Exception $e) {
        $err = 'Error saving settings: ' . $e->getMessage();
    }
}

// Fetch all current settings
$settings = [];
$sRows = $pdo->query("SELECT setting_key, setting_value FROM website_settings")->fetchAll();
foreach ($sRows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
?>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ⚠️ <?= sanitize($err) ?>
    </div>
<?php endif; ?>

<div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm); max-width:920px;">
    <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.5rem;">Corporate Information &amp; Header Settings</h3>

    <form action="" method="POST">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">Brand Name</label>
                <input type="text" name="site_name" value="<?= sanitize($settings['site_name'] ?? 'OnBolt') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Brand Tagline</label>
                <input type="text" name="site_tagline" value="<?= sanitize($settings['site_tagline'] ?? 'Custom School Uniforms, Branding & Corporate Gifting') ?>" class="form-control">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">Official Contact Email</label>
                <input type="email" name="contact_email" value="<?= sanitize($settings['contact_email'] ?? 'onboltconsumerindia@gmail.com') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Customer Support Phone</label>
                <input type="tel" name="contact_phone" value="<?= sanitize($settings['contact_phone'] ?? '+91 9946925888') ?>" class="form-control">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <div class="form-group">
                <label class="form-label">WhatsApp Order Desk Number (International Format)</label>
                <input type="text" name="whatsapp_order_phone" value="<?= sanitize($settings['whatsapp_order_phone'] ?? '918848670739') ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Active Currency Symbol</label>
                <input type="text" name="currency_symbol" value="<?= sanitize($settings['currency_symbol'] ?? '₹') ?>" class="form-control" style="width:80px;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Corporate Office Address</label>
            <textarea name="office_address" class="form-control" style="min-height:90px;"><?= sanitize($settings['office_address'] ?? 'OnBolt Consumer India, Technopark Campus Road, Trivandrum, Kerala 695582') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:1rem;">
            Publish Content Updates to Website
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
