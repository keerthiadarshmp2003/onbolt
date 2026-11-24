<?php
/**
 * OnBolt Digital Experience Platform - Schools Module
 * Exact match of official https://onboltlive.com/schools/
 * URL: /schools/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();

$activeNav = 'schools';
$pageTitle = 'Schools – On Bolt';
$pageDesc = 'Select your educational institution to order official school uniforms and complete academic kits from OnBolt.';

// Release session lock early for high-concurrency scaling
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Clean header with Track Order button */
.site-header .nav-links,
.site-header .mobile-nav-toggle {
    display: none !important;
}
.site-header .nav-actions {
    display: flex !important;
}
.site-header .nav-actions .btn-primary {
    display: none !important;
}

/* Exact styling matching https://onboltlive.com/schools/ */
.schools-page-hero {
    position: relative;
    background-image: url('<?= BASE_URL ?>/assets/images/breadcrumb-bg.webp');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    padding: 125px 0 65px;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.schools-page-hero-title {
    font-family: 'Urbanist', sans-serif;
    font-size: 46px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    letter-spacing: -0.5px;
}

.schools-page-breadcrumb {
    font-family: 'Urbanist', sans-serif;
    font-size: 15px;
    color: #6B7280;
    font-weight: 500;
}

.schools-page-breadcrumb a {
    color: #6B7280;
    text-decoration: none;
    transition: color 0.2s ease;
}

.schools-page-breadcrumb a:hover {
    color: #FF6702;
}

.schools-page-breadcrumb .sep {
    margin: 0 8px;
    color: #9CA3AF;
}

.schools-page-breadcrumb .active {
    color: #FF6702;
    font-weight: 600;
}

/* Schools Grid Section */
.schools-section {
    padding: 70px 0 90px;
    background: #ffffff;
}

.schools-sec-heading {
    text-align: center;
    margin-bottom: 48px;
}

.schools-sec-heading h2 {
    font-family: 'Urbanist', sans-serif;
    font-size: 38px;
    font-weight: 700;
    color: #000000;
    margin: 0;
    text-transform: capitalize;
}

/* Responsive 2-Column Grid for the 2 Selected Institutions */
.schools-exact-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 28px;
    max-width: 860px;
    margin: 0 auto;
    align-items: stretch;
}

.schools-exact-card {
    background-color: #ffffff;
    border-radius: 20px;
    border: 1.5px solid #E5E7EB;
    padding: 34px 28px 28px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
    text-align: left;
    min-height: auto;
    box-sizing: border-box;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.schools-exact-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.07);
    border-color: #CBD5E1;
}

.schools-exact-card h2 {
    font-family: 'Urbanist', sans-serif;
    font-size: 24px;
    font-weight: 600;
    color: #000000;
    margin: 0 0 16px 0;
    line-height: 1.3;
}

.schools-exact-card p {
    font-family: 'Urbanist', sans-serif;
    font-size: 15px;
    font-weight: 400;
    color: #4B5563;
    line-height: 1.6;
    margin: 0 0 28px 0;
}

.schools-order-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background-color: #FF6702;
    color: #ffffff !important;
    padding: 12px 28px;
    border-radius: 9999px;
    font-family: 'Urbanist', sans-serif;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(255, 103, 2, 0.3);
    transition: all 0.25s ease;
    cursor: pointer;
    border: none;
}

.schools-order-btn:hover {
    background-color: #E55A00;
    box-shadow: 0 6px 18px rgba(255, 103, 2, 0.45);
    transform: translateY(-1px);
}

.schools-order-btn svg {
    transition: transform 0.2s ease;
}

.schools-order-btn:hover svg {
    transform: translateX(3px);
}

/* Responsive Breakpoints */
@media (max-width: 1100px) {
    .schools-exact-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .schools-page-hero {
        padding: 40px 0;
    }
    .schools-section {
        padding: 30px 0 60px !important;
    }
}

@media (max-width: 640px) {
    .schools-exact-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .schools-page-hero-title {
        font-size: clamp(28px, 8vw, 36px);
    }
    .schools-sec-heading h2 {
        font-size: clamp(22px, 6vw, 28px) !important;
    }
    .schools-exact-card {
        padding: 24px 18px;
        min-height: auto;
    }
    .ajax-newsletter-form {
        flex-direction: column !important;
    }
    .ajax-newsletter-form button {
        width: 100% !important;
    }
}
</style>

<!-- ==================== 1. PAGE BREADCRUMB HEADER ==================== -->
<div class="schools-page-hero">
    <div class="container">
        <h1 class="schools-page-hero-title">Schools</h1>
    </div>
</div>

<!-- ==================== 2. SELECT A SCHOOL (SEARCH + LOGO GRID) ==================== -->
<section class="schools-section" style="padding: 40px 0 80px;">
    <div class="container">
        <div class="schools-sec-heading" style="text-align:center; margin-bottom: 25px;">
            <h2 style="font-size: 32px; font-weight: 800; color: #111827; margin-bottom: 8px;">Select Your School</h2>
            <p style="color: #6B7280; font-size: 15px; margin: 0;">Choose your school or pre-school to view approved uniform patterns and academic kits.</p>
        </div>

        <!-- School Search Box -->
        <div style="max-width: 600px; margin: 0 auto 35px; position: relative;">
            <div style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9CA3AF; pointer-events: none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
            <input type="text" id="schoolSearchInput" onkeyup="filterSchoolCards()" placeholder="Search school by name, board (CBSE, IB, CAIE), or keyword..." style="width: 100%; padding: 14px 20px 14px 48px; border-radius: 9999px; border: 1.5px solid #E5E7EB; background: #ffffff; font-family: 'Urbanist', sans-serif; font-size: 15px; font-weight: 600; color: #111827; box-shadow: 0 4px 12px rgba(0,0,0,0.04); outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease;">
        </div>

        <!-- School Cards Grid (Dynamically fetched top-level institutions based on Admin hierarchy) -->
        <div class="schools-exact-grid" id="schoolsGrid">
            <?php
            $cachedTopSchools = getFastCache('top_schools', 120);
            if ($cachedTopSchools !== null) {
                $topSchools = $cachedTopSchools;
            } else {
                $pdo = getDbConnection();
                $topSchools = $pdo->query("SELECT * FROM schools WHERE parent_id IS NULL AND status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
                setFastCache('top_schools', $topSchools, 120);
            }
            foreach ($topSchools as $ts):
            ?>
                <div class="schools-exact-card" data-slug="<?= sanitize($ts['slug']) ?>" data-keywords="<?= sanitize(strtolower($ts['name'] . ' ' . $ts['code'] . ' ' . $ts['location'])) ?>">
                    <div style="margin-bottom: 24px;">
                        <h2 style="font-size: 24px; font-weight: 800; color: #111827; margin: 0; line-height: 1.3;"><?= sanitize($ts['name']) ?></h2>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>/schools/<?= sanitize($ts['slug']) ?>/login/?prompt=1" onclick="rememberSchool('<?= sanitize($ts['slug']) ?>')" class="schools-order-btn" title="Order for <?= sanitize($ts['name']) ?>" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 16px; padding: 14px 28px;">
                            <span>Order Now</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// Filter School Cards based on search input
function filterSchoolCards() {
    const input = document.getElementById('schoolSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.schools-exact-card');
    cards.forEach(card => {
        const text = (card.innerText + ' ' + (card.getAttribute('data-keywords') || '')).toLowerCase();
        if (text.includes(input)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Remember Last Chosen School in localStorage
function rememberSchool(slug) {
    try {
        localStorage.setItem('onbolt_last_school', slug);
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        const last = localStorage.getItem('onbolt_last_school');
        if (last) {
            let targetSlug = last;
            if (['gps-stepping-stones', 'global-public-school-cbse', 'gps-brookes', 'gps-international'].includes(last)) {
                targetSlug = 'global-public-school';
            }
            const card = document.querySelector(`.schools-exact-card[data-slug="${targetSlug}"]`);
            if (card) {
                card.style.borderColor = '#FF6702';
                card.style.boxShadow = '0 10px 25px -5px rgba(255, 103, 2, 0.2)';
                const badge = card.querySelector('.last-selected-badge');
                if (badge) badge.style.display = 'block';
            }
        }
    } catch(e) {}
});
</script>

<!-- ==================== 3. JOIN NOW FOR SPECIAL DEALS & UPDATES ==================== -->
<section style="padding: 3.5rem 0; background: linear-gradient(135deg, var(--brand-dark) 0%, #172132 100%);">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:2rem;">
            <div style="max-width:560px;">
                <span class="text-eyebrow" style="background:rgba(255,103,2,0.15); color:var(--brand-primary); border-color:var(--brand-primary); margin-bottom:0.75rem;">Stay Informed</span>
                <h3 class="display-2" style="color:#ffffff; font-size:1.85rem; margin-bottom:0.5rem; font-family:'Urbanist', sans-serif;">Join Now for Special Deals &amp; Updates</h3>
                <p style="color:#94A3B8; font-size:0.95rem; margin:0; font-family:'Urbanist', sans-serif;">
                    Get early access to exclusive deals, school uniform production schedules, and new institutional apparel releases.
                </p>
            </div>

            <form action="<?= BASE_URL ?>/api/newsletter.php" method="POST" class="ajax-newsletter-form" style="display:flex; gap:0.75rem; width:100%; max-width:440px;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="email" name="email" required placeholder="Enter your email" style="padding:0.9rem 1.25rem; border-radius:var(--radius-pill); border:1.5px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.08); color:#fff; font-size:0.92rem; flex-grow:1; outline:none; font-family:'Urbanist', sans-serif;">
                <button type="submit" class="btn btn-primary" style="padding:0.9rem 1.75rem; font-family:'Urbanist', sans-serif; font-weight:700;">Subscribe</button>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
