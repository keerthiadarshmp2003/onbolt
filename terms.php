<?php
/**
 * OnBolt Digital Experience Platform - Terms & Conditions
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Terms & Conditions | OnBolt';
$pageDesc = 'Terms of service, school kit supply policies, sizing exchanges, and institutional agreements.';

require_once __DIR__ . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <h1 class="display-1" style="margin-bottom:1rem;">Terms of Service &amp; Supply</h1>
        <p class="text-lead">Effective Date: November 2026</p>
    </div>
</div>

<section class="section">
    <div class="container" style="max-width:900px;">
        <div style="background:#fff; padding:3rem; border-radius:var(--radius-lg); border:1px solid var(--brand-border); line-height:1.8; color:var(--brand-dark);">
            <h2 class="heading-2" style="margin-bottom:1rem;">1. School Kit Requisitions</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                Uniform requisitions placed through the OnBolt school portals are custom-manufactured in alignment with approved school dress codes. Parents are advised to consult the size charts before finalizing orders.
            </p>

            <h2 class="heading-2" style="margin-bottom:1rem;">2. Size Exchanges &amp; Alterations</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                Unworn uniforms with original tags intact may be exchanged for alternative sizing within 7 business days following delivery, subject to stock availability or immediate exchange camps hosted at the school.
            </p>

            <h2 class="heading-2" style="margin-bottom:1rem;">3. Pricing &amp; Taxes</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                All uniform prices published on school-specific pages are institutional rates approved by school administrations.
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
