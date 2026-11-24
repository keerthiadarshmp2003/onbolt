<?php
/**
 * OnBolt Digital Experience Platform - Parent Account Management
 * URL: /admin/parents.php
 */

declare(strict_types=1);

$adminActive = 'parents';
$adminTitle = 'Parent Accounts & Login ID Directory';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $schoolId = (int)$_POST['school_id'];
        $loginId = strtoupper(trim($_POST['login_id']));
        $parentName = trim($_POST['parent_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($loginId) || empty($parentName) || empty($phone)) {
            $err = 'Login ID, Parent Name and Phone are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO parents (school_id, login_id, parent_name, phone, email, address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$schoolId, $loginId, $parentName, $phone, $email, $address]);
                $msg = "Parent account '{$loginId}' created successfully!";
                logActivity('create_parent', 'parents', (int)$admin['id'], 'admin', "Created parent {$loginId}");
            } catch (Exception $e) {
                $err = 'Error saving parent: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM parent_students WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM parents WHERE id = ?")->execute([$id]);
            $msg = 'Parent account removed.';
            logActivity('delete_parent', 'parents', (int)$admin['id'], 'admin', "Deleted parent #{$id}");
        } catch (Exception $e) {
            $err = 'Cannot delete parent with existing orders.';
        }
    }
}

$schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT p.*, s.name as school_name,
        (SELECT COUNT(*) FROM parent_students ps WHERE ps.parent_id = p.id) as student_count,
        (SELECT COUNT(*) FROM orders o WHERE o.parent_id = p.id) as order_count
        FROM parents p 
        LEFT JOIN schools s ON p.school_id = s.id WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (p.login_id LIKE " . $pdo->quote("%{$search}%") . " 
              OR p.parent_name LIKE " . $pdo->quote("%{$search}%") . " 
              OR p.phone LIKE " . $pdo->quote("%{$search}%") . ")";
}
$sql .= " ORDER BY p.id DESC";
$parentsList = $pdo->query($sql)->fetchAll();
?>

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

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:2.5rem; align-items:flex-start;">
    
    <!-- Parent List -->
    <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 class="heading-2" style="font-size:1.3rem;">Authorized Parent Portals (<?= count($parentsList) ?>)</h3>
            
            <form action="" method="GET" style="display:flex; gap:0.5rem;">
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search Login ID/Phone…" class="form-control" style="padding:0.4rem 0.6rem; font-size:0.85rem; width:180px;">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>

        <div style="background:#fff; border-radius:var(--radius-lg); border:1px solid var(--brand-border); overflow:hidden; box-shadow:var(--shadow-sm);">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Login ID</th>
                        <th>Parent Name &amp; Phone</th>
                        <th>Assigned School</th>
                        <th>Students</th>
                        <th>Orders</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parentsList)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--brand-muted);">No parents found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($parentsList as $p): ?>
                            <tr>
                                <td style="font-weight:800; color:var(--brand-primary); font-family:monospace; font-size:0.95rem;">
                                    <?= sanitize($p['login_id']) ?>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?= sanitize($p['parent_name']) ?></div>
                                    <div style="font-size:0.8rem; color:var(--brand-muted);"><?= sanitize($p['phone']) ?> &bull; <?= sanitize($p['email'] ?? '') ?></div>
                                </td>
                                <td style="font-size:0.85rem; font-weight:600;"><?= sanitize($p['school_name'] ?: 'School') ?></td>
                                <td style="font-weight:700;"><?= $p['student_count'] ?></td>
                                <td style="font-weight:700; color:var(--brand-dark);"><?= $p['order_count'] ?></td>
                                <td style="text-align:right;">
                                    <form action="" method="POST" data-confirm="delete" data-item-name="<?= htmlspecialchars($p['full_name']) ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--brand-accent); padding:0.25rem 0.5rem; font-size:0.75rem;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Parent Form -->
    <div style="background:#ffffff; border-radius:var(--radius-xl); border:1px solid var(--brand-border); padding:2rem; box-shadow:var(--shadow-sm);">
        <h3 class="heading-2" style="font-size:1.2rem; margin-bottom:1.25rem;">Register New Parent Account</h3>

        <form action="" method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">School Affiliation *</label>
                <select name="school_id" required class="form-control">
                    <?php foreach ($schools as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Assigned Login ID *</label>
                <input type="text" name="login_id" required class="form-control" placeholder="e.g. TIS202610" style="text-transform:uppercase; font-weight:700;">
            </div>

            <div class="form-group">
                <label class="form-label">Parent / Guardian Name *</label>
                <input type="text" name="parent_name" required class="form-control" placeholder="Parent Full Name">
            </div>

            <div class="form-group">
                <label class="form-label">Primary Mobile Phone *</label>
                <input type="tel" name="phone" required class="form-control" placeholder="+91 98470 00000">
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="parent@example.com">
            </div>

            <div class="form-group">
                <label class="form-label">Residential Address</label>
                <textarea name="address" class="form-control" placeholder="Flat/House No, Residential Area"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                Create Parent Account
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
