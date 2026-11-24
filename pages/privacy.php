<?php
/**
 * OnBolt Digital Experience Platform - Privacy Policy
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pageTitle = 'Privacy Policy | OnBolt';
$pageDesc = 'Learn how OnBolt safeguards student, parent, and institutional data with strict confidentiality.';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <h1 class="display-1" style="margin-bottom:1rem;">Privacy &amp; Data Protection Policy</h1>
        <p class="text-lead">Effective Date: November 2026</p>
    </div>
</div>

<section class="section">
    <div class="container" style="max-width:900px;">
        <div style="background:#fff; padding:3rem; border-radius:var(--radius-lg); border:1px solid var(--brand-border); line-height:1.8; color:var(--brand-dark);">
            <h2 class="heading-2" style="margin-bottom:1rem;">1. Institutional &amp; Student Data Protection</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                OnBolt Consumer India treats student identity and family details with the highest standards of data security. Student names, grades, and parent phone numbers provided for uniform fitting and kit fulfillment are strictly partitioned on an institution-by-institution basis. School A's parent and student credentials can never access or query School B's roster.
            </p>

            <h2 class="heading-2" style="margin-bottom:1rem;">2. Information We Collect</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                We collect parent contact details, child's grade/division, sizing measurements, and shipping addresses exclusively for uniform production, packaging, and delivery tracking. We do not sell or monetize personal student information.
            </p>

            <h2 class="heading-2" style="margin-bottom:1rem;">3. Secure Session Architecture</h2>
            <p style="margin-bottom:1.5rem; color:var(--brand-muted);">
                Authentication sessions utilize HTTP-only, secure, samesite cookies. Passwords and sensitive credentials are encrypted using industry-standard bcrypt hashing.
            </p>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

