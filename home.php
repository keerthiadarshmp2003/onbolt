<?php
/**
 * OnBolt Digital Experience Platform - Homepage
 * Creative Editorial Direction with 3D Canvas, GSAP Visuals & Actual Brand Identity
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$activeNav = 'home';
$pageTitle = 'OnBolt | Complete Branding Solutions & Custom School Uniforms';
$pageDesc = 'OnBolt - Complete Branding Solutions. From premium school uniforms and complete kits to custom branding and corporate merchandise, trusted by 300+ institutions.';

$pdo = getDbConnection();

// 1. Fetch Categories
$categories = $pdo->query("SELECT * FROM product_categories WHERE status = 1 ORDER BY sort_order ASC")->fetchAll();

// 2. Fetch Featured Products
$featuredProducts = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN product_categories c ON p.category_id = c.id WHERE p.status = 1 ORDER BY p.sort_order ASC LIMIT 4")->fetchAll();

// 3. Fetch Top Schools (Show top 8 partner institutions on homepage)
$schools = $pdo->query("SELECT * FROM schools WHERE status = 1 ORDER BY sort_order ASC LIMIT 8")->fetchAll();

// 4. Fetch Portfolio
$portfolio = $pdo->query("SELECT * FROM portfolio WHERE status = 1 ORDER BY sort_order ASC LIMIT 6")->fetchAll();

// 5. Fetch Testimonials with real author avatars
$testimonials = $pdo->query("SELECT * FROM testimonials WHERE status = 1 ORDER BY sort_order ASC LIMIT 3")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================== 1. IMMERSIVE EDITORIAL HERO SECTION WITH 3D CANVAS ==================== -->
<section class="hero-editorial">
    <div class="container-wide">
        <div class="hero-grid">
            <div class="hero-content">
                <div class="hero-badge-wrap">
                    <span class="text-eyebrow">
                        <span style="font-size:1.1rem; line-height:0;">⚡</span> Complete Branding Solutions
                    </span>
                </div>

                <h1 class="display-1 hero-title">
                    Custom School &amp; <span class="highlight-orange">Brand</span> <span class="highlight-dark">Solutions</span>
                </h1>

                <p class="text-lead hero-description">
                    From premium school uniforms and complete kits to custom branding and corporate merchandise, OnBolt delivers high-quality products trusted by 300+ schools.
                </p>

                <div class="hero-cta-group">
                    <a href="<?= BASE_URL ?>/schools/" class="btn btn-primary btn-lg">
                        <span>School Order</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>

                    <a href="#solutions" class="btn btn-outline btn-lg">
                        <span>Explore Solutions</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <polyline points="19 12 12 19 5 12"></polyline>
                        </svg>
                    </a>
                </div>

                <!-- Hero Key Value Features -->
                <div class="hero-feature-pills">
                    <div class="hero-pill-item">
                        <span class="hero-pill-icon">✨</span>
                        <div>
                            <strong>Perfect Fit &amp; Comfort</strong>
                            <p>Tailored school uniforms designed for lasting comfort, durability, and confidence.</p>
                        </div>
                    </div>
                    <div class="hero-pill-item">
                        <span class="hero-pill-icon">🎨</span>
                        <div>
                            <strong>Custom Branding &amp; Design</strong>
                            <p>Personalized uniforms featuring school colors, crest logos, patterns, and unique details.</p>
                        </div>
                    </div>
                </div>

                <!-- Authentic Institutional Social Proof Avatars from onboltlive.com -->
                <div style="display:flex; align-items:center; gap:1rem; margin-top:1.5rem; margin-bottom:1.5rem;">
                    <div style="display:flex; margin-left:0.5rem;">
                        <img src="<?= BASE_URL ?>/assets/images/home/author-1-1.webp" alt="Administrator" style="width:40px; height:40px; border-radius:50%; border:2.5px solid #fff; margin-left:-8px; object-fit:cover; box-shadow:var(--shadow-sm);">
                        <img src="<?= BASE_URL ?>/assets/images/home/author-2-1.webp" alt="Administrator" style="width:40px; height:40px; border-radius:50%; border:2.5px solid #fff; margin-left:-8px; object-fit:cover; box-shadow:var(--shadow-sm);">
                        <img src="<?= BASE_URL ?>/assets/images/home/author-3-1.webp" alt="Administrator" style="width:40px; height:40px; border-radius:50%; border:2.5px solid #fff; margin-left:-8px; object-fit:cover; box-shadow:var(--shadow-sm);">
                    </div>
                    <div style="font-size:0.88rem; font-weight:700; color:var(--brand-dark);">
                        Trusted by <span style="color:var(--brand-primary); font-weight:800;">300+ Educational Institutions</span> &amp; Principals
                    </div>
                </div>

                <div class="hero-trust-bar">
                    <div class="trust-item">
                        <span class="trust-number">300+</span>
                        <span class="trust-label">Educational Institutions</span>
                    </div>
                    <div class="trust-item">
                        <span class="trust-number">100K+</span>
                        <span class="trust-label">Students Outfitted</span>
                    </div>
                    <div class="trust-item">
                        <span class="trust-number">100%</span>
                        <span class="trust-label">Quality Assured Combed Cotton</span>
                    </div>
                </div>
            </div>

            <!-- 3D Interactive Stage (100% Borderless) -->
            <div class="hero-visual-stage">
                <div id="threejs-hero-canvas"></div>
                
                <!-- Floating Glass Editorial Badges (Fully Clickable) -->
                <div class="hero-floating-card card-top" onclick="window.location.href='<?= BASE_URL ?>/schools/international-school/login/'" title="Open Parent Portal">
                    <div style="display:flex; align-items:center; gap:0.85rem;">
                        <div style="width:40px; height:40px; border-radius:50%; background:var(--brand-primary); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:1.1rem; box-shadow:0 4px 12px rgba(255,103,2,0.4);">
                            ⚡
                        </div>
                        <div>
                            <div style="font-size:0.85rem; font-weight:800; color:var(--brand-dark);">Unified Portal</div>
                            <div style="font-size:0.75rem; color:var(--brand-muted);">Direct School Kit Ordering &rarr;</div>
                        </div>
                    </div>
                </div>

                <div class="hero-floating-card card-bottom" onclick="window.location.href='<?= BASE_URL ?>/schools/international-school/'" title="Inspect School Profile">
                    <div style="display:flex; align-items:center; gap:0.85rem;">
                        <div style="width:40px; height:40px; border-radius:50%; background:var(--brand-secondary); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:0.95rem;">
                            ★
                        </div>
                        <div>
                            <div style="font-size:0.85rem; font-weight:800; color:var(--brand-dark);">Trivandrum International</div>
                            <div style="font-size:0.75rem; color:var(--brand-muted);">Official Uniform Provider &rarr;</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 2. COMPANY INTRODUCTION & HERITAGE ==================== -->
<section class="section" id="about">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:4rem; align-items:center;">
            <div>
                <span class="text-eyebrow" style="margin-bottom:1.25rem;">Excellence in Production</span>
                <h2 class="display-2" style="margin-bottom:1.5rem;">
                    Engineering High-Performance Attire for Schools &amp; Enterprises
                </h2>
                <p class="text-lead" style="margin-bottom:1.5rem;">
                    OnBolt – Complete Branding Solutions brings world-class fabric technology, ergonomic tailoring, and effortless digital ordering to educational institutions and progressive corporations.
                </p>
                <p style="color:var(--brand-muted); line-height:1.75; margin-bottom:2rem;">
                    From day-one fabric testing against shrinkage, fading, and high-frequency laundering, to our custom digital school ordering portal that eliminates classroom distribution queues, OnBolt sets the benchmark for institutional merchandising across South India.
                </p>
                <div style="display:flex; gap:1.25rem; flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>/about.php" class="btn btn-primary">Discover Our Heritage</a>
                    <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Consult Our Specialists</a>
                </div>
            </div>

            <div style="position:relative;">
                <div style="border-radius:var(--radius-xl); overflow:hidden; box-shadow:var(--shadow-xl); border:6px solid #ffffff;">
                    <img src="<?= BASE_URL ?>/assets/images/home/540-pixel-x-836-pixel-1.webp" alt="OnBolt Tailoring" style="width:100%; height:auto;">
                </div>
                <div style="position:absolute; bottom:-25px; left:-25px; background:var(--brand-primary); padding:1.75rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); max-width:240px; color:#ffffff;">
                    <div style="font-family:var(--font-display); font-size:2rem; font-weight:800; color:#ffffff; line-height:1;">12+ Yrs</div>
                    <div style="font-size:0.85rem; font-weight:700; color:rgba(255,255,255,0.9); margin-top:0.4rem;">Pioneering Academic Uniform Innovation</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 3. COMPLETE CUSTOMIZATION SERVICES ==================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow">Our Specializations</span>
            <h2 class="display-2 section-title">Complete Customization Services</h2>
            <p class="text-lead">
                We provide complete custom design, manufacturing, and branding solutions for schools and organizations, including uniforms, accessories, and personalized merchandise with high-quality printing and reliable delivery.
            </p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:2.5rem;">
            <!-- Service 1: School Uniform Solutions -->
            <div class="card-tilt" style="background:#ffffff; padding:2.5rem; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
                <div style="width:60px; height:60px; border-radius:var(--radius-md); background:var(--brand-primary-light); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:1.5rem;">
                    👔
                </div>
                <h3 class="heading-2" style="font-size:1.4rem; margin-bottom:0.75rem;">School Uniform Solutions</h3>
                <p style="color:var(--brand-muted); line-height:1.65; margin-bottom:1.5rem; flex-grow:1;">
                    Tailored daily polos, pleated skirts, trousers, pinafores, and formal blazers engineered with 2-inch let-out growth allowances and breathable cotton pique weaves.
                </p>
                <a href="<?= BASE_URL ?>/schools/" style="color:var(--brand-primary); font-weight:800; display:inline-flex; align-items:center; gap:0.4rem;">
                    <span>Explore School Uniforms</span> &rarr;
                </a>
            </div>

            <!-- Service 2: Custom Branding & Merchandise -->
            <div class="card-tilt" style="background:#ffffff; padding:2.5rem; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
                <div style="width:60px; height:60px; border-radius:var(--radius-md); background:var(--brand-secondary-light); color:#92580c; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:1.5rem;">
                    🧵
                </div>
                <h3 class="heading-2" style="font-size:1.4rem; margin-bottom:0.75rem;">Custom Branding &amp; Merchandise</h3>
                <p style="color:var(--brand-muted); line-height:1.65; margin-bottom:1.5rem; flex-grow:1;">
                    Computerized Tajima multi-head embroidery, high-density plastisol printing, woven crest badges, and customized athletic wear built for institutional pride.
                </p>
                <a href="<?= BASE_URL ?>/corporate.php" style="color:var(--brand-primary); font-weight:800; display:inline-flex; align-items:center; gap:0.4rem;">
                    <span>View Branding Solutions</span> &rarr;
                </a>
            </div>

            <!-- Service 3: Complete School Kit Supply -->
            <div class="card-tilt" style="background:#ffffff; padding:2.5rem; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
                <div style="width:60px; height:60px; border-radius:var(--radius-md); background:var(--brand-primary-light); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:1.5rem;">
                    🎒
                </div>
                <h3 class="heading-2" style="font-size:1.4rem; margin-bottom:0.75rem;">Complete School Kit Supply</h3>
                <p style="color:var(--brand-muted); line-height:1.65; margin-bottom:1.5rem; flex-grow:1;">
                    All-in-one head-to-toe kits: academic uniforms, sports house t-shirts, athletic track pants, backpacks, cushioned socks, and accessories packaged in student-ready kits.
                </p>
                <a href="<?= BASE_URL ?>/categories.php" style="color:var(--brand-primary); font-weight:800; display:inline-flex; align-items:center; gap:0.4rem;">
                    <span>Browse Complete Kits</span> &rarr;
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 4. DYNAMIC PRODUCT CATEGORIES ==================== -->
<section class="section" id="solutions">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow">Production Range</span>
            <h2 class="display-2 section-title">Explore Our Print Categories</h2>
            <p class="text-lead">
                Engineered with breathable weaves, reinforced seams, and institutional color precision.
            </p>
        </div>

        <div class="category-cards-grid">
            <?php foreach ($categories as $cat): ?>
                <div class="creative-category-card" onclick="window.location.href='<?= BASE_URL ?>/categories.php'" style="cursor:pointer;">
                    <div class="card-media-wrap">
                        <img src="<?= BASE_URL ?>/<?= sanitize($cat['image']) ?>" alt="<?= sanitize($cat['name']) ?>">
                        <span class="card-tag">Collection</span>
                    </div>
                    <div class="card-content-wrap">
                        <h3 class="heading-2 card-title"><?= sanitize($cat['name']) ?></h3>
                        <p class="card-text"><?= sanitize($cat['description']) ?></p>
                        <div class="card-footer-action">
                            <a href="<?= BASE_URL ?>/categories.php" style="color:var(--brand-primary); font-weight:800;">Explore Catalog &rarr;</a>
                            <a href="<?= BASE_URL ?>/schools/" class="btn btn-primary btn-sm" style="padding:0.35rem 0.85rem; font-size:0.78rem;">Order for School</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== 5. FEATURED UNIFORM PRODUCTS SHOWCASE ==================== -->
<section class="section section-alt">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:3.5rem; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <span class="text-eyebrow">Academic Wardrobe</span>
                <h2 class="display-2" style="margin-top:0.5rem;">Approved Uniform Products</h2>
                <p class="text-lead" style="max-width:560px;">Standard institutional school garments tailored for extended classroom and playground wear.</p>
            </div>
            <a href="<?= BASE_URL ?>/categories.php" class="btn btn-outline">
                <span>View All Products</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>

        <div class="products-portal-grid">
            <?php foreach ($featuredProducts as $prod): ?>
                <div class="product-item-card">
                    <div class="product-thumb-box">
                        <img src="<?= BASE_URL ?>/<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>">
                        <?php if ($prod['is_kit']): ?>
                            <span class="product-mandatory-pill">Complete Kit</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-details">
                        <span style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--brand-muted);"><?= sanitize($prod['category_name'] ?: 'Apparel') ?></span>
                        <h4 class="product-title-text"><?= sanitize($prod['name']) ?></h4>
                        <div class="product-price-tag"><?= formatCurrency($prod['price']) ?></div>
                        <p style="font-size:0.85rem; color:var(--brand-muted); line-height:1.5; margin-bottom:1.25rem;">
                            <?= sanitize($prod['short_description'] ?: $prod['description']) ?>
                        </p>
                        <a href="<?= BASE_URL ?>/schools/" class="btn btn-secondary btn-sm" style="margin-top:auto;">
                            <span>School Specific Order</span>
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

<!-- ==================== 6. CORPORATE GIFTS & MERCHANDISE SHOWCASE ==================== -->
<section class="section">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1.1fr 0.9fr; gap:4rem; align-items:center;">
            <div>
                <span class="text-eyebrow">Corporate Gifting</span>
                <h2 class="display-2" style="margin-top:0.75rem; margin-bottom:1.5rem;">
                    Corporate Gifts That Strengthen Every Connection
                </h2>
                <p class="text-lead" style="margin-bottom:1.5rem;">
                    Make every occasion memorable with thoughtfully curated corporate gifts designed to reflect your brand. From employee appreciation and festive gifting to client welcome kits and event giveaways, we create customized gifting solutions that combine quality, style, and meaningful brand visibility.
                </p>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2.5rem;">
                    <div style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                        <div style="font-size:1.8rem; margin-bottom:0.75rem;">🎁</div>
                        <h4 style="font-weight:800; font-size:1.1rem; margin-bottom:0.4rem; color:var(--brand-dark);">Customized &amp; Branded Gifts</h4>
                        <p style="font-size:0.88rem; color:var(--brand-muted); line-height:1.5; margin:0;">
                            Personalized products that showcase your brand with style and impact.
                        </p>
                    </div>

                    <div style="background:#ffffff; border-radius:var(--radius-lg); padding:1.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                        <div style="font-size:1.8rem; margin-bottom:0.75rem;">🎉</div>
                        <h4 style="font-weight:800; font-size:1.1rem; margin-bottom:0.4rem; color:var(--brand-dark);">Gifts for Every Occasion</h4>
                        <p style="font-size:0.88rem; color:var(--brand-muted); line-height:1.5; margin:0;">
                            Thoughtful gifting solutions for employees, clients, events, and celebrations.
                        </p>
                    </div>
                </div>

                <div style="display:flex; gap:1.25rem; flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>/gifts.php" class="btn btn-primary">Browse Corporate Gifting</a>
                    <a href="<?= BASE_URL ?>/corporate.php" class="btn btn-outline">Explore Executive Polos</a>
                </div>
            </div>

            <div style="position:relative;">
                <div style="border-radius:var(--radius-xl); overflow:hidden; box-shadow:var(--shadow-xl); border:6px solid #ffffff;">
                    <img src="<?= BASE_URL ?>/assets/images/portfolio/1500-x-1500-pixel-Corporate-Gifting.webp" alt="Corporate Gifts" style="width:100%; height:auto; display:block;">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 7. PARTNER SCHOOL DIRECTORY HIGHLIGHT SECTION ==================== -->
<section class="section section-alt">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:3.5rem; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <span class="text-eyebrow">Dedicated Portals</span>
                <h2 class="display-2" style="margin-top:0.5rem;">Partner School Directory</h2>
                <p class="text-lead" style="max-width:540px;">
                    Select your educational institution to access official school kits, grade-specific uniforms, and secure student ordering.
                </p>
            </div>
            <a href="<?= BASE_URL ?>/schools/" class="btn btn-outline">
                <span>View All Registered Schools</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>

        <div class="schools-directory-grid">
            <?php foreach ($schools as $sch): ?>
                <div class="school-card-editorial">
                    <div class="school-card-hero">
                        <img src="<?= BASE_URL ?>/<?= sanitize($sch['banner_image'] ?: 'assets/images/home/540-pixel-x-836-pixel-1.webp') ?>" alt="<?= sanitize($sch['name']) ?>">
                        <div class="school-logo-badge">
                            <img src="<?= BASE_URL ?>/<?= sanitize($sch['logo'] ?: 'assets/images/logo/cropped-favicon-1-96x96.png') ?>" alt="<?= sanitize($sch['name']) ?> Logo">
                        </div>
                    </div>
                    <div class="school-card-body">
                        <h3 class="school-name"><?= sanitize($sch['name']) ?></h3>
                        <div class="school-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span><?= sanitize($sch['location'] ?: $sch['city']) ?></span>
                        </div>
                        <p class="school-description"><?= sanitize($sch['description']) ?></p>
                        
                        <div class="school-card-cta-group">
                            <a href="<?= BASE_URL ?>/schools/<?= sanitize($sch['slug']) ?>/" class="btn btn-outline btn-sm">
                                View School
                            </a>
                            <a href="<?= BASE_URL ?>/schools/<?= sanitize($sch['slug']) ?>/login/" class="btn btn-primary btn-sm">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== 8. STEP-BY-STEP PROCESS TO BRING YOUR CUSTOM IDEAS TO LIFE ==================== -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow dark">How Does It Work</span>
            <h2 class="display-2 section-title">Step-by-Step Process to Bring Your Custom Ideas to Life</h2>
            <p class="text-lead">From conceptual fabric swatches to student doorstep fulfillment.</p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:2.5rem;">
            <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-lg); padding: 2.5rem; position: relative;">
                <div style="font-family: var(--font-display); font-size: 3.5rem; font-weight: 800; color: var(--brand-primary); opacity: 0.9; line-height: 1; margin-bottom: 1rem;">01</div>
                <h3 class="heading-2" style="color:#ffffff; margin-bottom:1rem;">Share Your Requirements</h3>
                <p style="color:#94A3B8; font-size:0.95rem; line-height:1.65;">
                    Tell us the products, quantity, school grade levels, and branding or crest details you need. We design tailored digital mockups and color swatch swatches.
                </p>
            </div>

            <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-lg); padding: 2.5rem; position: relative;">
                <div style="font-family: var(--font-display); font-size: 3.5rem; font-weight: 800; color: var(--brand-primary); opacity: 0.9; line-height: 1; margin-bottom: 1rem;">02</div>
                <h3 class="heading-2" style="color:#ffffff; margin-bottom:1rem;">Design &amp; Production</h3>
                <p style="color:#94A3B8; font-size:0.95rem; line-height:1.65;">
                    We manufacture high-quality branded products in our Tirupur textile facility with computerized Tajima embroidery, anti-shrinkage washes, and precision dyeing.
                </p>
            </div>

            <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-lg); padding: 2.5rem; position: relative;">
                <div style="font-family: var(--font-display); font-size: 3.5rem; font-weight: 800; color: var(--brand-primary); opacity: 0.9; line-height: 1; margin-bottom: 1rem;">03</div>
                <h3 class="heading-2" style="color:#ffffff; margin-bottom:1rem;">Quality Check &amp; Delivery</h3>
                <p style="color:#94A3B8; font-size:0.95rem; line-height:1.65;">
                    After multi-point inspection for seam strength, zip resilience, and colorfastness, your order is packed and delivered on time directly to parents or school campuses.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 9. WHAT MAKES US DIFFERENT (WHY CHOOSE ONBOLT) ==================== -->
<section class="section">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow">Our Distinct Strengths</span>
            <h2 class="display-2 section-title">What Makes Us Different</h2>
            <p class="text-lead">
                Built on rigorous manufacturing discipline, dependable logistics, and deep educational sector expertise.
            </p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:2rem;">
            <!-- Differentiator 1 -->
            <div class="differentiator-card card-tilt">
                <div class="differentiator-num">01</div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:0.75rem;">High-Quality Printing</h3>
                <p style="color:var(--brand-muted); font-size:0.92rem; line-height:1.6; margin:0;">
                    All products are manufactured in our Tirupur factory, ensuring strict quality control, computerized embroidery, and consistent production standards.
                </p>
            </div>

            <!-- Differentiator 2 -->
            <div class="differentiator-card card-tilt">
                <div class="differentiator-num">02</div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:0.75rem;">Complete School Solutions</h3>
                <p style="color:var(--brand-muted); font-size:0.92rem; line-height:1.6; margin:0;">
                    From academic uniforms to bags, socks, house tees, and accessories, we supply every essential school item in one place for effortless procurement.
                </p>
            </div>

            <!-- Differentiator 3 -->
            <div class="differentiator-card card-tilt">
                <div class="differentiator-num">03</div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:0.75rem;">Custom Design Options</h3>
                <p style="color:var(--brand-muted); font-size:0.92rem; line-height:1.6; margin:0;">
                    We create personalized products with crest logos, photos, and unique color combinations tailored specifically to each school or corporate partner.
                </p>
            </div>

            <!-- Differentiator 4 -->
            <div class="differentiator-card card-tilt">
                <div class="differentiator-num">04</div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:0.75rem;">Trusted by 300+ Schools</h3>
                <p style="color:var(--brand-muted); font-size:0.92rem; line-height:1.6; margin:0;">
                    Over 300 leading educational institutions rely on OnBolt for premium apparel, professional branding, reliable parent portal ordering, and dedicated support.
                </p>
            </div>

            <!-- Differentiator 5 -->
            <div class="differentiator-card card-tilt">
                <div class="differentiator-num">05</div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:0.75rem;">Durable &amp; Premium Materials</h3>
                <p style="color:var(--brand-muted); font-size:0.92rem; line-height:1.6; margin:0;">
                    Our products are crafted with long-staple combed cotton and skin-safe non-toxic dyes, designed for enduring comfort and high-frequency laundering.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 10. PORTFOLIO & INSTITUTIONAL SHOWCASE ==================== -->
<section class="section section-alt">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:3.5rem; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <span class="text-eyebrow">Craftsmanship Showcase</span>
                <h2 class="display-2" style="margin-top:0.5rem;">Our Realized Portfolio</h2>
                <p class="text-lead" style="max-width:540px;">Explore authentic student uniform ensembles and corporate branding executed by OnBolt.</p>
            </div>
            <a href="<?= BASE_URL ?>/portfolio.php" class="btn btn-outline">
                <span>View Full Portfolio</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:2rem;">
            <?php foreach ($portfolio as $port): ?>
                <div class="card-tilt" style="border-radius: var(--radius-lg); overflow:hidden; background:#fff; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); cursor:pointer;" onclick="window.location.href='<?= BASE_URL ?>/portfolio.php'">
                    <div style="aspect-ratio:4/3; overflow:hidden; background:var(--brand-surface-alt);">
                        <img src="<?= BASE_URL ?>/<?= sanitize($port['image']) ?>" alt="<?= sanitize($port['title']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <div style="padding:1.5rem;">
                        <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--brand-primary); letter-spacing:0.05em;"><?= sanitize($port['category']) ?></span>
                        <h4 style="font-size:1.15rem; font-weight:800; margin-top:0.35rem; margin-bottom:0.5rem;"><?= sanitize($port['title']) ?></h4>
                        <p style="font-size:0.88rem; color:var(--brand-muted);"><?= sanitize($port['client_name']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== 11. SUCCESS STORIES FROM OUR CUSTOMERS (TESTIMONIALS) ==================== -->
<section class="section">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow">Client Endorsements</span>
            <h2 class="display-2 section-title">Success Stories From Our Customers</h2>
            <p class="text-lead">Read how school leaders and corporate administrators experience the OnBolt difference.</p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
            <?php foreach ($testimonials as $t): ?>
                <div class="card-tilt" style="background:#ffffff; padding:2.25rem; border-radius:var(--radius-xl); border:1.5px solid var(--brand-border); box-shadow:var(--shadow-sm); display:flex; flex-direction:column;">
                    <div style="color:var(--brand-primary); font-size:1.3rem; margin-bottom:1rem;">★★★★★</div>
                    <p style="font-size:0.95rem; color:var(--brand-dark-soft); font-style:italic; line-height:1.75; margin-bottom:1.5rem; flex-grow:1;">
                        “<?= sanitize($t['review_text']) ?>”
                    </p>
                    
                    <div class="testimonial-card-author">
                        <img src="<?= BASE_URL ?>/<?= sanitize($t['avatar']) ?>" alt="<?= sanitize($t['author_name']) ?>" class="testimonial-avatar">
                        <div>
                            <div style="font-weight:800; font-size:1.05rem; color:var(--brand-dark);"><?= sanitize($t['author_name']) ?></div>
                            <div style="font-size:0.85rem; color:var(--brand-primary); font-weight:700;"><?= sanitize($t['role_title']) ?></div>
                            <div style="font-size:0.8rem; color:var(--brand-muted);"><?= sanitize($t['company_or_school']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== 12. JOIN NOW FOR SPECIAL DEALS & UPDATES ==================== -->
<section style="padding: 3rem 0; background: linear-gradient(135deg, var(--brand-dark) 0%, #172132 100%);">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:2rem;">
            <div style="max-width:560px;">
                <span class="text-eyebrow" style="background:rgba(255,103,2,0.15); color:var(--brand-primary); border-color:var(--brand-primary); margin-bottom:0.75rem;">Stay Informed</span>
                <h3 class="display-2" style="color:#ffffff; font-size:1.85rem; margin-bottom:0.5rem;">Join Now for Special Deals &amp; Updates</h3>
                <p style="color:#94A3B8; font-size:0.95rem; margin:0;">
                    Get early access to exclusive deals, school uniform production schedules, and new institutional apparel releases.
                </p>
            </div>

            <form action="<?= BASE_URL ?>/api/newsletter.php" method="POST" class="ajax-newsletter-form" style="display:flex; gap:0.75rem; width:100%; max-width:440px;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="email" name="email" required placeholder="Enter institutional email address..." style="padding:0.9rem 1.25rem; border-radius:var(--radius-pill); border:1.5px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.08); color:#fff; font-size:0.92rem; flex-grow:1; outline:none;">
                <button type="submit" class="btn btn-primary" style="padding:0.9rem 1.75rem;">Subscribe</button>
            </form>
        </div>
    </div>
</section>

<!-- ==================== 13. REQUEST INSTITUTIONAL PROPOSAL (CONTACT) ==================== -->
<section class="section" id="contact">
    <div class="container">
        <div style="background:linear-gradient(135deg, var(--brand-dark) 0%, #172132 100%); border-radius:var(--radius-xl); padding:4rem; color:#fff; display:grid; grid-template-columns: 1fr 1fr; gap:4rem; align-items:center; border:2px solid rgba(255,103,2,0.3); box-shadow:var(--shadow-xl);">
            <div>
                <span class="text-eyebrow" style="background:rgba(255,103,2,0.15); color:var(--brand-primary); border-color:var(--brand-primary); margin-bottom:1.25rem;">Direct Partnership</span>
                <h2 class="display-2" style="color:#fff; margin-bottom:1.25rem;">Partner with OnBolt for Your Institution</h2>
                <p style="color:#d1dbee; font-size:1.05rem; line-height:1.65; margin-bottom:2rem;">
                    Whether you require 200 kits or 10,000 complete student uniform bundles, our executive manufacturing desk is at your service.
                </p>
                <div style="display:flex; flex-direction:column; gap:0.9rem;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <span style="color:var(--brand-primary); font-weight:900; font-size:1.1rem;">✓</span>
                        <span>Zero classroom distribution overhead with parent portal delivery</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <span style="color:var(--brand-primary); font-weight:900; font-size:1.1rem;">✓</span>
                        <span>Lab-certified anti-shrinkage combed cotton fabric blends</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <span style="color:var(--brand-primary); font-weight:900; font-size:1.1rem;">✓</span>
                        <span>Complimentary student size sampling session on campus</span>
                    </div>
                </div>
            </div>

            <div style="background:#ffffff; border-radius:var(--radius-lg); padding:2.5rem; color:var(--brand-dark); box-shadow:var(--shadow-xl);">
                <h3 class="heading-2" style="margin-bottom:0.5rem;">Request Institutional Proposal</h3>
                <p style="font-size:0.88rem; color:var(--brand-muted); margin-bottom:1.5rem;">Fill in your details and our team will get in touch within 24 hours.</p>

                <form action="<?= BASE_URL ?>/api/enquiry.php" method="POST" class="ajax-enquiry-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="form-group">
                        <label class="form-label">Full Name / Designation *</label>
                        <input type="text" name="name" required class="form-control" placeholder="Dr. Rajesh / Principal">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Institution / School Name *</label>
                        <input type="text" name="subject" required class="form-control" placeholder="St. Mary's Academy">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" required class="form-control" placeholder="admin@school.org">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" required class="form-control" placeholder="+91 98470 00000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Approximate Student Strength &amp; Requirements</label>
                        <textarea name="message" required class="form-control" placeholder="We have 850 students from Grade 1 to 12. Looking for full kit samples for next academic year."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Submit Proposal Request</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
