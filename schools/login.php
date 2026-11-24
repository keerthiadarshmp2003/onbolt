<?php
/**
 * OnBolt Digital Experience Platform - Parent Portal Login
 * Exact layout matching Lovable design specification:
 * - Fullscreen pastel background
 * - Centered minimal white card
 * - OnBolt logo, "Parent Login", "Enter your login ID to order your child's school kit."
 * - "Login ID" field and "Sign in" button with lock icon
 * - No site navbar, no footer, no other clutter
 * 
 * URL: /schools/{school-slug}/login/ or /login
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$schoolSlug = trim($_GET['school'] ?? '');
if (empty($schoolSlug)) {
    $schoolSlug = 'international-school';
}

$school = getSchoolBySlug($schoolSlug);
if (!$school) {
    $pdo = getDbConnection();
    $school = $pdo->query("SELECT * FROM schools WHERE status = 1 ORDER BY id ASC LIMIT 1")->fetch();
}

$schoolId = (int)($school['id'] ?? 1);

// Reset parent session when prompt or reauth is requested
if (isset($_GET['prompt']) || isset($_GET['reauth'])) {
    unset(
        $_SESSION['parent_id'],
        $_SESSION['parent_school_id'],
        $_SESSION['parent_login_id'],
        $_SESSION['parent_name'],
        $_SESSION['parent_phone'],
        $_SESSION['parent_email']
    );
}

$error = $_GET['err'] ?? '';
$success = $_GET['msg'] ?? '';

// Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Session expired. Please try submitting again.';
    } else {
        $loginId = trim($_POST['login_id'] ?? '');
        // If left blank, fallback to TRINS01 for seamless one-click submission
        if (empty($loginId)) {
            $loginId = 'TRINS01';
        }

        if (loginParentByLoginId($loginId, $schoolId)) {
            // Successfully authenticated -> Navigate directly to product page
            header("Location: " . BASE_URL . "/schools/{$school['slug']}/order/");
            exit;
        } else {
            $error = 'Invalid Login ID. Please enter demo password TRINS01 or GPS01';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Login | OnBolt</title>
    
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/logo/cropped-favicon-1-96x96.png" type="image/png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Urbanist', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F8FAFC;
            background-image: url('<?= BASE_URL ?>/assets/images/breadcrumb-bg.webp');
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
            padding: 24px;
            color: #1E2530;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 18px;
            padding: 42px 36px 38px;
            box-shadow: 0 14px 38px rgba(0, 0, 0, 0.05), 0 2px 8px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(226, 232, 240, 0.85);
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            display: inline-block;
            margin-bottom: 24px;
        }

        .login-logo img {
            max-height: 48px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .login-title {
            font-family: 'Urbanist', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #1E2530;
            margin: 0 0 8px 0;
            letter-spacing: -0.01em;
        }

        .login-subtitle {
            font-family: 'Urbanist', sans-serif;
            font-size: 14px;
            font-weight: 400;
            color: #64748B;
            margin: 0 0 28px 0;
            line-height: 1.5;
        }

        .login-form {
            text-align: left;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-family: 'Urbanist', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #1E2530;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 8px;
            font-family: 'Urbanist', sans-serif;
            font-size: 14.5px;
            font-weight: 500;
            color: #1E2530;
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            border-color: #FF6702;
            box-shadow: 0 0 0 3px rgba(255, 103, 2, 0.12);
        }

        .submit-btn {
            width: 100%;
            height: 46px;
            background: #FF6702;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-family: 'Urbanist', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 12px rgba(255, 103, 2, 0.25);
            margin-top: 6px;
        }

        .submit-btn:hover {
            background: #E55A00;
            box-shadow: 0 6px 16px rgba(255, 103, 2, 0.35);
        }

        .submit-btn:active {
            transform: scale(0.99);
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
                border-radius: 16px;
            }
            .login-title {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- Logo -->
        <div class="login-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo/onbolt-actual-logo.png" alt="OnBolt - Complete Branding Solutions">
        </div>

        <!-- Title & Subtitle -->
        <h1 class="login-title">Parent Login</h1>
        <p class="login-subtitle">Enter your login ID to order your child's school kit.</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="" method="POST" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="form-group">
                <label for="login_id" class="form-label">Login ID</label>
                <input type="text" 
                       id="login_id" 
                       name="login_id" 
                       required 
                       autofocus 
                       class="form-input" 
                       value="<?= htmlspecialchars($_POST['login_id'] ?? 'TRINS01') ?>"
                       placeholder="Enter Login ID (e.g. TRINS01)" 
                       autocomplete="off">
            </div>

            <button type="submit" class="submit-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Sign in</span>
            </button>
        </form>
    </div>

</body>
</html>