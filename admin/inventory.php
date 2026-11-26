<?php
/**
 * OnBolt Digital Experience Platform - Inventory Stock Management
 * URL: /admin/inventory.php
 */

declare(strict_types=1);

$adminActive = 'inventory';
$adminTitle = 'Uniform Inventory & Variant Stock Management';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $variantId = (int)$_POST['variant_id'];
    $stock = max(0, (int)$_POST['stock_quantity']);

    try {
        $stmt = $pdo->prepare("UPDATE product_variants SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$stock, $variantId]);
        $msg = 'Stock quantity updated successfully.';
        logActivity('update_stock', 'inventory', (int)$admin['id'], 'admin', "Adjusted stock for variant #{$variantId} to {$stock}");
    } catch (Exception $e) {
        $err = 'Error updating stock: ' . $e->getMessage();
    }
}

// Fetch all variants
$prodFilter = (int)($_GET['product_id'] ?? 0);
$products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();

$sql = "SELECT pv.*, p.name as product_name, p.featured_image, c.name as category_name
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE 1=1";

if ($prodFilter > 0) {
    $sql .= " AND pv.product_id = {$prodFilter}";
}
$sql .= " ORDER BY pv.stock_quantity ASC, p.name ASC";
$variants = $pdo->query($sql)->fetchAll();
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

<!-- Filter -->
<div style="background:#fff; border-radius:var(--radius-lg); padding:1.25rem 1.5rem; border:1px solid var(--brand-border); margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; align-items:center; gap:1rem;">
        <label style="font-weight:800; font-size:0.95rem;">Filter by Product:</label>
        <form action="" method="GET">
            <select name="product_id" class="form-control" style="width:280px;" onchange="this.form.submit()">
                <option value="0">Select Product</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $prodFilter === (int)$p['id'] ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Stock Table -->
<div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Product &amp; SKU</th>
                <th>Category</th>
                <th>Size Variant</th>
                <th>Available Units</th>
                <th>Status</th>
                <th style="text-align:right;">Adjust Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($variants as $v): ?>
                <?php $isLow = $v['stock_quantity'] < 20; ?>
                <tr>
                    <td style="width:50px;">
                        <div style="width:40px; height:40px; border-radius:var(--radius-sm); border:1px solid var(--brand-border); overflow:hidden; display:flex; align-items:center; justify-content:center;">
                            <img src="<?= BASE_URL ?>/<?= sanitize($v['featured_image']) ?>" alt="" style="max-width:85%; max-height:85%; object-fit:contain;">
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:800; color:var(--brand-dark);"><?= sanitize($v['product_name']) ?></div>
                        <div style="font-size:0.8rem; color:var(--brand-muted);">SKU: <?= sanitize($v['sku'] ?: 'OB-VAR-' . $v['id']) ?></div>
                    </td>
                    <td><?= sanitize($v['category_name'] ?: 'Uniform') ?></td>
                    <td style="font-weight:700; font-size:1rem; color:var(--brand-primary);">Size <?= sanitize($v['size']) ?></td>
                    <td style="font-weight:800; font-size:1.1rem; color:<?= $isLow ? '#dc2626' : 'var(--brand-dark)' ?>;">
                        <?= $v['stock_quantity'] ?> pcs
                    </td>
                    <td>
                        <?= $isLow ? '<span style="color:#dc2626; font-weight:800; font-size:0.75rem; background:rgba(255,107,107,0.15); padding:2px 8px; border-radius:4px;">Low Stock</span>' : '<span style="color:#15803d; font-weight:700; font-size:0.75rem; background:rgba(34,197,94,0.15); padding:2px 8px; border-radius:4px;">In Stock</span>' ?>
                    </td>
                    <td style="text-align:right;">
                        <form action="" method="POST" style="display:inline-flex; align-items:center; gap:0.4rem;">
                            <input type="hidden" name="action" value="update_stock">
                            <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">
                            <input type="number" name="stock_quantity" value="<?= $v['stock_quantity'] ?>" min="0" max="9999" class="form-control" style="width:80px; padding:0.3rem 0.5rem; font-size:0.85rem; text-align:center;">
                            <button type="submit" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem; font-size:0.78rem;">
                                Save
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
