<?php
/**
 * OnBolt Digital Experience Platform - Parent & Student Profile
 * URL: /schools/{school-slug}/profile/
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$schoolSlug = trim($_GET['school'] ?? '');
$school = getSchoolBySlug($schoolSlug);

if (!$school) {
    header("Location: " . BASE_URL . "/schools/");
    exit;
}

$parent = requireParentAuth((int)$school['id'], $school['slug']);
$students = getStudentsForParent((int)$parent['id']);
$pdo = getDbConnection();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $err = 'Session security token expired. Please reload.';
    } else {
        $parentName = trim($_POST['parent_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $altPhone = trim($_POST['alt_phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($parentName) || empty($phone)) {
            $err = 'Parent Name and Primary Phone are required.';
        } else {
            $uStmt = $pdo->prepare("UPDATE parents SET parent_name = ?, phone = ?, alt_phone = ?, email = ?, address = ? WHERE id = ?");
            $uStmt->execute([$parentName, $phone, $altPhone, $email, $address, $parent['id']]);

            $_SESSION['parent_name'] = $parentName;
            $_SESSION['parent_phone'] = $phone;
            $_SESSION['parent_email'] = $email;
            $parent['parent_name'] = $parentName;
            $parent['phone'] = $phone;
            $parent['email'] = $email;

            $msg = 'Profile updated successfully!';
            logActivity('update_profile', 'parent_profile', (int)$parent['id'], 'parent', "Parent profile updated");
        }
    }
}

// Fetch current parent row
$pRow = $pdo->prepare("SELECT * FROM parents WHERE id = ?");
$pRow->execute([$parent['id']]);
$parentData = $pRow->fetch();

$pageTitle = 'Parent Profile | ' . sanitize($school['name']);
$activeSchoolSlug = $school['slug'];

require_once __DIR__ . '/../includes/header.php';
?>

<div style="background: var(--brand-surface-alt); padding-top: 120px; padding-bottom: 2rem; border-bottom: 1px solid var(--brand-border);">
    <div class="container">
        <span class="text-eyebrow gold" style="font-size:0.7rem; padding:0.2rem 0.6rem;"><?= sanitize($school['name']) ?></span>
        <h1 class="heading-1" style="margin-top:0.25rem;">Parent Account &amp; Student Roster</h1>
        <p style="color:var(--brand-muted); font-size:0.9rem;">Manage communication details and verified student affiliations.</p>

        <div style="display:flex; gap:1rem; margin-top:2rem; overflow-x:auto;">
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/dashboard/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Overview</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/order/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Browse School Catalog</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/cart/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Cart Requisition</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/orders/" class="btn btn-white btn-sm" style="border-radius:var(--radius-pill);">Order History</a>
            <a href="<?= BASE_URL ?>/schools/<?= $schoolSlug ?>/profile/" class="btn btn-primary btn-sm" style="border-radius:var(--radius-pill);">Student Profiles</a>
        </div>
    </div>
</div>

<section class="section">
    <div class="container" style="max-width:960px;">
        <?php if (!empty($msg)): ?>
            <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
                ✓ <?= sanitize($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
                ⚠️ <?= sanitize($err) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:3rem;">
            <!-- Profile Form -->
            <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2.5rem; box-shadow:var(--shadow-sm);">
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:1.5rem;">Contact Information</h3>

                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="form-group">
                        <label class="form-label">School-Assigned Login ID</label>
                        <input type="text" disabled value="<?= sanitize($parentData['login_id']) ?>" class="form-control" style="background:var(--brand-surface-alt); font-weight:800; color:var(--brand-primary);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Parent / Guardian Name *</label>
                        <input type="text" name="parent_name" required value="<?= sanitize($parentData['parent_name']) ?>" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Primary Mobile Phone *</label>
                        <input type="tel" name="phone" required value="<?= sanitize($parentData['phone']) ?>" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Alternate Phone / WhatsApp</label>
                        <input type="tel" name="alt_phone" value="<?= sanitize($parentData['alt_phone'] ?? '') ?>" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Notification Address</label>
                        <input type="email" name="email" value="<?= sanitize($parentData['email'] ?? '') ?>" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Residential Address</label>
                        <textarea name="address" class="form-control"><?= sanitize($parentData['address'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        Save Updated Profile
                    </button>
                </form>
            </div>

            <!-- Student Cards List -->
            <div>
                <h3 class="heading-2" style="font-size:1.25rem; margin-bottom:1.5rem;">Linked Enrolled Students</h3>
                <div style="display:flex; flex-direction:column; gap:1.25rem;">
                    <?php foreach ($students as $stu): ?>
                        <div style="background:#ffffff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); padding:1.5rem; box-shadow:var(--shadow-sm);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                                <div>
                                    <div style="font-weight:800; font-size:1.15rem; color:var(--brand-dark);"><?= sanitize($stu['student_name']) ?></div>
                                    <div style="font-size:0.85rem; color:var(--brand-primary); font-weight:700;">
                                        <?= sanitize($stu['grade_class']) ?> <?= !empty($stu['section']) ? '- ' . sanitize($stu['section']) : '' ?>
                                    </div>
                                </div>
                                <span class="text-eyebrow" style="font-size:0.7rem; padding:0.2rem 0.5rem;"><?= sanitize($stu['relationship']) ?></span>
                            </div>

                            <div style="border-top:1px solid var(--brand-border); padding-top:0.75rem; font-size:0.82rem; color:var(--brand-muted); display:flex; justify-content:space-between;">
                                <span>Admission: <code><?= sanitize($stu['admission_no']) ?></code></span>
                                <span>Gender: <?= sanitize($stu['gender']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
