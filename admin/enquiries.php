<?php
/**
 * OnBolt Digital Experience Platform - Inquiries Management
 * URL: /admin/enquiries.php
 */

declare(strict_types=1);

$adminActive = 'enquiries';
$adminTitle = 'Contact Inquiries & Tenders';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = (int)$_POST['id'];
    $st = trim($_POST['status']);
    $pdo->prepare("UPDATE contact_enquiries SET status = ? WHERE id = ?")->execute([$st, $id]);
    $msg = 'Inquiry status updated!';
}

$enquiries = $pdo->query("SELECT * FROM contact_enquiries ORDER BY id DESC")->fetchAll();
?>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Sender Name</th>
                <th>Contact Details</th>
                <th>Subject / Institution</th>
                <th>Inquiry Message</th>
                <th>Status</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($enquiries)): ?>
                <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--brand-muted);">No inquiries received yet.</td></tr>
            <?php else: ?>
                <?php foreach ($enquiries as $eq): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--brand-muted);"><?= date('d M Y, h:i A', strtotime($eq['created_at'])) ?></td>
                        <td style="font-weight:800;"><?= sanitize($eq['name']) ?></td>
                        <td>
                            <div><?= sanitize($eq['email']) ?></div>
                            <div style="font-size:0.8rem; color:var(--brand-muted);"><?= sanitize($eq['phone']) ?></div>
                        </td>
                        <td style="font-weight:700; color:var(--brand-primary);"><?= sanitize($eq['subject']) ?></td>
                        <td style="font-size:0.85rem; color:var(--brand-dark-soft); max-width:300px;"><?= sanitize($eq['message']) ?></td>
                        <td>
                            <span style="font-weight:700; font-size:0.8rem; padding:3px 8px; border-radius:4px; background:<?= $eq['status'] === 'New' ? 'rgba(255,107,107,0.15); color:#dc2626;' : 'rgba(34,197,94,0.15); color:#15803d;' ?>"><?= sanitize($eq['status']) ?></span>
                        </td>
                        <td style="text-align:right;">
                            <form action="" method="POST" style="display:inline-flex; gap:0.3rem;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="form-control" style="padding:0.25rem 0.5rem; font-size:0.75rem;">
                                    <option value="New" <?= $eq['status'] === 'New' ? 'selected' : '' ?>>New</option>
                                    <option value="In Progress" <?= $eq['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $eq['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
