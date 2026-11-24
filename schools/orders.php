<?php
/**
 * OnBolt Digital Experience Platform - School Orders & Real-Time Tracking
 * URL: /schools/{school-slug}/orders/
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
$pdo = getDbConnection();

$selectedRef = trim($_GET['ref'] ?? '');
$justPlaced = !empty($_GET['placed']);

// Fetch all orders for this parent in this school
$stmt = $pdo->prepare("SELECT * FROM orders WHERE parent_id = ? AND school_id = ? ORDER BY id DESC");
$stmt->execute([$parent['id'], $school['id']]);
$orders = $stmt->fetchAll();

// If a ref is selected, fetch its itemized details
$currentOrder = null;
$orderItems = [];
if (!empty($selectedRef)) {
    $cStmt = $pdo->prepare("SELECT * FROM orders WHERE order_reference = ? AND parent_id = ? AND school_id = ? LIMIT 1");
    $cStmt->execute([$selectedRef, $parent['id'], $school['id']]);
    $currentOrder = $cStmt->fetch();

    if ($currentOrder) {
        $iStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $iStmt->execute([$currentOrder['id']]);
        $orderItems = $iStmt->fetchAll();
    }
} elseif (!empty($orders)) {
    $currentOrder = $orders[0];
    $iStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $iStmt->execute([$currentOrder['id']]);
    $orderItems = $iStmt->fetchAll();
}

$pageTitle = 'Order History | ' . sanitize($school['name']);
$activeSchoolSlug = $school['slug'];
$whatsappDesk = getWebsiteSetting('whatsapp_order_phone', '918848670739');

require_once __DIR__ . '/../includes/header.php';
?>

<div style="background: var(--brand-surface-alt); padding-top: 120px; padding-bottom: 2rem; border-bottom: 1px solid var(--brand-border);">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <span class="text-eyebrow gold" style="font-size:0.7rem; padding:0.2rem 0.6rem;"><?= sanitize($school['name']) ?></span>
                <h1 class="heading-1" style="margin-top:0.25rem;">My Uniform Requisitions</h1>
                <p style="color:var(--brand-muted); font-size:0.9rem;">View your uniform requisitions, status and receipts.</p>
            </div>

            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary btn-sm">
                <span>Place New Order</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </a>
        </div>

        <div style="display:flex; gap:1rem; margin-top:2rem; overflow-x:auto;">
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/dashboard/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Overview</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Browse School Catalog</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/cart/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Cart Requisition</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/" class="btn btn-primary btn-sm" style="border-radius:var(--radius-pill);">Order History</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/profile/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Student Profiles</a>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if ($justPlaced && $currentOrder): ?>
            <div style="background:linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(58, 91, 160, 0.1)); border:2px solid #22c55e; border-radius:var(--radius-xl); padding:2.5rem; margin-bottom:3rem; text-align:center;">
                <div style="font-size:3rem; margin-bottom:0.5rem;">🎉</div>
                <h2 class="display-2" style="font-size:2rem; margin-bottom:0.5rem; color:#15803d;">Requisition Submitted Successfully!</h2>
                <p style="font-size:1.05rem; color:var(--brand-dark); max-width:650px; margin:0 auto 1.5rem; line-height:1.6;">
                    Order Reference: <strong style="color:var(--brand-primary); font-size:1.2rem;"><?= sanitize($currentOrder['order_reference']) ?></strong> has been received by the OnBolt Institutional Production Desk.
                </p>
                
                <?php
                $waMsg = "Hello OnBolt! I have placed uniform order " . $currentOrder['order_reference'] . " for student " . $currentOrder['student_name'] . " at " . $school['name'] . ". Total: " . formatCurrency($currentOrder['total_amount']);
                $waUrl = "https://wa.me/" . $whatsappDesk . "?text=" . urlencode($waMsg);
                ?>
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-lg" style="box-shadow:var(--shadow-lg);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.055-1.928-.487-1.464-.614-2.428-2.091-2.502-2.19-.074-.099-.597-.796-.597-1.517 0-.721.378-1.077.512-1.222.134-.145.293-.182.391-.182.099 0 .197.001.283.006.09.004.21-.035.328.25.12.289.412 1.002.449 1.075.036.074.06.16.012.257-.048.098-.073.159-.144.244-.072.086-.152.191-.217.257-.072.072-.148.15-.064.294.084.144.372.613.799.992.551.488 1.015.639 1.159.711.144.072.228.06.312-.036.084-.096.36-419.456-.563.096-.144.192-.12.324-.072.132.048.841.396.985.468.144.072.24.108.276.168.036.06.036.348-.108.753z"/>
                    </svg>
                    <span>Instant WhatsApp Confirmation</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div style="background:#fff; border-radius:var(--radius-xl); padding:4rem 2rem; text-align:center; border:1px solid var(--brand-border);">
                <div style="font-size:3rem; margin-bottom:1rem;">📋</div>
                <h3 class="heading-2" style="margin-bottom:0.5rem;">No Requisitions On Record</h3>
                <p style="color:var(--brand-muted); margin-bottom:1.5rem;">You haven't submitted any uniform requests yet.</p>
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary">Order Now</a>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:2.5rem; align-items:flex-start;">
                
                <!-- Left: Order List -->
                <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
                    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--brand-border); font-weight:800; font-size:1.1rem;">
                        Your Orders (<?= count($orders) ?>)
                    </div>
                    <div>
                        <?php foreach ($orders as $o): ?>
                            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/?ref=<?= sanitize($o['order_reference']) ?>" style="display:block; padding:1.25rem 1.5rem; border-bottom:1px solid var(--brand-border); background: <?= ($currentOrder && $currentOrder['id'] === $o['id']) ? 'var(--brand-primary-light)' : '#ffffff' ?>; transition:background 0.2s;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:0.35rem;">
                                    <span style="font-weight:800; color:var(--brand-primary);"><?= sanitize($o['order_reference']) ?></span>
                                    <span><?= getOrderStatusBadge($o['order_status']) ?></span>
                                </div>
                                <div style="font-size:0.85rem; font-weight:700; color:var(--brand-dark);"><?= sanitize($o['student_name']) ?> (<?= sanitize($o['grade_class']) ?>)</div>
                                <div style="display:flex; justify-content:space-between; margin-top:0.35rem; font-size:0.8rem; color:var(--brand-muted);">
                                    <span><?= date('d M Y', strtotime($o['created_at'])) ?></span>
                                    <span style="font-weight:800; color:var(--brand-dark);"><?= formatCurrency($o['total_amount']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Active Order Details & Timeline -->
                <?php if ($currentOrder): ?>
                    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm);">
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <span class="text-eyebrow" style="font-size:0.75rem; margin-bottom:0.5rem;">Order Details</span>
                                <h2 class="heading-1"><?= sanitize($currentOrder['order_reference']) ?></h2>
                                <div style="font-size:0.85rem; color:var(--brand-muted);">Placed on <?= date('d F Y \a\t h:i A', strtotime($currentOrder['created_at'])) ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div><?= getOrderStatusBadge($currentOrder['order_status']) ?></div>
                                <div style="font-size:1.5rem; font-weight:800; color:var(--brand-primary); margin-top:0.5rem;"><?= formatCurrency($currentOrder['total_amount']) ?></div>
                            </div>
                        </div>

                        <!-- Real-time Production Timeline Stepper -->
                        <div style="background:var(--brand-surface-alt); border-radius:var(--radius-lg); padding:1.75rem; margin-bottom:2.5rem; border:1px solid var(--brand-border);">
                            <h4 style="font-weight:800; font-size:0.95rem; margin-bottom:1.25rem;">Manufacturing &amp; Fulfillment Milestones</h4>
                            
                            <?php
                            $steps = ['Pending', 'Confirmed', 'Processing', 'Production', 'Quality Check', 'Packed', 'Delivered'];
                            $currentIdx = array_search($currentOrder['order_status'], $steps);
                            if ($currentIdx === false) $currentIdx = 0;
                            ?>
                            <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:0.5rem; text-align:center;">
                                <?php foreach ($steps as $idx => $st): ?>
                                    <?php $isDone = $idx <= $currentIdx; ?>
                                    <div>
                                        <div style="width:28px; height:28px; border-radius:50%; margin:0 auto 0.5rem; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:800; background:<?= $isDone ? 'var(--brand-primary)' : '#e2e8f0' ?>; color:<?= $isDone ? '#ffffff' : 'var(--brand-muted)' ?>;">
                                            <?= $isDone ? '✓' : ($idx + 1) ?>
                                        </div>
                                        <div style="font-size:0.7rem; font-weight:700; color:<?= $isDone ? 'var(--brand-dark)' : 'var(--brand-muted)' ?>;"><?= $st ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Itemized Table -->
                        <h4 style="font-weight:800; font-size:1.1rem; margin-bottom:1rem;">Requisition Items</h4>
                        <div style="border:1px solid var(--brand-border); border-radius:var(--radius-md); overflow:hidden; margin-bottom:2rem;">
                            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
                                <thead>
                                    <tr style="background:var(--brand-surface-alt); border-bottom:1px solid var(--brand-border); color:var(--brand-muted); font-size:0.8rem; text-transform:uppercase;">
                                        <th style="padding:0.75rem 1rem;">Item</th>
                                        <th style="padding:0.75rem 1rem;">Size</th>
                                        <th style="padding:0.75rem 1rem; text-align:center;">Qty</th>
                                        <th style="padding:0.75rem 1rem; text-align:right;">Unit Price</th>
                                        <th style="padding:0.75rem 1rem; text-align:right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr style="border-bottom:1px solid var(--brand-border);">
                                            <td style="padding:1rem; font-weight:700;"><?= sanitize($item['product_name']) ?></td>
                                            <td style="padding:1rem; color:var(--brand-muted);">Size <?= sanitize($item['size']) ?></td>
                                            <td style="padding:1rem; text-align:center; font-weight:700;"><?= $item['quantity'] ?></td>
                                            <td style="padding:1rem; text-align:right;"><?= formatCurrency($item['unit_price']) ?></td>
                                            <td style="padding:1rem; text-align:right; font-weight:800;"><?= formatCurrency($item['total_price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Delivery Meta -->
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; background:var(--brand-surface-alt); padding:1.5rem; border-radius:var(--radius-md); font-size:0.9rem;">
                            <div>
                                <div style="font-weight:800; color:var(--brand-dark); margin-bottom:0.25rem;">Student Details</div>
                                <div style="color:var(--brand-muted);"><?= sanitize($currentOrder['student_name']) ?> (<?= sanitize($currentOrder['grade_class']) ?>)</div>
                                <div style="color:var(--brand-muted);">Parent: <?= sanitize($currentOrder['parent_name']) ?> (<?= sanitize($currentOrder['mobile']) ?>)</div>
                            </div>
                            <div>
                                <div style="font-weight:800; color:var(--brand-dark); margin-bottom:0.25rem;">Campus Delivery Address</div>
                                <div style="color:var(--brand-muted); line-height:1.4;"><?= sanitize($currentOrder['delivery_address']) ?> - <?= sanitize($currentOrder['pincode']) ?></div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2rem; flex-wrap:wrap; gap:1rem;">
                            <button onclick="window.print();" class="btn btn-outline btn-sm">
                                🖨️ Print Order Receipt
                            </button>

                            <?php
                            $waMsg = "Hello OnBolt! Please update me on the status of my order " . $currentOrder['order_reference'] . " for student " . $currentOrder['student_name'] . " at " . $school['name'];
                            $waUrl = "https://wa.me/" . $whatsappDesk . "?text=" . urlencode($waMsg);
                            ?>
                            <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                <span>WhatsApp Support Desk</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
