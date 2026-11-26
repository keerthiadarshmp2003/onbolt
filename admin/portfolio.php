<?php
/**
 * OnBolt Digital Experience Platform - Portfolio Management
 * URL: /admin/portfolio.php
 */

declare(strict_types=1);

$adminActive = 'portfolio';
$adminTitle = 'Institutional Portfolio Management';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title']);
        $clientName = trim($_POST['client_name']);
        $category = trim($_POST['category'] ?? 'School Uniforms');
        $description = trim($_POST['description'] ?? '');

        // Image upload
        $imagePath = 'assets/images/portfolio/IME-Vidhyalaya.jpeg';
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $fname = 'port_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], ROOT_PATH . '/uploads/portfolio/' . $fname)) {
                    $imagePath = 'uploads/portfolio/' . $fname;
                }
            }
        }

        if (empty($title) || empty($clientName)) {
            $err = 'Project Title and Client Name are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO portfolio (title, client_name, category, description, image, status, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$title, $clientName, $category, $description, $imagePath]);
                $msg = 'Portfolio showcase item created!';
                logActivity('create_portfolio', 'portfolio', (int)$admin['id'], 'admin', "Created portfolio item {$title}");
            } catch (Exception $e) {
                $err = 'Error creating portfolio item: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM portfolio WHERE id = ?")->execute([$id]);
        $msg = 'Portfolio item deleted.';
        logActivity('delete_portfolio', 'portfolio', (int)$admin['id'], 'admin', "Deleted portfolio #{$id}");
    }
}

$items = $pdo->query("SELECT * FROM portfolio ORDER BY sort_order ASC, id DESC")->fetchAll();
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

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2.5rem; align-items:flex-start;">
    <div>
        <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.25rem;">Portfolio Showcase Gallery (<?= count($items) ?>)</h3>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Media</th>
                        <th>Project Title</th>
                        <th>Client / School</th>
                        <th>Category</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td style="width:60px;">
                                <div style="width:50px; height:38px; border-radius:var(--radius-sm); border:1px solid var(--brand-border); overflow:hidden; background:var(--brand-surface-alt);">
                                    <img src="<?= BASE_URL ?>/<?= sanitize($it['image']) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:800; color:var(--brand-dark);"><?= sanitize($it['title']) ?></div>
                            </td>
                            <td style="font-weight:600;"><?= sanitize($it['client_name']) ?></td>
                            <td><span class="text-eyebrow" style="font-size:0.7rem; padding:2px 8px;"><?= sanitize($it['category']) ?></span></td>
                            <td style="text-align:right;">
                                <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($it['client_name']) ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $it['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--brand-accent); padding:0.25rem 0.5rem; font-size:0.75rem;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Add Showcase Project</h3>

        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Project Title *</label>
                <input type="text" name="title" required class="form-control" placeholder="Enter your Project Title">
            </div>

            <div class="form-group">
                <label class="form-label">Client Name / School *</label>
                <input type="text" name="client_name" required class="form-control" placeholder="Enter your Client / School Name">
            </div>

            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                    <option value="School Uniforms">School Uniforms</option>
                    <option value="Complete Kits">Complete Kits</option>
                    <option value="Athletic &amp; House Wear">Athletic &amp; House Wear</option>
                    <option value="Corporate Apparel">Corporate Apparel</option>
                    <option value="Executive Gifting">Executive Gifting</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Enter your Project Description"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Project Photograph</label>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                Add to Portfolio
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
