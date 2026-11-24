<?php
/**
 * OnBolt Digital Experience Platform - School Requisition Cart
 * URL: /schools/{school-slug}/cart/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$schoolSlug = trim($_GET['school'] ?? '');
$school = getSchoolBySlug($schoolSlug);

if (!$school) {
    header("Location: " . BASE_URL . "/schools/");
    exit;
}

$parent = requireParentAuth((int)$school['id'], $school['slug']);

// Handle Cart Actions directly via POST as well
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = $_POST['item_id'] ?? '';

    if ($action === 'update') {
        $qty = max(0, (int)($_POST['quantity'] ?? 1));
        updateCartQuantity((int)$school['id'], $itemId, $qty);
    } elseif ($action === 'remove') {
        removeFromCart((int)$school['id'], $itemId);
    } elseif ($action === 'clear') {
        clearCart((int)$school['id']);
    }
    header("Location: " . BASE_URL . "/schools/{$schoolSlug}/cart/");
    exit;
}

$cartTotals = getCartTotals((int)$school['id']);
$pageTitle = 'Uniform Cart Requisition | ' . sanitize($school['name']);
$activeSchoolSlug = $school['slug'];

require_once __DIR__ . '/../includes/header.php';
?>

<div style="background: var(--brand-surface-alt); padding-top: 120px; padding-bottom: 2rem; border-bottom: 1px solid var(--brand-border);">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <span class="text-eyebrow gold" style="font-size:0.7rem; padding:0.2rem 0.6rem;"><?= sanitize($school['name']) ?></span>
                <h1 class="heading-1" style="margin-top:0.25rem;">Student Uniform Requisition Cart</h1>
            </div>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-outline btn-sm">
                &larr; Add More Items
            </a>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if (empty($cartTotals['items'])): ?>
            <div style="background:#fff; border-radius:var(--radius-xl); padding:4rem 2rem; text-align:center; border:1px solid var(--brand-border); max-width:650px; margin:0 auto; box-shadow:var(--shadow-sm);">
                <div style="font-size:3.5rem; margin-bottom:1rem;">🛍️</div>
                <h2 class="heading-2" style="margin-bottom:0.75rem;">Your School Requisition Cart is Empty</h2>
                <p style="color:var(--brand-muted); margin-bottom:2rem; line-height:1.6;">
                    You haven't selected any uniform shirts, trousers, or complete kits for <?= sanitize($school['name']) ?> yet.
                </p>
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary btn-lg">
                    Browse School Uniform Catalog
                </a>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns: 1.8fr 1fr; gap:3rem; align-items:flex-start;">
                <!-- Cart Items Table / Cards -->
                <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
                    <div style="padding:1.5rem 2rem; border-bottom:1px solid var(--brand-border); display:flex; justify-content:space-between; align-items:center;">
                        <h3 class="heading-2" style="font-size:1.25rem;">Uniform Items (<?= $cartTotals['count'] ?>)</h3>
                        <form action="" method="POST" onsubmit="return confirm('Clear all items from your school cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" style="font-size:0.85rem; color:var(--brand-accent); font-weight:700;">Empty Cart</button>
                        </form>
                    </div>

                    <div style="padding:1rem 2rem;">
                        <?php foreach ($cartTotals['items'] as $item): ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:1.25rem 0; border-bottom:1px solid var(--brand-border); gap:1.5rem; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:1.25rem;">
                                    <div style="width:70px; height:70px; border-radius:var(--radius-md); background:var(--brand-surface-alt); border:1px solid var(--brand-border); overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                        <img src="<?= BASE_URL ?>/<?= sanitize($item['featured_image']) ?>" alt="<?= sanitize($item['name']) ?>" style="max-width:85%; max-height:85%; object-fit:contain;">
                                    </div>
                                    <div>
                                        <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;"><?= sanitize($item['name']) ?></h4>
                                        <div style="font-size:0.85rem; color:var(--brand-muted);">
                                            Size: <strong style="color:var(--brand-dark);"><?= sanitize($item['size']) ?></strong>
                                            <?php if (!empty($item['color'])): ?> &bull; Color: <?= sanitize($item['color']) ?><?php endif; ?>
                                        </div>
                                        <div style="font-size:0.9rem; font-weight:800; color:var(--brand-primary); margin-top:0.25rem;">
                                            <?= formatCurrency($item['price']) ?> / pc
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:1.5rem;">
                                    <!-- Quantity form with sleek stepper buttons -->
                                    <form action="" method="POST" style="display:flex; align-items:center; gap:0.5rem;" class="cart-qty-stepper">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="item_id" value="<?= sanitize($item['item_id']) ?>">
                                        <label style="font-size:0.8rem; font-weight:700; color:var(--brand-muted);">Qty:</label>
                                        <div style="display:flex; align-items:center; gap:5px;">
                                            <button type="button" class="btn-stepper btn-qty-minus" aria-label="Decrease Quantity">&minus;</button>
                                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="20" class="form-control" style="width:50px; padding:0.35rem 0.2rem; font-size:0.9rem; text-align:center; font-weight:700;" onchange="this.form.submit()">
                                            <button type="button" class="btn-stepper btn-qty-plus" aria-label="Increase Quantity">&plus;</button>
                                        </div>
                                    </form>

                                    <!-- Subtotal -->
                                    <div style="font-weight:800; font-size:1.1rem; color:var(--brand-dark); min-width:85px; text-align:right;">
                                        <?= formatCurrency($item['price'] * $item['quantity']) ?>
                                    </div>

                                    <!-- Remove -->
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="item_id" value="<?= sanitize($item['item_id']) ?>">
                                        <button type="submit" title="Remove Item" style="color:var(--brand-muted); font-size:1.1rem; padding:0.25rem 0.5rem; transition:color 0.2s;" onmouseover="this.style.color='var(--brand-accent)'" onmouseout="this.style.color='var(--brand-muted)'">
                                            ✕
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Summary Card -->
                <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-lg); position:sticky; top:100px;">
                    <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:1.5rem; border-bottom:1px solid var(--brand-border); padding-bottom:0.75rem;">Requisition Summary</h3>

                    <div style="display:flex; justify-content:space-between; margin-bottom:1rem; font-size:0.95rem;">
                        <span style="color:var(--brand-muted);">Subtotal (<?= $cartTotals['count'] ?> items):</span>
                        <span style="font-weight:700; color:var(--brand-dark);"><?= formatCurrency($cartTotals['subtotal']) ?></span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:1rem; font-size:0.95rem;">
                        <span style="color:var(--brand-muted);">School Campus Dispatch:</span>
                        <span style="font-weight:700; color:#15803d;">Free Institutional Delivery</span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:1.75rem; padding-top:1.25rem; border-top:1.5px dashed var(--brand-border); font-size:1.25rem; font-weight:800;">
                        <span>Estimated Total:</span>
                        <span style="color:var(--brand-primary);"><?= formatCurrency($cartTotals['total']) ?></span>
                    </div>

                    <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/checkout/" class="btn btn-secondary btn-lg" style="width:100%; margin-bottom:1rem;">
                        <span>Proceed to Final Checkout</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>

                    <div style="font-size:0.8rem; color:var(--brand-muted); text-align:center; line-height:1.5;">
                        🔒 Encrypted requisition. Payment via Instant WhatsApp confirmation or School Desk payment receipt.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
