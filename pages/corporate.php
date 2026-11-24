<?php
/**
 * OnBolt Digital Experience Platform - Corporate Attire & Merchandise
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'corporate';
$pageTitle = 'Corporate Wear & Promotional Apparel | OnBolt';
$pageDesc = 'Engineered corporate polos, tech-stretch jackets, branded event tees, and executive uniform collections.';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Enterprise Solutions</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Corporate Apparel &amp; Merchandise</h1>
        <p class="text-lead" style="max-width:700px;">
            Elevate team identity with premium corporate apparel, breathable athletic teamwear, and executive branded merchandise crafted for longevity.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:4rem; align-items:center; margin-bottom:5rem;">
            <div>
                <span class="text-eyebrow">Precision Craft</span>
                <h2 class="display-2" style="margin-top:0.75rem; margin-bottom:1.25rem;">Custom Polos, T-Shirts &amp; Executive Wear</h2>
                <p style="color:var(--brand-muted); line-height:1.7; margin-bottom:1.5rem;">
                    Whether preparing for an annual tech summit, onboarding hundreds of engineers, or curating an executive boardroom uniform, OnBolt produces custom corporate wear tailored to your brand identity.
                </p>
                <ul style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:2rem; font-weight:600; color:var(--brand-dark);">
                    <li style="display:flex; align-items:center; gap:0.75rem;"><span style="color:var(--brand-primary);">✓</span> 220–260 GSM combed cotton pique &amp; dry-fit athletic polyesters</li>
                    <li style="display:flex; align-items:center; gap:0.75rem;"><span style="color:var(--brand-primary);">✓</span> High-density Japanese Tajima computerized logo embroidery</li>
                    <li style="display:flex; align-items:center; gap:0.75rem;"><span style="color:var(--brand-primary);">✓</span> Non-curling rib collars and reinforced placket construction</li>
                    <li style="display:flex; align-items:center; gap:0.75rem;"><span style="color:var(--brand-primary);">✓</span> Custom branded neck tags and eco-friendly polybag packing</li>
                </ul>
                <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary">Request Corporate Catalog</a>
            </div>

            <div>
                <img src="<?= BASE_URL ?>/assets/images/portfolio/307-x-398-pixel-Corporate-Gifting.webp" alt="Corporate Gifting" style="border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); width:100%;">
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

