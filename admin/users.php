<?php
/**
 * OnBolt Digital Experience Platform - Super Admin User Management
 * Requirement 3: User Creation Form & Created Users Table
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (($admin['role_slug'] ?? '') !== 'superadmin' && ($admin['role_id'] ?? 0) !== 1 && !adminCan('users_manage')) {
    http_response_code(403);
    die('Access Denied: Super Admin privileges are required to access User Management.');
}

$pdo = getDbConnection();
$adminName = sanitize($admin['full_name']);
$adminRole = sanitize($admin['role_name']);

$msg = '';
$err = '';

// Fetch available roles (Superadmin, Admin, School)
$roles = $pdo->query("SELECT id, name, slug FROM roles WHERE slug IN ('superadmin', 'admin', 'school') ORDER BY FIELD(slug, 'superadmin', 'admin', 'school')")->fetchAll();

// Fetch active schools for the dropdown
$schools = $pdo->query("SELECT id, name, code, parent_id, has_sub_schools FROM schools WHERE status = 1 ORDER BY name ASC")->fetchAll();

// Handle User Actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $schoolId = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;

        if (empty($username) || empty($password) || $roleId <= 0) {
            $err = 'Username, Password, and Role are mandatory.';
        } elseif (strlen($password) < 4) {
            $err = 'Password must be at least 4 characters.';
        } else {
            // Check if username already exists
            $check = $pdo->prepare("SELECT id FROM admins WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $check->execute([$username]);
            if ($check->fetch()) {
                $err = "Username '{$username}' is already taken. Please choose another.";
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $username));
                    $email = $username . '@onbolt.in';

                    $stmt = $pdo->prepare("INSERT INTO admins (role_id, school_id, username, full_name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                    $stmt->execute([$roleId, $schoolId, $username, $fullName, $email, $hash]);
                    $newUserId = (int)$pdo->lastInsertId();

                    logActivity('create_user', 'users', (int)$admin['id'], 'admin', "Created user '{$username}' (#{$newUserId}) with role #{$roleId}");
                    $msg = "User '{$username}' created successfully!";
                } catch (Exception $e) {
                    $err = 'Failed to create user: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $schoolId = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;

        if ($userId <= 0 || empty($username) || $roleId <= 0) {
            $err = 'Invalid user details provided.';
        } else {
            // Check for duplicate username
            $check = $pdo->prepare("SELECT id FROM admins WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
            $check->execute([$username, $userId]);
            if ($check->fetch()) {
                $err = "Username '{$username}' is already taken by another account.";
            } else {
                try {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET username = ?, password_hash = ?, role_id = ?, school_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$username, $hash, $roleId, $schoolId, $userId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE admins SET username = ?, role_id = ?, school_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$username, $roleId, $schoolId, $userId]);
                    }

                    logActivity('update_user', 'users', (int)$admin['id'], 'admin', "Updated user #{$userId} ({$username})");
                    $msg = "User '{$username}' updated successfully!";
                } catch (Exception $e) {
                    $err = 'Failed to update user: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === (int)$admin['id']) {
            $err = 'Security protection: You cannot delete your own currently logged-in Super Admin account.';
        } elseif ($userId > 0) {
            try {
                $check = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
                $check->execute([$userId]);
                $uName = $check->fetchColumn() ?: "ID #{$userId}";

                $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$userId]);
                logActivity('delete_user', 'users', (int)$admin['id'], 'admin', "Deleted user account '{$uName}' (#{$userId})");
                $msg = "User '{$uName}' deleted successfully!";
            } catch (Exception $e) {
                $err = 'Failed to delete user: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all created users with school and role information
$schoolFilter = (int)($_GET['school_id'] ?? 0);
$subSchoolFilter = (int)($_GET['sub_school_id'] ?? 0);

// Main schools list (only main schools: standalone single schools and multi-school groups)
$mainSchools = $pdo->query("SELECT id, name, has_sub_schools FROM schools WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC")->fetchAll();

// Under schools list (only sub-schools / campuses with their parent reference)
$underSchools = $pdo->query("SELECT s.id, s.name, s.parent_id, p.name as parent_name FROM schools s LEFT JOIN schools p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL AND s.parent_id > 0 ORDER BY s.name ASC")->fetchAll();

// Check if selected main school has sub-schools
$selectedMainHasSubs = false;
if ($schoolFilter > 0) {
    foreach ($mainSchools as $ms) {
        if ((int)$ms['id'] === $schoolFilter && !empty($ms['has_sub_schools'])) {
            $selectedMainHasSubs = true;
            break;
        }
    }
}

$usersQuery = "SELECT a.*, r.name as role_name, r.slug as role_slug, s.name as school_name, s.code as school_code, s.parent_id 
               FROM admins a 
               JOIN roles r ON a.role_id = r.id 
               LEFT JOIN schools s ON a.school_id = s.id 
               WHERE 1=1";

if ($subSchoolFilter > 0) {
    $usersQuery .= " AND a.school_id = {$subSchoolFilter}";
} elseif ($schoolFilter > 0) {
    $usersQuery .= " AND (a.school_id = {$schoolFilter} OR s.parent_id = {$schoolFilter})";
}

$usersQuery .= " ORDER BY a.id DESC";
$createdUsers = $pdo->query($usersQuery)->fetchAll();

$adminActive = 'users';
$adminTitle = 'User Management (Super Admin)';
require_once __DIR__ . '/header.php';
?>

<div style="max-width: 1200px; margin: 0 auto;">

    <!-- Alerts -->
    <?php if (!empty($msg)): ?>
        <div style="background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1.5px solid rgba(34, 197, 94, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>✓</span> <?= sanitize($msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1.5px solid rgba(239, 68, 68, 0.3); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>⚠️</span> <?= sanitize($err) ?>
        </div>
    <?php endif; ?>

    <!-- ==================== 1. USER CREATION FORM (Requirement 3) ==================== -->
    <div style="background:#ffffff; border:1.5px solid var(--brand-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm); padding:2rem 2.25rem; margin-bottom:2.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid #F1F5F9; padding-bottom:1rem;">
            <div>
                <h3 class="heading-2" style="font-size:1.35rem; margin:0 0 0.25rem 0; color:var(--brand-dark);">
                    👤 Create New User Account
                </h3>
                <p style="font-size:0.85rem; color:var(--brand-muted); margin:0;">
                    Assign credentials, institutional school affiliations, and access roles.
                </p>
            </div>
            <span style="background:rgba(255,103,2,0.1); color:var(--brand-primary); font-size:0.75rem; font-weight:800; padding:0.3rem 0.8rem; border-radius:9999px; text-transform:uppercase;">
                Super Admin Privilege
            </span>
        </div>

        <form action="<?= BASE_URL ?>/admin/users.php" method="POST">
            <input type="hidden" name="action" value="create_user">

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
                <!-- School Name Dropdown -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem; color:var(--brand-dark);">
                        School Name <span style="font-size:0.75rem; color:var(--brand-muted);">(Optional for Superadmin)</span>
                    </label>
                    <select name="school_id" class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem; border-radius:var(--radius-md);">
                        <option value="">-- All Schools / Global System Level --</option>
                        <?php foreach ($schools as $sch): ?>
                            <option value="<?= $sch['id'] ?>">
                                <?= sanitize($sch['name']) ?> <?= !empty($sch['code']) ? '(' . sanitize($sch['code']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Username Input -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem; color:var(--brand-dark);">
                        Username *
                    </label>
                    <input type="text" name="username" required placeholder="e.g. school_admin1, desk_tvm" class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem; border-radius:var(--radius-md);">
                </div>

                <!-- Password Input -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem; color:var(--brand-dark);">
                        Password *
                    </label>
                    <input type="password" name="password" required placeholder="Enter secure password" class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem; border-radius:var(--radius-md);">
                </div>

                <!-- Roles Dropdown (Superadmin, Admin, School) -->
                <div class="form-group">
                    <label class="form-label" style="font-weight:700; font-size:0.85rem; color:var(--brand-dark);">
                        Roles *
                    </label>
                    <select name="role_id" required class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem; border-radius:var(--radius-md); font-weight:700; color:var(--brand-primary);">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $r['slug'] === 'admin' ? 'selected' : '' ?>>
                                <?= sanitize($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary" style="padding:0.65rem 1.75rem; font-weight:800; border-radius:var(--radius-md); box-shadow:0 3px 10px rgba(255,103,2,0.3);">
                    + Create User Account
                </button>
            </div>
        </form>
    </div>

    <!-- ==================== 2. CREATED USERS TABLE (Requirement 3) ==================== -->
    <div style="background:#ffffff; border:1.5px solid var(--brand-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm); overflow:hidden;">
        <div style="padding:1.5rem 2rem; border-bottom:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <h3 class="heading-2" style="font-size:1.25rem; margin:0 0 0.2rem 0; color:var(--brand-dark);">
                    Created Users Directory (<?= count($createdUsers) ?>)
                </h3>
                <p style="font-size:0.8rem; color:var(--brand-muted); margin:0;">
                    All registered accounts, affiliated institutions, and permission levels.
                </p>
            </div>

            <form action="" method="GET" style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; margin:0;">
                <!-- Main Schools Filter -->
                <select name="school_id" id="users_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:140px;" onchange="handleMainSchoolUserChange(this)">
                    <option value="">All Schools</option>
                    <?php foreach ($mainSchools as $s): ?>
                        <option value="<?= $s['id'] ?>" data-has-subs="<?= (int)$s['has_sub_schools'] ?>" <?= $schoolFilter === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= sanitize($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Under Schools Filter -->
                <select name="sub_school_id" id="users_sub_school_id_select" class="form-control" style="font-size:0.82rem; padding:0.35rem 0.65rem; border-radius:var(--radius-md); width:145px;" onchange="this.form.submit()" <?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'disabled' : '' ?>>
                    <option value=""><?= ($schoolFilter > 0 && !$selectedMainHasSubs) ? 'No Under Schools' : 'All Under Schools' ?></option>
                    <?php foreach ($underSchools as $sub): ?>
                        <?php 
                            if ($schoolFilter > 0 && (int)$sub['parent_id'] !== $schoolFilter) {
                                continue;
                            }
                        ?>
                        <option value="<?= $sub['id'] ?>" data-parent="<?= (int)$sub['parent_id'] ?>" <?= $subSchoolFilter === (int)$sub['id'] ? 'selected' : '' ?>>
                            <?= sanitize($sub['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($schoolFilter > 0 || $subSchoolFilter > 0): ?>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline btn-sm" style="padding:0.35rem 0.65rem; font-size:0.8rem;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-scroll-frame" style="max-height:520px; overflow-y:auto; overflow-x:auto; position:relative; border-radius:0; border:none; border-top:1px solid #F1F5F9;">
            <table class="admin-table" style="margin:0; border:none; border-radius:0;">
                <thead>
                    <tr>
                        <th style="padding:0.85rem 1.25rem; font-size:0.75rem; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">School Name</th>
                        <th style="padding:0.85rem 1.25rem; font-size:0.75rem; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Username</th>
                        <th style="padding:0.85rem 1.25rem; font-size:0.75rem; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Role</th>
                        <th style="padding:0.85rem 1.25rem; font-size:0.75rem; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Created Date</th>
                        <th style="padding:0.85rem 1.25rem; font-size:0.75rem; text-align:right; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($createdUsers)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:2rem; color:var(--brand-muted);">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($createdUsers as $u): ?>
                            <tr>
                                <td style="padding:1rem 1.25rem; font-weight:700;">
                                    <?php if (!empty($u['school_name'])): ?>
                                        <span style="color:#0F172A;"><?= sanitize($u['school_name']) ?></span>
                                        <?php if (!empty($u['school_code'])): ?>
                                            <span style="font-size:0.72rem; color:var(--brand-muted); margin-left:4px;">(<?= sanitize($u['school_code']) ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#64748B; font-weight:600; font-size:0.82rem; font-style:italic;">All Schools (Global)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:1rem 1.25rem;">
                                    <strong style="color:var(--brand-primary); font-size:0.92rem;"><?= sanitize($u['username']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$admin['id']): ?>
                                        <span style="font-size:0.68rem; background:#DCFCE7; color:#15803D; font-weight:800; padding:2px 6px; border-radius:9999px; margin-left:6px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:1rem 1.25rem;">
                                    <?php
                                    $roleBadgeStyle = 'background:#DBEAFE; color:#1D4ED8; border:1px solid #BFDBFE;';
                                    if ($u['role_slug'] === 'superadmin') {
                                        $roleBadgeStyle = 'background:#FEF3C7; color:#B45309; border:1px solid #FDE68A;';
                                    } elseif ($u['role_slug'] === 'school') {
                                        $roleBadgeStyle = 'background:#F3E8FF; color:#7E22CE; border:1px solid #DDD6FE;';
                                    }
                                    ?>
                                    <span style="display:inline-block; font-size:0.75rem; font-weight:800; padding:0.25rem 0.65rem; border-radius:9999px; <?= $roleBadgeStyle ?>">
                                        <?= sanitize($u['role_name']) ?>
                                    </span>
                                </td>
                                <td style="padding:1rem 1.25rem; font-size:0.82rem; color:var(--brand-muted);">
                                    <?= date('d M Y, h:i A', strtotime($u['created_at'])) ?>
                                </td>
                                <td style="padding:1rem 1.25rem; text-align:right; white-space:nowrap;">
                                    <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                        <button type="button" class="btn btn-outline btn-sm" style="padding:0.3rem 0.65rem; font-size:0.75rem; font-weight:700;" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                                            'id' => $u['id'],
                                            'username' => $u['username'],
                                            'role_id' => $u['role_id'],
                                            'school_id' => $u['school_id'] ?: ''
                                        ])) ?>)">
                                            Edit
                                        </button>

                                        <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                            <form action="<?= BASE_URL ?>/admin/users.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete user <?= htmlspecialchars($u['username']) ?>?');" style="margin:0; display:inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm" style="padding:0.3rem 0.65rem; font-size:0.75rem; font-weight:700; color:#DC2626; border:1px solid #FCA5A5; background:#FEF2F2; border-radius:var(--radius-md); cursor:pointer;">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== EDIT USER MODAL ==================== -->
<div id="edit-user-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:99999; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(3px);">
    <div style="background:#ffffff; border-radius:var(--radius-xl); max-width:520px; width:100%; border:1.5px solid var(--brand-border); box-shadow:var(--shadow-xl); overflow:hidden; animation:modalPop 0.2s ease;">
        <div style="padding:1.25rem 1.75rem; border-bottom:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center; background:#FFF9F5;">
            <h4 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--brand-dark);">
                ✏️ Edit User Profile
            </h4>
            <button type="button" onclick="closeEditUserModal()" style="background:transparent; border:none; color:#64748B; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" style="padding:1.5rem 1.75rem;">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit-user-id" value="">

            <div class="form-group" style="margin-bottom:1.2rem;">
                <label class="form-label" style="font-weight:700; font-size:0.85rem;">School Name</label>
                <select name="school_id" id="edit-user-school" class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem;">
                    <option value="">-- All Schools / Global System Level --</option>
                    <?php foreach ($schools as $sch): ?>
                        <option value="<?= $sch['id'] ?>">
                            <?= sanitize($sch['name']) ?> <?= !empty($sch['code']) ? '(' . sanitize($sch['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1.2rem;">
                <label class="form-label" style="font-weight:700; font-size:0.85rem;">Username *</label>
                <input type="text" name="username" id="edit-user-username" required class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem;">
            </div>

            <div class="form-group" style="margin-bottom:1.2rem;">
                <label class="form-label" style="font-weight:700; font-size:0.85rem;">New Password <span style="font-size:0.75rem; color:var(--brand-muted);">(Leave blank to keep existing)</span></label>
                <input type="password" name="password" placeholder="Enter new password to change" class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem;">
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label" style="font-weight:700; font-size:0.85rem;">Role *</label>
                <select name="role_id" id="edit-user-role" required class="form-control" style="font-size:0.88rem; padding:0.6rem 0.85rem; font-weight:700; color:var(--brand-primary);">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= sanitize($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeEditUserModal()" class="btn btn-outline" style="padding:0.6rem 1.25rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem; font-weight:800;">✓ Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
function handleMainSchoolUserChange(mainSelect) {
    const subSelect = document.getElementById('users_sub_school_id_select');
    if (subSelect) {
        subSelect.value = '';
    }
    mainSelect.form.submit();
}

function openEditUserModal(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-username').value = user.username;
    document.getElementById('edit-user-school').value = user.school_id || '';
    document.getElementById('edit-user-role').value = user.role_id;
    document.getElementById('edit-user-modal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('edit-user-modal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
