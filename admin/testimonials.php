<?php
/**
 * OnBolt Digital Experience Platform - Testimonials Management
 * URL: /admin/testimonials.php
 */

declare(strict_types=1);

$adminActive = 'testimonials';
$adminTitle = 'Client & Institutional Endorsements';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $author = trim($_POST['author_name']);
        $role = trim($_POST['role_title'] ?? '');
        $org = trim($_POST['company_or_school'] ?? '');
        $review = trim($_POST['review_text'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));

        if (empty($author) || empty($review)) {
            $err = 'Author Name and Review text are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO testimonials (author_name, role_title, company_or_school, rating, review_text, status, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$author, $role, $org, $rating, $review]);
                $msg = 'Endorsement saved successfully!';
                logActivity('create_testimonial', 'testimonials', (int)$admin['id'], 'admin', "Added testimonial from {$author}");
            } catch (Exception $e) {
                $err = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
        $msg = 'Testimonial removed.';
    }
}

$testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC")->fetchAll();
?>

<?php if (!empty($msg)): ?>
    <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3); padding: 1rem; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 2rem;">
        ✓ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2.5rem; align-items:flex-start;">
    <div>
        <h3 class="heading-2" style="font-size:1.3rem; margin-bottom:1.25rem;">Published Endorsements (<?= count($testimonials) ?>)</h3>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Author &amp; Title</th>
                        <th>Institution</th>
                        <th>Rating</th>
                        <th>Review Snippet</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td>
                                <div style="font-weight:800;"><?= sanitize($t['author_name']) ?></div>
                                <div style="font-size:0.8rem; color:var(--brand-muted);"><?= sanitize($t['role_title']) ?></div>
                            </td>
                            <td style="font-size:0.85rem; font-weight:600;"><?= sanitize($t['company_or_school']) ?></td>
                            <td style="color:var(--brand-secondary); font-size:1rem;"><?= str_repeat('★', (int)$t['rating']) ?></td>
                            <td style="font-size:0.85rem; color:var(--brand-muted); max-width:260px;"><?= sanitize($t['review_text']) ?></td>
                            <td style="text-align:right;">
                                <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($t['author_name']) ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--brand-accent); padding:0.25rem 0.5rem; font-size:0.75rem;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Add Client Endorsement</h3>

        <form action="" method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Author Name *</label>
                <input type="text" name="author_name" required class="form-control" placeholder="Enter your Author Name">
            </div>

            <div class="form-group">
                <label class="form-label">Designation / Role</label>
                <input type="text" name="role_title" class="form-control" placeholder="Enter your Designation">
            </div>

            <div class="form-group">
                <label class="form-label">School / Corporate Entity</label>
                <input type="text" name="company_or_school" class="form-control" placeholder="Enter your Institution / School Name">
            </div>

            <div class="form-group">
                <label class="form-label">Rating (1 to 5 Stars)</label>
                <select name="rating" class="form-control">
                    <option value="5" selected>5 Stars ★★★★★</option>
                    <option value="4">4 Stars ★★★★☆</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Endorsement Text *</label>
                <textarea name="review_text" required class="form-control" placeholder="Enter your Testimonial Quote"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                Publish Testimonial
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
