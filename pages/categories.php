<?php
/**
 * OnBolt Digital Experience Platform - Categories
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'categories';
$pageTitle = 'Product Categories | OnBolt';
$pageDesc = 'Explore OnBolt collections: Daily School Uniforms, Complete Kits, Custom Crests & Badges, and Corporate Merchandise.';

$pdo = getDbConnection();
$categories = $pdo->query("SELECT * FROM product_categories WHERE status = 1 ORDER BY sort_order ASC")->fetchAll();
$allProducts = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN product_categories c ON p.category_id = c.id WHERE p.status = 1 ORDER BY p.sort_order ASC LIMIT 12")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Production Range</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Product Categories</h1>
        <p class="text-lead" style="max-width:700px;">
            From standard classroom attire to executive merchandise and athletic gear. Every item is tailored to exacting quality standards.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="category-cards-grid" style="margin-bottom: 5rem;">
            <?php foreach ($categories as $cat): ?>
                <div class="creative-category-card">
                    <div class="card-media-wrap">
                        <img src="<?= BASE_URL ?>/<?= sanitize($cat['image']) ?>" alt="<?= sanitize($cat['name']) ?>">
                    </div>
                    <div class="card-content-wrap">
                        <h3 class="heading-2 card-title"><?= sanitize($cat['name']) ?></h3>
                        <p class="card-text"><?= sanitize($cat['description']) ?></p>
                        <div class="card-footer-action">
                            <a href="<?= BASE_URL ?>/schools/" style="color:var(--brand-primary);">Order for Your School &rarr;</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Featured Products Grid -->
        <div class="section-header">
            <span class="text-eyebrow">Catalog Overview</span>
            <h2 class="display-2 section-title">Standard Product Showcase</h2>
            <p class="text-lead">Uniforms and garments available for institutional supply and customization.</p>
        </div>

        <div class="products-portal-grid">
            <?php foreach ($allProducts as $p): ?>
                <div class="product-item-card">
                    <div class="product-thumb-box">
                        <img src="<?= BASE_URL ?>/<?= sanitize($p['featured_image'] ?: 'assets/images/home/630px-x-420px-Catogary-Uniforms-1.webp') ?>" alt="<?= sanitize($p['name']) ?>">
                        <?php if ($p['is_kit']): ?>
                            <span class="product-mandatory-pill">Complete Kit</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-details">
                        <span style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--brand-muted); letter-spacing:0.05em; margin-bottom:0.35rem;"><?= sanitize($p['category_name'] ?: 'Apparel') ?></span>
                        <h4 class="product-title-text"><?= sanitize($p['name']) ?></h4>
                        <div class="product-price-tag"><?= formatCurrency($p['price']) ?></div>
                        <p style="font-size:0.85rem; color:var(--brand-muted); line-height:1.5; margin-bottom:1.25rem;">
                            <?= sanitize($p['short_description'] ?: 'High durability fabric designed for comfort and extended wear.') ?>
                        </p>
                        <a href="<?= BASE_URL ?>/schools/" class="btn btn-outline btn-sm" style="margin-top:auto;">
                            School Specific Order
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

