<?php
/**
 * OnBolt Digital Experience Platform - Customised Gifts
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'gifts';
$pageTitle = 'Customised Gifts & Executive Hampers | OnBolt';
$pageDesc = 'Bespoke corporate gifting solutions, welcome kits, insulated drinkware, laser-engraved sets, and student recognition awards.';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Executive Keepsakes</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Customised Gifts &amp; Hampers</h1>
        <p class="text-lead" style="max-width:700px;">
            Memorable tokens of appreciation for faculty, corporate partners, student achievers, and alumni celebrations.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:4rem; align-items:center;">
            <div>
                <img src="<?= BASE_URL ?>/assets/images/home/630px-x-420px-Corporate-Gifting-1.webp" alt="Customised Hampers" style="border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); width:100%;">
            </div>

            <div>
                <span class="text-eyebrow">Curated Hampers</span>
                <h2 class="display-2" style="margin-top:0.75rem; margin-bottom:1.25rem;">Bespoke Institutional Keepsakes</h2>
                <p style="color:var(--brand-muted); line-height:1.7; margin-bottom:1.5rem;">
                    From annual school anniversary mementos to employee recognition awards, OnBolt designs end-to-end gift boxes complete with custom laser-engraved vacuum flasks, executive vegan leather planners, matte metal pens, and branded presentation packaging.
                </p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
                    <div style="background:#fff; padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--brand-border);">
                        <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;">Welcome Kits</h4>
                        <p style="font-size:0.85rem; color:var(--brand-muted);">Curated employee and student orientation hampers.</p>
                    </div>
                    <div style="background:#fff; padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--brand-border);">
                        <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;">Drinkware &amp; Tech</h4>
                        <p style="font-size:0.85rem; color:var(--brand-muted);">Insulated thermos, ceramic mugs, and wireless chargers.</p>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/contact.php" class="btn btn-secondary">Request Gifting Proposal</a>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

