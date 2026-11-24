<?php
/**
 * OnBolt Digital Experience Platform - Portfolio
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'portfolio';
$pageTitle = 'Portfolio & Institutional Showcase | OnBolt';
$pageDesc = 'Inspect bespoke uniform ensembles, athletic teamwear, and executive merchandise produced by OnBolt.';

$pdo = getDbConnection();
$portfolio = $pdo->query("SELECT * FROM portfolio WHERE status = 1 ORDER BY sort_order ASC")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Verified Works</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Institutional Portfolio</h1>
        <p class="text-lead" style="max-width:700px;">
            A showcase of custom student uniforms, kindergarten kits, athletic apparel, and bespoke corporate branding executed across South India.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <!-- Interactive Grid -->
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap:2.5rem;">
            <?php foreach ($portfolio as $item): ?>
                <div style="background:#fff; border-radius:var(--radius-lg); overflow:hidden; border:1px solid var(--brand-border); box-shadow:var(--shadow-sm); transition:transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none';this.style.boxShadow='var(--shadow-sm)';">
                    <div style="aspect-ratio:16/11; overflow:hidden; background:var(--brand-surface-alt);">
                        <img src="<?= BASE_URL ?>/<?= sanitize($item['image']) ?>" alt="<?= sanitize($item['title']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <div style="padding:1.75rem;">
                        <span class="text-eyebrow" style="font-size:0.7rem; padding:0.25rem 0.65rem; margin-bottom:0.75rem;"><?= sanitize($item['category']) ?></span>
                        <h3 class="heading-2" style="font-size:1.3rem; margin-top:0.5rem; margin-bottom:0.5rem;"><?= sanitize($item['title']) ?></h3>
                        <p style="font-size:0.9rem; color:var(--brand-muted); margin-bottom:1rem; line-height:1.5;"><?= sanitize($item['description']) ?></p>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:1rem; border-top:1px solid var(--brand-border); font-size:0.85rem; font-weight:700; color:var(--brand-primary);">
                            <span>Client: <?= sanitize($item['client_name']) ?></span>
                            <span><?= sanitize($item['completion_year'] ?? '2026') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

