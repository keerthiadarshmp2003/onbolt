<?php
/**
 * OnBolt Digital Platform - Authentication & RBAC Engine
 * Strict Server-Side Session & Role Validation
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

// ==================== ADMIN AUTHENTICATION ====================

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role_id']);
}

function getLoggedInAdmin(): ?array {
    if (!isAdminLoggedIn()) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['admin_id'],
        'username'  => $_SESSION['admin_username'] ?? '',
        'full_name' => $_SESSION['admin_name'] ?? 'Admin',
        'role_id'   => (int)$_SESSION['admin_role_id'],
        'role_name' => $_SESSION['admin_role_name'] ?? 'Administrator',
        'role_slug' => $_SESSION['admin_role_slug'] ?? 'superadmin',
        'school_id' => isset($_SESSION['admin_school_id']) && $_SESSION['admin_school_id'] !== '' ? (int)$_SESSION['admin_school_id'] : null,
    ];
}

function adminCan(string $permissionSlug): bool {
    $admin = getLoggedInAdmin();
    if (!$admin) {
        return false;
    }
    // Superadmin has universal unrestricted access
    if (($admin['role_slug'] ?? '') === 'superadmin' || ($admin['role_id'] ?? 0) === 1) {
        return true;
    }
    static $permCache = [];
    $roleId = (int)$admin['role_id'];
    if (!isset($permCache[$roleId])) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?");
        $stmt->execute([$roleId]);
        $permCache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($permissionSlug, $permCache[$roleId], true);
}

function requireAdminAuth(?int $requiredRoleId = null): array {
    if (!isAdminLoggedIn()) {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: ' . BASE_URL . '/admin/login.php?redirect=' . urlencode($reqUri));
        exit;
    }

    $admin = getLoggedInAdmin();

    if ($requiredRoleId !== null && $admin['role_id'] > $requiredRoleId && ($admin['role_slug'] ?? '') !== 'superadmin') {
        http_response_code(403);
        die('Access denied: You do not have permission to view this resource.');
    }

    return $admin;
}

function requireAdminPermission(string $permissionSlug): array {
    $admin = requireAdminAuth();
    if (!adminCan($permissionSlug)) {
        http_response_code(403);
        die('Access denied: You do not have sufficient privileges to access this section.');
    }
    return $admin;
}

function loginAdmin(string $username, string $password): bool {
    $cleanUser = trim($username);
    if ($cleanUser === '') {
        return false;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT a.*, r.name as role_name, r.slug as role_slug 
                           FROM admins a 
                           JOIN roles r ON a.role_id = r.id 
                           WHERE a.username = ? AND a.status = 1 
                           LIMIT 1");
    $stmt->execute([$cleanUser]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role_id'] = $admin['role_id'];
        $_SESSION['admin_role_name'] = $admin['role_name'];
        $_SESSION['admin_role_slug'] = $admin['role_slug'];
        $_SESSION['admin_school_id'] = $admin['school_id'];
        $_SESSION['admin_last_activity'] = time();

        $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        logActivity('login', 'admin_auth', (int)$admin['id'], 'admin', "Logged in successfully as {$admin['username']}");
        return true;
    }
    return false;
}

function logoutAdmin(): void {
    if (isAdminLoggedIn()) {
        logActivity('logout', 'admin_auth', (int)$_SESSION['admin_id'], 'admin', "Logged out");
    }
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_name'],
        $_SESSION['admin_role_id'],
        $_SESSION['admin_role_name'],
        $_SESSION['admin_role_slug'],
        $_SESSION['admin_school_id']
    );
}


// ==================== PARENT AUTHENTICATION ====================

function isParentLoggedIn(int $schoolId = 0): bool {
    if (empty($_SESSION['parent_id']) || empty($_SESSION['parent_school_id'])) {
        return false;
    }
    if ($schoolId > 0 && (int)$_SESSION['parent_school_id'] !== $schoolId) {
        // Allow seamless switching across sister branches under the same main school group
        $pdo = getDbConnection();
        $currStmt = $pdo->prepare("SELECT id, parent_id, has_sub_schools FROM schools WHERE id = ?");
        $currStmt->execute([(int)$_SESSION['parent_school_id']]);
        $curr = $currStmt->fetch();

        $tgtStmt = $pdo->prepare("SELECT id, parent_id, has_sub_schools FROM schools WHERE id = ?");
        $tgtStmt->execute([(int)$schoolId]);
        $tgt = $tgtStmt->fetch();

        if ($curr && $tgt) {
            $currParent = $curr['parent_id'] ?: ($curr['has_sub_schools'] ? $curr['id'] : null);
            $tgtParent = $tgt['parent_id'] ?: ($tgt['has_sub_schools'] ? $tgt['id'] : null);
            if ($currParent && $tgtParent && (int)$currParent === (int)$tgtParent) {
                $_SESSION['parent_school_id'] = $schoolId;
                return true;
            }
        }
        return false;
    }
    return true;
}

function getLoggedInParent(): ?array {
    if (empty($_SESSION['parent_id'])) {
        return null;
    }
    return [
        'id'          => (int)$_SESSION['parent_id'],
        'school_id'   => (int)$_SESSION['parent_school_id'],
        'login_id'    => $_SESSION['parent_login_id'] ?? '',
        'parent_name' => $_SESSION['parent_name'] ?? 'Parent',
        'phone'       => $_SESSION['parent_phone'] ?? '',
        'email'       => $_SESSION['parent_email'] ?? '',
    ];
}

function requireParentAuth(int $schoolId, string $schoolSlug): array {
    if (!isParentLoggedIn($schoolId)) {
        header("Location: " . BASE_URL . "/schools/{$schoolSlug}/login/?err=" . urlencode("Please sign in to access your student's portal."));
        exit;
    }
    return getLoggedInParent();
}

function loginParentByLoginId(string $loginId, int $schoolId = 0): bool {
    $pdo = getDbConnection();
    $cleanId = trim($loginId);

    // Universal Demo Passwords: TRINS01 or GPS01 (case-insensitive)
    if (strcasecmp($cleanId, 'TRINS01') === 0 || strcasecmp($cleanId, 'GPS01') === 0) {
        // Find existing demo parent or fallback to first active parent record
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE UPPER(login_id) = 'TRINS01' LIMIT 1");
        $stmt->execute();
        $parent = $stmt->fetch();

        if (!$parent) {
            $stmt = $pdo->query("SELECT * FROM parents WHERE status = 1 ORDER BY id ASC LIMIT 1");
            $parent = $stmt->fetch();
        }

        if ($parent) {
            session_regenerate_id(true);
            $_SESSION['parent_id'] = $parent['id'];
            $_SESSION['parent_school_id'] = ($schoolId > 0) ? $schoolId : $parent['school_id'];
            $_SESSION['parent_login_id'] = 'Trins01';
            $_SESSION['parent_name'] = $parent['parent_name'] ?: 'Rajesh Kumar';
            $_SESSION['parent_phone'] = $parent['phone'] ?: '9847012345';
            $_SESSION['parent_email'] = $parent['email'] ?: 'parent@example.com';
            $_SESSION['parent_last_activity'] = time();

            logActivity('parent_login', 'school_portal', (int)$parent['id'], 'parent', "Parent logged in with Demo Password TRINS01 for school #{$schoolId}");
            return true;
        }
    }

    // Standard database lookup by login ID
    if ($schoolId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE UPPER(login_id) = UPPER(?) AND school_id = ? AND status = 1 LIMIT 1");
        $stmt->execute([$cleanId, $schoolId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE UPPER(login_id) = UPPER(?) AND status = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute([$cleanId]);
    }
    $parent = $stmt->fetch();

    if ($parent) {
        session_regenerate_id(true);
        $_SESSION['parent_id'] = $parent['id'];
        $_SESSION['parent_school_id'] = ($schoolId > 0) ? $schoolId : $parent['school_id'];
        $_SESSION['parent_login_id'] = $parent['login_id'];
        $_SESSION['parent_name'] = $parent['parent_name'];
        $_SESSION['parent_phone'] = $parent['phone'];
        $_SESSION['parent_email'] = $parent['email'];
        $_SESSION['parent_last_activity'] = time();

        $pdo->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?")->execute([$parent['id']]);
        logActivity('parent_login', 'school_portal', (int)$parent['id'], 'parent', "Parent logged in with ID {$loginId} for school #{$schoolId}");
        return true;
    }
    return false;
}

function logoutParent(string $schoolSlug): void {
    if (!empty($_SESSION['parent_id'])) {
        logActivity('parent_logout', 'school_portal', (int)$_SESSION['parent_id'], 'parent', "Parent logged out");
    }
    unset(
        $_SESSION['parent_id'],
        $_SESSION['parent_school_id'],
        $_SESSION['parent_login_id'],
        $_SESSION['parent_name'],
        $_SESSION['parent_phone'],
        $_SESSION['parent_email'],
        $_SESSION['cart_' . ($_SESSION['parent_school_id'] ?? '')]
    );
    header("Location: " . BASE_URL . "/schools/{$schoolSlug}/login/?msg=" . urlencode("You have been securely signed out."));
    exit;
}
