<?php
/**
 * OnBolt Digital Experience Platform - School Directory Module
 * URL: /schools/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$activeNav = 'directory';
$pageTitle = 'Partner School Directory | Official Uniform Portals';
$pageDesc = 'Select your educational institution to access official school kits, grade-specific uniforms, and parent portal ordering.';

$allSchools = getAllSchools(true);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="padding-top: 140px; padding-bottom: 60px; background: linear-gradient(180deg, var(--brand-primary-light) 0%, var(--brand-light) 100%);">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <span class="text-eyebrow gold" style="margin-bottom:1rem;">Verified Portals</span>
                <h1 class="display-1" style="margin-bottom:1.5rem;">Partner School Directory</h1>
                <p class="text-lead" style="max-width:650px;">
                    Select your institution to access official dress codes, complete school kits, and authorized parent ordering.
                </p>
            </div>

            <!-- Instant Search Input -->
            <div style="width:100%; max-width:380px;">
                <div style="position:relative;">
                    <input type="text" id="school-search-box" placeholder="Search school name or location…" class="form-control" style="padding-left:2.75rem; border-radius:var(--radius-pill); box-shadow:var(--shadow-sm);">
                    <svg style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--brand-muted);" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="schools-directory-grid" id="schools-container">
            <?php foreach ($allSchools as $sch): ?>
                <div class="school-card-editorial school-item-card" data-name="<?= strtolower(sanitize($sch['name'])) ?>" data-city="<?= strtolower(sanitize($sch['city'] . ' ' . $sch['location'])) ?>">
                    <div class="school-card-hero">
                        <img src="<?= BASE_URL ?>/<?= sanitize($sch['banner_image'] ?: 'assets/images/home/540-pixel-x-836-pixel-1.webp') ?>" alt="<?= sanitize($sch['name']) ?>">
                        <div class="school-logo-badge">
                            <img src="<?= BASE_URL ?>/<?= sanitize($sch['logo'] ?: 'assets/images/logo/cropped-favicon-1-96x96.png') ?>" alt="<?= sanitize($sch['name']) ?> Logo">
                        </div>
                    </div>
                    <div class="school-card-body">
                        <h3 class="school-name"><?= sanitize($sch['name']) ?></h3>
                        <div class="school-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span><?= sanitize($sch['location'] ?: $sch['city']) ?></span>
                        </div>
                        <p class="school-description"><?= sanitize($sch['description']) ?></p>

                        <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                            <span style="font-size:0.75rem; background:var(--brand-primary-light); color:var(--brand-primary); font-weight:700; padding:0.2rem 0.6rem; border-radius:4px;">
                                <?= $sch['product_count'] ?> Active Products
                            </span>
                            <span style="font-size:0.75rem; background:var(--brand-secondary-light); color:#92580c; font-weight:700; padding:0.2rem 0.6rem; border-radius:4px;">
                                Parent Portal Active
                            </span>
                        </div>
                        
                        <div class="school-card-cta-group">
                            <a href="<?= BASE_URL ?>/schools/<?= sanitize($sch['slug']) ?>/" class="btn btn-outline btn-sm">
                                View School
                            </a>
                            <a href="<?= BASE_URL ?>/schools/<?= sanitize($sch['slug']) ?>/login/" class="btn btn-secondary btn-sm">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="no-schools-found" style="display:none; text-align:center; padding:4rem 2rem;">
            <p style="font-size:1.2rem; color:var(--brand-muted);">No partner schools match your search query.</p>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('school-search-box');
    const items = document.querySelectorAll('.school-item-card');
    const emptyNotice = document.getElementById('no-schools-found');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            let matches = 0;
            items.forEach(card => {
                const name = card.getAttribute('data-name');
                const city = card.getAttribute('data-city');
                if (name.includes(query) || city.includes(query)) {
                    card.style.display = 'flex';
                    matches++;
                } else {
                    card.style.display = 'none';
                }
            });
            emptyNotice.style.display = matches === 0 ? 'block' : 'none';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
