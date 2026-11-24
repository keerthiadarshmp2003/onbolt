<?php
/**
 * OnBolt Digital Experience Platform
 * Global Header Template with 3-Second Cinematic Preloader & Actual Logo
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? sanitize($pageTitle) . ' | OnBolt' : 'OnBolt | Complete Branding Solutions & Custom School Uniforms';
$pageDesc = isset($pageDesc) ? sanitize($pageDesc) : 'OnBolt - Complete Branding Solutions. High-performance school uniforms, bespoke academic kits, and corporate merchandise trusted by 300+ institutions.';
$siteLogo = BASE_URL . '/assets/images/logo/onbolt-actual-logo.png';
$favicon = BASE_URL . '/' . getWebsiteSetting('site_favicon', 'assets/images/logo/cropped-favicon-1-96x96.png');

// Get active school cart count if applicable
$activeSchoolId = $activeSchoolId ?? ($_SESSION['parent_school_id'] ?? 0);
$cartSummary = $activeSchoolId ? getCartTotals((int)$activeSchoolId) : ['count' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDesc ?>">
    
    <!-- Open Graph / Meta -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDesc ?>">
    <meta property="og:image" content="<?= $siteLogo ?>">
    
    <link rel="icon" href="<?= $favicon ?>" type="image/png">
    
    <!-- Fonts (Urbanist, Plus Jakarta Sans, Space Grotesk) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700;800&family=Urbanist:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">

    <!-- Design System Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= filemtime(__DIR__ . '/../assets/css/main.css') ?>">
    <style>
        .site-header .nav-wrapper {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 1.5rem !important;
        }
        .site-header .nav-links {
            display: flex !important;
            align-items: center !important;
            gap: clamp(0.65rem, 1.35vw, 1.55rem) !important;
            flex-wrap: nowrap !important;
            white-space: nowrap !important;
        }
        .site-header .nav-link {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            line-height: 1 !important;
            font-size: clamp(0.82rem, 0.92vw, 0.95rem) !important;
        }
    </style>
    
    <!-- Three.js & GSAP Animation Suite -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js" defer></script>

    <!-- Client-Side Vector PDF Generator (jsPDF & AutoTable) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" defer></script>
    
    <!-- Main Interactive Script -->
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>" defer></script>
</head>
<body>

<?php if (!empty($activeNav) && $activeNav === 'home'): ?>
<!-- 3-SECOND CINEMATIC PRELOADER WITH ACTUAL ONBOLT LOGO (HOMEPAGE ONLY) -->
<div id="onbolt-preloader">
    <div class="preloader-bg-glow"></div>
    <div class="preloader-speed-lines">
        <span></span><span></span><span></span><span></span>
    </div>
    <div class="preloader-content-box">
        <div class="preloader-logo-wrap">
            <img src="<?= BASE_URL ?>/assets/images/logo/onbolt-logo-white.png" alt="OnBolt - Complete Branding Solutions" class="preloader-logo-img">
        </div>
        <div class="preloader-tagline">Complete Branding Solutions</div>
        <div class="preloader-progress-track">
            <div class="preloader-progress-bar"></div>
        </div>
        <div class="preloader-meta-row">
            <span class="preloader-status-text">INITIALIZING PLATFORM</span>
            <span class="preloader-percent-num">00%</span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($hideSiteHeader)): ?>
<header class="site-header">
    <div class="container-wide">
        <div class="nav-wrapper">
            <a href="<?= BASE_URL ?>/" class="brand-logo" aria-label="OnBolt Home">
                <img src="<?= $siteLogo ?>" alt="OnBolt - Complete Branding Solutions" style="height: 44px; width: auto; object-fit: contain;">
            </a>

            <nav class="nav-links">
                <a href="<?= BASE_URL ?>/" class="nav-link <?= empty($activeNav) || $activeNav === 'home' ? 'active' : '' ?>">Home</a>
                <a href="<?= BASE_URL ?>/about.php" class="nav-link <?= ($activeNav ?? '') === 'about' ? 'active' : '' ?>">About Us</a>
                <a href="<?= BASE_URL ?>/categories.php" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">Categories</a>
                <a href="<?= BASE_URL ?>/portfolio.php" class="nav-link <?= ($activeNav ?? '') === 'portfolio' ? 'active' : '' ?>">Portfolio</a>
                <a href="<?= BASE_URL ?>/schools/" class="nav-link <?= ($activeNav ?? '') === 'schools' ? 'active' : '' ?>">Schools</a>
                <a href="<?= BASE_URL ?>/schools/directory.php" class="nav-link <?= ($activeNav ?? '') === 'directory' ? 'active' : '' ?>">School Directory</a>
                <a href="<?= BASE_URL ?>/schools/track-order.php" class="nav-link <?= ($activeNav ?? '') === 'track' ? 'active' : '' ?>">Track Order</a>
                <a href="<?= BASE_URL ?>/corporate.php" class="nav-link <?= ($activeNav ?? '') === 'corporate' ? 'active' : '' ?>">Corporate Wear</a>
                <a href="<?= BASE_URL ?>/gifts.php" class="nav-link <?= ($activeNav ?? '') === 'gifts' ? 'active' : '' ?>">Customised Gifts</a>
                <a href="<?= BASE_URL ?>/contact.php" class="nav-link <?= ($activeNav ?? '') === 'contact' ? 'active' : '' ?>">Contact</a>
            </nav>

            <div class="nav-actions">
                <a href="<?= BASE_URL ?>/schools/track-order.php" class="btn btn-outline btn-sm" style="white-space:nowrap; border-radius:9999px; font-weight:700;">
                    <span>📦 Track Order</span>
                </a>
                <a href="<?= BASE_URL ?>/schools/" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                    <span>School Order</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>

                <button class="mobile-nav-toggle" aria-label="Toggle Navigation Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>
