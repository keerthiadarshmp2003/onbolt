<?php
/**
 * OnBolt Digital Experience Platform - Categories Management
 * URL: /admin/categories.php
 */

declare(strict_types=1);

$adminActive = 'categories';
$adminTitle = 'Product Categories Management';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?? $name)));
        $description = trim($_POST['description'] ?? '');

        // Image upload
        $imagePath = 'assets/images/home/630px-x-420px-Catogary-Uniforms-1.webp';
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $fname = 'cat_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], ROOT_PATH . '/uploads/banners/' . $fname)) {
                    $imagePath = 'uploads/banners/' . $fname;
                }
            }
        }

        if (empty($name)) {
            $err = 'Category Name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO product_categories (name, slug, description, image, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$name, $slug, $description, $imagePath]);
                $msg = "Category '{$name}' created successfully!";
                logActivity('create_category', 'categories', (int)$admin['id'], 'admin', "Created category {$name}");
            } catch (Exception $e) {
                $err = 'Error creating category: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM product_categories WHERE id = ?")->execute([$id]);
            $msg = 'Category deleted.';
            logActivity('delete_category', 'categories', (int)$admin['id'], 'admin', "Deleted category #{$id}");
        } catch (Exception $e) {
            $err = 'Cannot delete category containing assigned products.';
        }
    }
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count FROM product_categories c ORDER BY c.sort_order ASC")->fetchAll();
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
    
    <!-- Categories Table -->
    <div>
        <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.25rem;">Active Categories (<?= count($categories) ?>)</h3>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Media</th>
                        <th>Name &amp; Slug</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td style="width:60px;">
                                <div style="width:50px; height:35px; border-radius:var(--radius-sm); border:1px solid var(--brand-border); overflow:hidden; background:var(--brand-surface-alt);">
                                    <img src="<?= BASE_URL ?>/<?= sanitize($c['image']) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:800; color:var(--brand-dark);"><?= sanitize($c['name']) ?></div>
                                <div style="font-size:0.8rem; color:var(--brand-muted);"><code><?= sanitize($c['slug']) ?></code></div>
                            </td>
                            <td style="font-size:0.85rem; color:var(--brand-muted);"><?= sanitize($c['description']) ?></td>
                            <td style="font-weight:700;"><?= $c['product_count'] ?></td>
                            <td style="text-align:right;">
                                <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($c['name']) ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

    <!-- Create Category Form -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Create New Category</h3>

        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" required class="form-control" placeholder="e.g. Winter Uniforms &amp; Hoodies">
            </div>

            <div class="form-group">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" placeholder="winter-uniforms-hoodies">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Institutional fleece hoodies, blazers, and thermal vests."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Cover Banner</label>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                Add Category
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
