<?php
/**
 * OnBolt Digital Experience Platform - School Product Assignment
 * URL: /admin/school_products.php
 */

declare(strict_types=1);

$adminActive = 'school_products';
$adminTitle = 'School-Wise Uniform Assignment & Pricing';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

$schoolId = (int)($_GET['school_id'] ?? 1);
$schools = $pdo->query("SELECT id, name, slug FROM schools ORDER BY name ASC")->fetchAll();

// Handle Assign / Update / Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $pId = (int)$_POST['product_id'];
        $customPrice = !empty($_POST['custom_price']) ? (float)$_POST['custom_price'] : null;
        $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, is_mandatory, status, created_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), is_mandatory = VALUES(is_mandatory), status = 1");
            $stmt->execute([$schoolId, $pId, $customPrice, $isMandatory]);
            $msg = 'Product assigned to school catalog successfully!';
            logActivity('assign_product', 'school_products', (int)$admin['id'], 'admin', "Assigned product #{$pId} to school #{$schoolId}");
        } catch (Exception $e) {
            $err = 'Error assigning product: ' . $e->getMessage();
        }
    } elseif ($action === 'remove') {
        $spId = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM school_products WHERE id = ?")->execute([$spId]);
            $msg = 'Product removed from school catalog.';
            logActivity('remove_school_product', 'school_products', (int)$admin['id'], 'admin', "Removed assignment #{$spId}");
        } catch (Exception $e) {
            $err = 'Error removing product.';
        }
    }
}

// Fetch Assigned Products for this school
$assignedStmt = $pdo->prepare("SELECT sp.*, p.name as product_name, p.sku, p.price as base_price, p.featured_image, c.name as category_name
                               FROM school_products sp
                               JOIN products p ON sp.product_id = p.id
                               LEFT JOIN product_categories c ON p.category_id = c.id
                               WHERE sp.school_id = ?
                               ORDER BY sp.is_mandatory DESC, p.name ASC");
$assignedStmt->execute([$schoolId]);
$assignedProducts = $assignedStmt->fetchAll();

// Fetch Unassigned master products
$unassignedStmt = $pdo->prepare("SELECT p.* FROM products p WHERE p.id NOT IN (SELECT product_id FROM school_products WHERE school_id = ?) ORDER BY p.name ASC");
$unassignedStmt->execute([$schoolId]);
$unassignedProducts = $unassignedStmt->fetchAll();
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

<!-- School Selector Bar -->
<div style="background:#ffffff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; align-items:center; gap:1rem;">
        <label style="font-weight:800; font-size:0.95rem;">Select School Institution:</label>
        <form action="" method="GET">
            <select name="school_id" class="form-control" style="width:300px; font-weight:700;" onchange="this.form.submit()">
                <?php foreach ($schools as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $schoolId === (int)$s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2.5rem; align-items:flex-start;">
    
    <!-- Assigned Products Table -->
    <div>
        <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.25rem;">Active School Catalog (<?= count($assignedProducts) ?>)</h3>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Product &amp; SKU</th>
                        <th>Price</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignedProducts)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--brand-muted);">No products assigned to this school yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignedProducts as $ap): ?>
                            <tr>
                                <td style="width:50px;">
                                    <div style="width:40px; height:40px; border-radius:var(--radius-sm); border:1px solid var(--brand-border); overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                        <img src="<?= BASE_URL ?>/<?= sanitize($ap['featured_image']) ?>" alt="" style="max-width:85%; max-height:85%; object-fit:contain;">
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:800; color:var(--brand-dark);"><?= sanitize($ap['product_name']) ?></div>
                                    <div style="font-size:0.8rem; color:var(--brand-muted);">SKU: <?= sanitize($ap['sku']) ?></div>
                                </td>
                                <td style="font-weight:800; color:var(--brand-primary);"><?= formatCurrency($ap['custom_price'] ?: $ap['base_price']) ?></td>
                                <td style="text-align:right;">
                                    <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($ap['product_name']) ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="id" value="<?= $ap['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--brand-accent); padding:0.25rem 0.5rem; font-size:0.75rem;">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assign Product Form -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Assign Product to School</h3>

        <?php if (empty($unassignedProducts)): ?>
            <p style="color:var(--brand-muted); font-size:0.9rem;">All catalog products are already assigned to this school.</p>
        <?php else: ?>
            <form action="" method="POST">
                <input type="hidden" name="action" value="assign">

                <div class="form-group">
                    <label class="form-label">Select Master Product *</label>
                    <select name="product_id" required class="form-control">
                        <?php foreach ($unassignedProducts as $up): ?>
                            <option value="<?= $up['id'] ?>"><?= sanitize($up['name']) ?> (Base: <?= formatCurrency($up['price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">School-Specific Custom Price (Optional Override)</label>
                    <input type="number" step="0.01" name="custom_price" class="form-control" placeholder="Leave blank to use base catalog price">
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="is_mandatory" value="1" style="accent-color:var(--brand-primary); width:18px; height:18px;">
                        <span style="font-weight:700; font-size:0.9rem;">Mark as Mandatory Uniform Kit Item</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-secondary" style="width:100%; margin-top:1rem;">
                    Assign to School Portal
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
