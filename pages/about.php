<?php
/**
 * OnBolt Digital Experience Platform - About Us
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'about';
$pageTitle = 'About Us | The OnBolt Story';
$pageDesc = 'Discover OnBolt - delivering world-class fabric technology, ergonomic tailoring, and seamless school kits trusted by 300+ schools.';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Our Heritage &amp; Craft</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Pioneering Academic &amp; Corporate Attire</h1>
        <p class="text-lead" style="max-width:750px;">
            At OnBolt, we bridge the gap between heavy-duty durability and refined comfort. We outfit generations of learners with pride and purpose.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:4rem; align-items:center;">
            <div>
                <span class="text-eyebrow">The OnBolt Mission</span>
                <h2 class="display-2" style="margin-top:0.75rem; margin-bottom:1.25rem;">Quality Without Compromise</h2>
                <p style="color:var(--brand-muted); line-height:1.8; margin-bottom:1.5rem;">
                    Founded in Kerala, OnBolt began with a clear mission: schools deserve an apparel partner who treats student comfort with the same rigor as athletic performance gear. Students spend upwards of 8 hours daily in their uniforms—enduring active play, classroom study, and seasonal weather changes.
                </p>
                <p style="color:var(--brand-muted); line-height:1.8; margin-bottom:2rem;">
                    By sourcing combed long-staple cotton, testing colorfast dyes, and employing reinforced twin-needle stitching on high-stress zones, we ensure that an OnBolt uniform maintains its shape, vibrancy, and soft hand-feel from June until the final term.
                </p>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">
                    <div style="border-left: 3px solid var(--brand-primary); padding-left:1.25rem;">
                        <h4 style="font-size:1.1rem; font-weight:800; margin-bottom:0.35rem;">Zero Shrinkage</h4>
                        <p style="font-size:0.85rem; color:var(--brand-muted);">Pre-washed fabrics prevent fit distortion after laundering.</p>
                    </div>
                    <div style="border-left: 3px solid var(--brand-secondary); padding-left:1.25rem;">
                        <h4 style="font-size:1.1rem; font-weight:800; margin-bottom:0.35rem;">Breathable Weaves</h4>
                        <p style="font-size:0.85rem; color:var(--brand-muted);">High airflow cotton pique and interlock jersey blends.</p>
                    </div>
                </div>
            </div>

            <div>
                <img src="<?= BASE_URL ?>/assets/images/portfolio/330-x-711-pixel-Personal-Gifting-copy.webp" alt="OnBolt Garments" style="border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); width:100%; max-height:550px; object-fit:cover;">
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="section section-alt">
    <div class="container">
        <div class="section-header text-center">
            <span class="text-eyebrow gold">Core Tenets</span>
            <h2 class="display-2 section-title">What Defines Our Standards</h2>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:2.5rem;">
            <div style="background:#fff; border-radius:var(--radius-lg); padding:2.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-size:2rem; margin-bottom:1rem; color:var(--brand-primary);">🏛️</div>
                <h3 class="heading-2" style="margin-bottom:0.75rem;">Institutional Integrity</h3>
                <p style="font-size:0.92rem; color:var(--brand-muted); line-height:1.6;">
                    We respect school branding guidelines with exact Pantone color matching and high-definition computerized crest embroidery.
                </p>
            </div>

            <div style="background:#fff; border-radius:var(--radius-lg); padding:2.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-size:2rem; margin-bottom:1rem; color:var(--brand-secondary);">📱</div>
                <h3 class="heading-2" style="margin-bottom:0.75rem;">Digital Convenience</h3>
                <p style="font-size:0.92rem; color:var(--brand-muted); line-height:1.6;">
                    Our dedicated parent ordering portal gives guardians direct online access to size charts, student kits, and live order tracking.
                </p>
            </div>

            <div style="background:#fff; border-radius:var(--radius-lg); padding:2.5rem; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm);">
                <div style="font-size:2rem; margin-bottom:1rem; color:var(--brand-accent);">🌱</div>
                <h3 class="heading-2" style="margin-bottom:0.75rem;">Responsible Sourcing</h3>
                <p style="font-size:0.92rem; color:var(--brand-muted); line-height:1.6;">
                    Skin-safe non-toxic dyes and OEKO-TEX compliant yarns that ensure maximum comfort for sensitive children's skin.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

