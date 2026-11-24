<?php
/**
 * OnBolt Digital Experience Platform - Dedicated School Landing Page
 * URL: /schools/{school-slug}/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$schoolSlug = trim($_GET['school'] ?? '');
$school = getSchoolBySlug($schoolSlug);

if (!$school) {
    header("Location: " . BASE_URL . "/schools/");
    exit;
}

$schoolProducts = getProductsForSchool((int)$school['id']);
$pageTitle = sanitize($school['name']) . ' | Official Uniforms & School Kits';
$pageDesc = sanitize($school['description']);
$activeSchoolSlug = $school['slug'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- School Hero Banner -->
<div style="position:relative; padding-top:160px; padding-bottom:90px; background:linear-gradient(135deg, rgba(11, 19, 43, 0.92) 0%, rgba(58, 91, 160, 0.88) 100%), url('<?= BASE_URL ?>/<?= sanitize($school['banner_image'] ?: 'assets/images/home/540-pixel-x-836-pixel-1.webp') ?>') center/cover; color:#ffffff;">
    <div class="container">
        <div style="display:flex; align-items:center; gap:2.5rem; flex-wrap:wrap;">
            <div style="width:110px; height:110px; background:#fff; border-radius:var(--radius-lg); padding:10px; box-shadow:var(--shadow-xl); flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                <img src="<?= BASE_URL ?>/<?= sanitize($school['logo'] ?: 'assets/images/logo/cropped-favicon-1-96x96.png') ?>" alt="<?= sanitize($school['name']) ?> Crest" style="max-width:100%; max-height:100%; object-fit:contain;">
            </div>

            <div style="flex-grow:1; max-width:800px;">
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                    <span class="text-eyebrow gold" style="font-size:0.75rem;">Official Academic Partner</span>
                    <span style="font-size:0.85rem; color:#cbd5e1;">📍 <?= sanitize($school['location'] ?: $school['city']) ?></span>
                </div>
                <h1 class="display-2" style="color:#ffffff; margin-bottom:1rem;"><?= sanitize($school['name']) ?></h1>
                <p style="color:#e2e8f0; font-size:1.1rem; line-height:1.6; margin-bottom:1.5rem;">
                    <?= sanitize($school['description']) ?>
                </p>
                <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>/schools/<?= sanitize($school['slug']) ?>/login/" class="btn btn-primary btn-lg">
                        <span>Access Parent Order Portal</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <a href="#products-showcase" class="btn btn-outline btn-lg" style="color:#fff; background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.3);">
                        <span>Inspect Uniform Catalog</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ordering Instructions -->
<section class="section" style="padding-top:4rem; padding-bottom:4rem;">
    <div class="container">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:2rem;">
            <div style="background:#fff; border-radius:var(--radius-md); padding:1.75rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-weight:800; color:var(--brand-primary); font-size:1.2rem; margin-bottom:0.5rem;">Step 1: Parent Sign In</div>
                <p style="font-size:0.9rem; color:var(--brand-muted); line-height:1.5;">Use your school-assigned Login ID (e.g. <code>Trins01</code>) to unlock your child's grade curriculum uniform items.</p>
            </div>
            <div style="background:#fff; border-radius:var(--radius-md); padding:1.75rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-weight:800; color:var(--brand-secondary); font-size:1.2rem; margin-bottom:0.5rem;">Step 2: Sizing &amp; Selection</div>
                <p style="font-size:0.9rem; color:var(--brand-muted); line-height:1.5;">Review the accurate digital size chart. Select required quantities for daily uniforms, house t-shirts, and kits.</p>
            </div>
            <div style="background:#fff; border-radius:var(--radius-md); padding:1.75rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-weight:800; color:var(--brand-accent); font-size:1.2rem; margin-bottom:0.5rem;">Step 3: Fast Fulfillment</div>
                <p style="font-size:0.9rem; color:var(--brand-muted); line-height:1.5;">Confirm your order with instantaneous WhatsApp verification or offline school counter pick-up receipt.</p>
            </div>
        </div>
    </div>
</section>

<!-- Available Products for this School -->
<section class="section section-alt" id="products-showcase">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow">Approved Collection</span>
            <h2 class="display-2 section-title">School Uniforms &amp; Complete Kits</h2>
            <p class="text-lead">Exclusively tailored and verified for <?= sanitize($school['name']) ?>.</p>
        </div>

        <div class="products-portal-grid">
            <?php foreach ($schoolProducts as $p): ?>
                <div class="product-item-card">
                    <div class="product-thumb-box">
                        <img src="<?= BASE_URL ?>/<?= sanitize($p['featured_image'] ?: 'assets/images/home/630px-x-420px-Catogary-Uniforms-1.webp') ?>" alt="<?= sanitize($p['name']) ?>">
                    </div>
                    <div class="product-card-details">
                        <span style="font-size:0.75rem; font-weight:800; color:var(--brand-muted); text-transform:uppercase; letter-spacing:0.05em;"><?= sanitize($p['category_name'] ?: 'Uniform') ?></span>
                        <h4 class="product-title-text"><?= sanitize($p['name']) ?></h4>
                        <div class="product-price-tag"><?= formatCurrency($p['effective_price']) ?></div>
                        
                        <p style="font-size:0.88rem; color:var(--brand-muted); line-height:1.5; margin-bottom:1.5rem;">
                            <?= sanitize($p['short_description'] ?: $p['description']) ?>
                        </p>

                        <a href="<?= BASE_URL ?>/schools/<?= sanitize($school['slug']) ?>/login/" class="btn btn-secondary btn-sm" style="margin-top:auto;">
                            <span>Sign In to Order</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
