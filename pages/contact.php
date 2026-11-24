<?php
/**
 * OnBolt Digital Experience Platform - Contact Us
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$activeNav = 'contact';
$pageTitle = 'Contact Us | Institutional & School Desk';
$pageDesc = 'Get in touch with OnBolt Consumer India for school uniform tenders, corporate inquiries, and parent support.';

$contactPhone = getWebsiteSetting('contact_phone', '+91 9946925888');
$contactEmail = getWebsiteSetting('contact_email', 'onboltconsumerindia@gmail.com');
$officeAddress = getWebsiteSetting('office_address', 'OnBolt Consumer India, Technopark Campus Road, Trivandrum, Kerala 695582');
$whatsapp = getWebsiteSetting('whatsapp_order_phone', '918848670739');

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <span class="text-eyebrow gold" style="margin-bottom:1rem;">Communication Desk</span>
        <h1 class="display-1" style="margin-bottom:1.5rem;">Connect with OnBolt</h1>
        <p class="text-lead" style="max-width:700px;">
            Whether you represent a school board seeking a uniform partnership or require order support, our team is ready to assist.
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:4rem;">
            <!-- Contact Info -->
            <div>
                <span class="text-eyebrow" style="margin-bottom:1rem;">Headquarters</span>
                <h2 class="display-2" style="margin-bottom:1.5rem;">We're Here to Help</h2>
                <p style="color:var(--brand-muted); line-height:1.7; margin-bottom:2.5rem;">
                    Our manufacturing facility and design studio are located near Technopark, Trivandrum. Institutional visits and sampling consultations are scheduled on all business days.
                </p>

                <div style="display:flex; flex-direction:column; gap:1.75rem; margin-bottom:2.5rem;">
                    <div style="display:flex; gap:1rem; align-items:flex-start;">
                        <div style="width:44px; height:44px; border-radius:50%; background:var(--brand-primary-light); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0;">📍</div>
                        <div>
                            <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;">Address</h4>
                            <p style="font-size:0.92rem; color:var(--brand-muted); line-height:1.5;"><?= $officeAddress ?></p>
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; align-items:flex-start;">
                        <div style="width:44px; height:44px; border-radius:50%; background:var(--brand-secondary-light); color:#92580c; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0;">📞</div>
                        <div>
                            <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;">Telephone Support</h4>
                            <p style="font-size:0.92rem; color:var(--brand-muted);"><?= $contactPhone ?></p>
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; align-items:flex-start;">
                        <div style="width:44px; height:44px; border-radius:50%; background:var(--brand-accent-light); color:var(--brand-accent); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0;">✉️</div>
                        <div>
                            <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:0.25rem;">Direct Email</h4>
                            <p style="font-size:0.92rem; color:var(--brand-muted);"><?= $contactEmail ?></p>
                        </div>
                    </div>
                </div>

                <a href="https://wa.me/<?= $whatsapp ?>?text=Hello%20OnBolt%20Support" target="_blank" rel="noopener" class="btn btn-secondary">
                    Chat with Us on WhatsApp
                </a>
            </div>

            <!-- Inquiry Form -->
            <div style="background:#ffffff; padding:3rem; border-radius:var(--radius-xl); border:1px solid var(--brand-border); box-shadow:var(--shadow-xl);">
                <h3 class="heading-2" style="margin-bottom:0.5rem;">Send Us a Message</h3>
                <p style="font-size:0.9rem; color:var(--brand-muted); margin-bottom:2rem;">Institutional tenders, uniform quotations, or general queries.</p>

                <form action="<?= BASE_URL ?>/api/enquiry.php" method="POST" class="ajax-enquiry-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" required class="form-control" placeholder="Your Name">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" required class="form-control" placeholder="you@domain.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+91 00000 00000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="School Uniform Proposal / Order Query">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message *</label>
                        <textarea name="message" required class="form-control" placeholder="How can we assist you?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Transmit Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

