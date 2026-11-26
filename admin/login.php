<?php
/**
 * OnBolt Digital Experience Platform - Admin Login
 * URL: /admin/login.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            if (loginAdmin($username, $password)) {
                $redirect = $_GET['redirect'] ?? (BASE_URL . '/admin/');
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid administrative credentials.';
            }
        }
    }
}

$siteLogo = BASE_URL . '/assets/images/logo/onbolt-actual-logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | OnBolt</title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/logo/cropped-favicon-1-96x96.png" type="image/png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=2.0">
</head>
<body style="background: radial-gradient(circle at 50% 30%, rgba(58, 91, 160, 0.15), transparent 70%), #0B132B; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">

<div style="width: 100%; max-width: 440px; background: #ffffff; border-radius: var(--radius-xl); padding: 3rem 2.5rem; box-shadow: var(--shadow-xl); border: 1px solid rgba(255,255,255,0.1);">
    
    <div style="text-align: center; margin-bottom: 2rem;">
        <img src="<?= $siteLogo ?>" alt="OnBolt" style="height: 42px; margin: 0 auto 1.25rem; display: block; max-width: 100%; object-fit: contain;">
        <h1 class="heading-1" style="margin: 0; font-size: 1.65rem;">Admin Portal</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div style="background: rgba(255, 107, 107, 0.12); color: #dc2626; border: 1px solid rgba(255, 107, 107, 0.3); padding: 0.85rem; border-radius: var(--radius-md); font-size: 0.88rem; font-weight: 600; margin-bottom: 1.5rem;">
            ⚠️ <?= sanitize($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus class="form-control" placeholder="Enter your Username">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your Password">
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 0.5rem;">
            <span>Authorize Access</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </button>
    </form>
</div>

</body>
</html>
