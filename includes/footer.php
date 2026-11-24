<?php
/**
 * OnBolt Digital Experience Platform
 * Global Footer Template
 */

declare(strict_types=1);

$contactPhone = getWebsiteSetting('contact_phone', '+91 9946925888');
$contactEmail = getWebsiteSetting('contact_email', 'onboltconsumerindia@gmail.com');
$officeAddress = getWebsiteSetting('office_address', 'OnBolt Consumer India, Technopark Campus Road, Trivandrum, Kerala 695582');
$whatsapp = getWebsiteSetting('whatsapp_order_phone', '918848670739');
$actualLogo = BASE_URL . '/assets/images/logo/onbolt-actual-logo.png';
?>

<footer class="site-footer">
    <div class="container-wide">
        <div class="footer-top-grid">
            <!-- Brand Column -->
            <div class="footer-col">
                <div style="margin-bottom: 1.5rem; display: inline-block; background: #ffffff; padding: 0.6rem 1.25rem; border-radius: var(--radius-md); box-shadow: 0 4px 16px rgba(0,0,0,0.3);">
                    <img src="<?= $actualLogo ?>" alt="OnBolt - Complete Branding Solutions" style="height: 38px; width: auto; object-fit: contain;">
                </div>
                <div style="font-family: var(--font-display); font-size: 0.85rem; font-weight: 800; color: var(--brand-primary); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 1rem;">
                    Complete Branding Solutions
                </div>
                <p style="color: #94A3B8; font-size: 0.94rem; line-height: 1.65; margin-bottom: 1.75rem;">
                    OnBolt is the premier digital apparel &amp; institutional merchandise manufacturer trusted by 300+ schools, colleges, and corporate organizations across South India.
                </p>
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <a href="https://wa.me/<?= $whatsapp ?>?text=Hello%20OnBolt%20Team" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="box-shadow: 0 4px 15px rgba(255, 103, 2, 0.4);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.055-1.928-.487-1.464-.614-2.428-2.091-2.502-2.19-.074-.099-.597-.796-.597-1.517 0-.721.378-1.077.512-1.222.134-.145.293-.182.391-.182.099 0 .197.001.283.006.09.004.21-.035.328.25.12.289.412 1.002.449 1.075.036.074.06.16.012.257-.048.098-.073.159-.144.244-.072.086-.152.191-.217.257-.072.072-.148.15-.064.294.084.144.372.613.799.992.551.488 1.015.639 1.159.711.144.072.228.06.312-.036.084-.096.36-419.456-.563.096-.144.192-.12.324-.072.132.048.841.396.985.468.144.072.24.108.276.168.036.06.036.348-.108.753z"/>
                        </svg>
                        <span>WhatsApp Desk</span>
                    </a>
                    <a href="<?= BASE_URL ?>/schools/" class="btn btn-secondary btn-sm">
                        <span>Partner Portals</span>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4 class="footer-col-title">School Uniforms</h4>
                <ul class="footer-links-list">
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/">All Partner Schools</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/international-school/">Trivandrum International</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/guruchaitanya-vidyalaya/">Guruchaitanya Vidyalaya</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/city-kids-school/">City Kids School</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/offspring-school/">Offspring School</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/schools/ime-vidhyalaya/">IME Vidhyalaya</a></li>
                </ul>
            </div>

            <!-- Solutions -->
            <div class="footer-col">
                <h4 class="footer-col-title">Solutions &amp; Products</h4>
                <ul class="footer-links-list">
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/categories.php">Product Categories</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/portfolio.php">Brand Showcase &amp; Portfolio</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/corporate.php">Corporate Apparel &amp; Polos</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/gifts.php">Executive Gifting Hampers</a></li>
                    <li class="footer-link-item"><a href="<?= BASE_URL ?>/contact.php">Request Institutional Proposal</a></li>
                </ul>
            </div>

            <!-- Office & Connect -->
            <div class="footer-col">
                <h4 class="footer-col-title">Corporate Headquarters</h4>
                <p style="color: #94A3B8; font-size: 0.9rem; margin-bottom: 0.85rem; line-height: 1.5;">
                    <?= $officeAddress ?>
                </p>
                <p style="color: #94A3B8; font-size: 0.9rem; margin-bottom: 0.5rem;">
                    <strong>Phone:</strong> <?= $contactPhone ?>
                </p>
                <p style="color: #94A3B8; font-size: 0.9rem; margin-bottom: 1.5rem;">
                    <strong>Email:</strong> <?= $contactEmail ?>
                </p>

                <!-- Newsletter Form -->
                <form action="<?= BASE_URL ?>/api/newsletter.php" method="POST" class="ajax-newsletter-form" style="display: flex; gap: 0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="email" name="email" required placeholder="Enter institutional email" style="padding: 0.65rem 0.9rem; border-radius: var(--radius-pill); border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); color: #fff; font-size: 0.85rem; flex-grow: 1;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.65rem 1.25rem;">Join</button>
                </form>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <div>
                &copy; <?= date('Y') ?> OnBolt - Complete Branding Solutions. All rights reserved.
            </div>
            <div style="display: flex; gap: 1.5rem;">
                <a href="<?= BASE_URL ?>/privacy.php" style="color: #94A3B8; font-size: 0.85rem;">Privacy Policy</a>
                <a href="<?= BASE_URL ?>/terms.php" style="color: #94A3B8; font-size: 0.85rem;">Terms &amp; Conditions</a>
                <a href="<?= BASE_URL ?>/admin/login.php" style="color: #94A3B8; font-size: 0.85rem;">Staff &amp; Admin Login</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
