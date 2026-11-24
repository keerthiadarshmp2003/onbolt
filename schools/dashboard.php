<?php
/**
 * OnBolt Digital Experience Platform - Parent Dashboard
 * URL: /schools/{school-slug}/dashboard/
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
$students = getStudentsForParent((int)$parent['id']);
$schoolProducts = getProductsForSchool((int)$school['id']);

// Fetch Parent's Recent Orders
$pdo = getDbConnection();
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE parent_id = ? AND school_id = ? ORDER BY id DESC LIMIT 5");
$orderStmt->execute([$parent['id'], $school['id']]);
$recentOrders = $orderStmt->fetchAll();

$pageTitle = 'Dashboard | ' . sanitize($parent['parent_name']) . ' - ' . sanitize($school['name']);
$activeSchoolSlug = $school['slug'];

require_once __DIR__ . '/../includes/header.php';
?>

<div style="background: var(--brand-surface-alt); padding-top: 120px; padding-bottom: 2rem; border-bottom: 1px solid var(--brand-border);">
    <div class="container">
        <!-- Dashboard Top Header Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
            <div style="display:flex; align-items:center; gap:1.5rem;">
                <div style="width:68px; height:68px; background:#fff; border-radius:var(--radius-md); padding:6px; box-shadow:var(--shadow-sm); border:1px solid var(--brand-border); display:flex; align-items:center; justify-content:center;">
                    <img src="<?= BASE_URL ?>/<?= sanitize($school['logo'] ?: 'assets/images/logo/cropped-favicon-1-96x96.png') ?>" alt="<?= sanitize($school['name']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
                </div>
                <div>
                    <span class="text-eyebrow gold" style="font-size:0.7rem; padding:0.2rem 0.6rem;"><?= sanitize($school['name']) ?></span>
                    <h1 class="heading-1" style="margin-top:0.25rem;">Welcome back, <?= sanitize($parent['parent_name']) ?></h1>
                    <div style="font-size:0.85rem; color:var(--brand-muted);">Parent ID: <strong><?= sanitize($parent['login_id']) ?></strong> &bull; Contact: <?= sanitize($parent['phone']) ?></div>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; align-items:center;">
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary">
                    <span>Order Uniforms</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/logout/" class="btn btn-outline btn-sm">
                    Sign Out
                </a>
            </div>
        </div>

        <!-- Dashboard Sub-Navigation Tabs -->
        <div style="display:flex; gap:1rem; margin-top:2rem; overflow-x:auto; padding-bottom:0.5rem;">
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/dashboard/" class="btn btn-primary btn-sm" style="border-radius:var(--radius-pill);">Overview</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Browse School Catalog</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/cart/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Cart Requisition</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Order History</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/profile/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Student Profiles</a>
        </div>
    </div>
</div>

<section class="section" style="padding-top: 3rem;">
    <div class="container">
        <!-- Student Information Card -->
        <div style="margin-bottom: 3.5rem;">
            <h3 class="heading-2" style="margin-bottom:1.25rem;">Registered Enrolled Students</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:1.75rem;">
                <?php foreach ($students as $stu): ?>
                    <div style="background:#ffffff; border-radius:var(--radius-lg); padding:1.75rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:1.25rem;">
                        <div style="width:58px; height:58px; border-radius:50%; background:linear-gradient(135deg, var(--brand-primary), #1a2a4e); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.3rem; flex-shrink:0;">
                            <?= strtoupper(substr($stu['student_name'], 0, 1)) ?>
                        </div>
                        <div style="flex-grow:1;">
                            <div style="font-weight:800; font-size:1.15rem; color:var(--brand-dark);"><?= sanitize($stu['student_name']) ?></div>
                            <div style="font-size:0.85rem; color:var(--brand-primary); font-weight:700;">
                                <?= sanitize($stu['grade_class']) ?> <?= !empty($stu['section']) ? '- Section ' . sanitize($stu['section']) : '' ?>
                            </div>
                            <div style="font-size:0.8rem; color:var(--brand-muted); margin-top:0.25rem;">
                                Admission: <code><?= sanitize($stu['admission_no']) ?></code> &bull; <?= sanitize($stu['gender']) ?>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary btn-sm" style="padding:0.4rem 0.8rem; font-size:0.8rem;">
                            Kit
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Orders & Tracking -->
        <div style="margin-bottom: 3.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h3 class="heading-2">Recent Requisitions</h3>
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/" style="font-size:0.9rem; font-weight:700; color:var(--brand-primary);">View All Orders &rarr;</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <div style="background:#fff; border-radius:var(--radius-lg); padding:3rem; text-align:center; border:1px solid var(--brand-border);">
                    <div style="font-size:2.5rem; margin-bottom:0.75rem;">📦</div>
                    <h4 class="heading-3" style="margin-bottom:0.5rem;">No orders placed yet</h4>
                    <p style="color:var(--brand-muted); font-size:0.92rem; margin-bottom:1.5rem;">You haven't ordered your child's uniform kit for the academic session.</p>
                    <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-secondary">Order Uniform Kit Now</a>
                </div>
            <?php else: ?>
                <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.92rem;">
                            <thead>
                                <tr style="background:var(--brand-surface-alt); border-bottom:1px solid var(--brand-border); color:var(--brand-muted); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <th style="padding:1rem 1.5rem;">Order Reference</th>
                                    <th style="padding:1rem 1.5rem;">Student</th>
                                    <th style="padding:1rem 1.5rem;">Date</th>
                                    <th style="padding:1rem 1.5rem;">Total Items</th>
                                    <th style="padding:1rem 1.5rem;">Amount</th>
                                    <th style="padding:1rem 1.5rem;">Status</th>
                                    <th style="padding:1rem 1.5rem; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $ord): ?>
                                    <tr style="border-bottom:1px solid var(--brand-border);">
                                        <td style="padding:1.25rem 1.5rem; font-weight:800; color:var(--brand-primary);">
                                            <?= sanitize($ord['order_reference']) ?>
                                        </td>
                                        <td style="padding:1.25rem 1.5rem;">
                                            <div style="font-weight:700;"><?= sanitize($ord['student_name']) ?></div>
                                            <div style="font-size:0.8rem; color:var(--brand-muted);"><?= sanitize($ord['grade_class']) ?></div>
                                        </td>
                                        <td style="padding:1.25rem 1.5rem; color:var(--brand-muted); font-size:0.85rem;">
                                            <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                                        </td>
                                        <td style="padding:1.25rem 1.5rem;">
                                            <?= $ord['total_items'] ?> pcs
                                        </td>
                                        <td style="padding:1.25rem 1.5rem; font-weight:800; color:var(--brand-dark);">
                                            <?= formatCurrency($ord['total_amount']) ?>
                                        </td>
                                        <td style="padding:1.25rem 1.5rem;">
                                            <?= getOrderStatusBadge($ord['order_status']) ?>
                                        </td>
                                        <td style="padding:1.25rem 1.5rem; text-align:right;">
                                            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/?ref=<?= sanitize($ord['order_reference']) ?>" class="btn btn-outline btn-sm">
                                                View Order
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Catalog Fast Order Grid -->
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <div>
                    <h3 class="heading-2">Approved Uniforms for <?= sanitize($school['name']) ?></h3>
                    <p style="color:var(--brand-muted); font-size:0.9rem;">Select sizes and add to your child's uniform requisition cart.</p>
                </div>
                <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-outline btn-sm">Full Catalog</a>
            </div>

            <div class="products-portal-grid">
                <?php foreach (array_slice($schoolProducts, 0, 4) as $prod): ?>
                    <div class="product-item-card">
                        <div class="product-thumb-box">
                            <img src="<?= BASE_URL ?>/<?= sanitize($prod['featured_image'] ?: 'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png') ?>" alt="<?= sanitize($prod['name']) ?>">
                        </div>
                        <div class="product-card-details">
                            <span style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--brand-muted);"><?= sanitize($prod['category_name'] ?: 'Uniform') ?></span>
                            <h4 class="product-title-text"><?= sanitize($prod['name']) ?></h4>
                            <div class="product-price-tag"><?= formatCurrency($prod['effective_price']) ?></div>

                            <form action="<?= BASE_URL ?>/api/cart.php" method="POST" class="ajax-add-cart-form" style="margin-top:auto;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="school_id" value="<?= $school['id'] ?>">
                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">

                                <div style="margin-bottom:0.75rem;">
                                    <label style="font-size:0.75rem; font-weight:700; color:var(--brand-muted); display:block; margin-bottom:0.25rem;">Select Size:</label>
                                    <select name="size" required class="form-control" style="padding:0.4rem 0.75rem; font-size:0.85rem;">
                                        <?php if (!empty($prod['variants'])): ?>
                                            <?php foreach ($prod['variants'] as $v): ?>
                                                <option value="<?= sanitize($v['size']) ?>">Size <?= sanitize($v['size']) ?></option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="Standard">Standard Size</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">
                                    <span>Add to Cart</span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
