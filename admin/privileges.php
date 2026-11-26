<?php
/**
 * OnBolt Digital Experience Platform - Privilege Settings
 * Requirement 6: Configure and toggle permissions/access rights for each role (Admin, School, etc.)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = requireAdminAuth();
if (($admin['role_slug'] ?? '') !== 'superadmin' && ($admin['role_id'] ?? 0) !== 1 && !adminCan('privileges_manage')) {
    http_response_code(403);
    die('Access Denied: Super Admin privileges are required to configure Privilege Settings.');
}

$pdo = getDbConnection();
$msg = '';
$err = '';

// Handle saving permissions matrix
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_privileges') {
    $postedPerms = $_POST['perms'] ?? []; // format: [role_id => [perm_id => 1]]

    try {
        $pdo->beginTransaction();

        // Configurable roles (excluding Superadmin which always maintains universal access)
        $targetRoleIds = $pdo->query("SELECT id FROM roles WHERE slug IN ('admin', 'school')")->fetchAll(PDO::FETCH_COLUMN);

        $delStmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $insStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

        foreach ($targetRoleIds as $rid) {
            $delStmt->execute([$rid]);
            if (!empty($postedPerms[$rid]) && is_array($postedPerms[$rid])) {
                foreach (array_keys($postedPerms[$rid]) as $pid) {
                    $insStmt->execute([$rid, (int)$pid]);
                }
            }
        }

        $pdo->commit();
        logActivity('update_privileges', 'settings', (int)$admin['id'], 'admin', "Updated role permissions matrix");
        $msg = "Privilege settings and role access rights updated successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "Failed to update privileges: " . $e->getMessage();
    }
}

// Fetch all permissions ordered logically by functional module and id
$permissions = $pdo->query("
    SELECT * FROM permissions 
    ORDER BY FIELD(module, 'Core Management', 'Catalog & Products', 'Parent & Student Accounts', 'Financial Operations', 'Communications & Content', 'Administration & Access'), id ASC
")->fetchAll();

// Fetch roles to display in matrix
$roles = $pdo->query("SELECT * FROM roles WHERE slug IN ('superadmin', 'admin', 'school') ORDER BY FIELD(slug, 'superadmin', 'admin', 'school')")->fetchAll();

// Fetch current role_permissions map
$rolePermRows = $pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$activePermMap = [];
foreach ($rolePermRows as $rp) {
    $activePermMap[$rp['role_id']][$rp['permission_id']] = true;
}

$adminActive = 'privileges';
$adminTitle = 'Privilege Settings (Super Admin)';
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

    <!-- Header Description Card -->
    <div style="background:#ffffff; border:1.5px solid var(--brand-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm); padding:1.75rem 2rem; margin-bottom:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <h3 class="heading-2" style="font-size:1.35rem; margin:0 0 0.3rem 0; color:var(--brand-dark);">
                    🛡️ Role-Based Access Control &amp; Privilege Matrix
                </h3>
                <p style="font-size:0.85rem; color:var(--brand-muted); margin:0;">
                    Configure and toggle fine-grained permission rights for each operational role. Changes take effect across user sessions immediately.
                </p>
            </div>
            <span style="background:#FFF7ED; color:var(--brand-primary); border:1.5px solid #FFEDD5; font-size:0.75rem; font-weight:800; padding:0.35rem 0.85rem; border-radius:9999px; text-transform:uppercase;">
                Super Admin Privilege
            </span>
        </div>
    </div>

    <!-- ==================== PRIVILEGE MATRIX FORM ==================== -->
    <form action="<?= BASE_URL ?>/admin/privileges.php" method="POST">
        <input type="hidden" name="action" value="save_privileges">

        <div style="background:#ffffff; border:1.5px solid var(--brand-border); border-radius:var(--radius-xl); box-shadow:var(--shadow-sm); overflow:hidden; margin-bottom:2rem;">
            <div class="table-scroll-frame" style="max-height:650px; overflow-y:auto; overflow-x:auto; position:relative; border:none; border-radius:0;">
                <table class="admin-table" style="margin:0; border:none; border-radius:0;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--brand-border);">
                            <th style="padding:1.1rem 1.5rem; font-size:0.82rem; font-weight:800; color:var(--brand-dark); width:40%; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">
                                Module &amp; Permission Right
                            </th>
                            <?php foreach ($roles as $r): ?>
                                <th style="padding:1.1rem 1.25rem; font-size:0.82rem; font-weight:800; text-align:center; min-width:180px; position:sticky; top:0; z-index:10; background:var(--brand-surface-alt); box-shadow:0 1px 2px rgba(0,0,0,0.06);">
                                    <div><?= sanitize($r['name']) ?></div>
                                    <div style="font-size:0.7rem; font-weight:600; color:var(--brand-muted); text-transform:none; margin-top:2px;">
                                        <?= $r['slug'] === 'superadmin' ? 'Universal Unrestricted' : ($r['slug'] === 'admin' ? 'Operational Admin' : 'School Level') ?>
                                    </div>
                                    <?php if ($r['slug'] !== 'superadmin'): ?>
                                        <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:0.4rem;">
                                            <button type="button" onclick="toggleColumn(<?= $r['id'] ?>, true)" style="background:none; border:none; font-size:0.68rem; font-weight:700; color:var(--brand-primary); cursor:pointer; text-decoration:underline;">All</button>
                                            <span style="font-size:0.68rem; color:#CBD5E1;">|</span>
                                            <button type="button" onclick="toggleColumn(<?= $r['id'] ?>, false)" style="background:none; border:none; font-size:0.68rem; font-weight:700; color:#64748B; cursor:pointer; text-decoration:underline;">None</button>
                                        </div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentModule = null;
                        foreach ($permissions as $p): 
                            if ($currentModule !== $p['module']):
                                $currentModule = $p['module'];
                        ?>
                            <tr style="background:#F8FAFC; border-top:2px solid #E2E8F0; border-bottom:1px solid #E2E8F0;">
                                <td colspan="<?= count($roles) + 1 ?>" style="padding:0.65rem 1.5rem; font-weight:800; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.06em; color:var(--brand-primary);">
                                    📁 <?= sanitize($currentModule) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <td style="padding:1rem 1.5rem; vertical-align:middle;">
                                    <div style="font-weight:700; color:#0F172A; font-size:0.92rem;">
                                        <?= sanitize($p['name']) ?>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--brand-muted); margin-top:2px;">
                                        <?= sanitize($p['description']) ?>
                                    </div>
                                </td>

                                <?php foreach ($roles as $r): ?>
                                    <td style="padding:1rem 1.25rem; text-align:center; vertical-align:middle;">
                                        <?php if ($r['slug'] === 'superadmin'): ?>
                                            <!-- Superadmin always has full access -->
                                            <span style="display:inline-flex; align-items:center; gap:4px; font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:0.25rem 0.65rem; border-radius:9999px;">
                                                ✓ Full Access
                                            </span>
                                        <?php else: ?>
                                            <?php $isChecked = !empty($activePermMap[$r['id']][$p['id']]); ?>
                                            <label style="display:inline-flex; align-items:center; justify-content:center; cursor:pointer; margin:0; user-select:none;">
                                                <input type="checkbox" 
                                                       name="perms[<?= $r['id'] ?>][<?= $p['id'] ?>]" 
                                                       value="1" 
                                                       class="perm-checkbox-role-<?= $r['id'] ?>"
                                                       <?= $isChecked ? 'checked' : '' ?> 
                                                       style="width:20px; height:20px; accent-color:#FF6702; cursor:pointer;">
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Save Toolbar -->
            <div style="background:var(--brand-surface-alt); padding:1.25rem 2rem; border-top:1px solid var(--brand-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div style="font-size:0.8rem; color:var(--brand-muted);">
                    Click <strong>"Save Privilege Settings"</strong> to persist and apply updated permissions.
                </div>
                <button type="submit" class="btn btn-primary" style="padding:0.7rem 2rem; font-weight:800; font-size:0.95rem; border-radius:var(--radius-md); box-shadow:0 3px 12px rgba(255,103,2,0.35);">
                    ✓ Save Privilege Settings
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function toggleColumn(roleId, checkState) {
    const checkboxes = document.querySelectorAll('.perm-checkbox-role-' + roleId);
    checkboxes.forEach(cb => {
        cb.checked = checkState;
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
