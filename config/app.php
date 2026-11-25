<?php
/**
 * OnBolt Digital Platform - Global Application Configuration
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Vercel serverless environment requires sessions to be stored in /tmp (read-only filesystem workaround)
    if (is_dir('/tmp') && is_writable('/tmp')) {
        session_save_path('/tmp');
    }
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.lazy_write', '1');
    session_start();
}

require_once __DIR__ . '/database.php';

// Detect Base URL dynamically with proxy SSL support & cloud host detection
$rawBase = '';
if (getenv('APP_URL')) {
    $rawBase = (string)getenv('APP_URL');
} elseif (getenv('BASE_URL')) {
    $rawBase = (string)getenv('BASE_URL');
} else {
    $isHttps = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $isHttps = true;
    } elseif (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        $isHttps = true;
    }
    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'store.onboltlive.com';

    // Normalize root path: only use /onbolt when on localhost XAMPP
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($requestUri, '/onbolt') !== false || strpos($scriptName, '/onbolt') !== false) {
        $basePath = '/onbolt';
    } else {
        $basePath = '';
    }
    $rawBase = $protocol . $host . $basePath;
}

// Ensure BASE_URL never has trailing slashes or accidental subfolder paths like /schools or /admin
$cleanBase = rtrim($rawBase, '/');
$cleanBase = preg_replace('#/(schools|admin|api|pages)(/.*)?$#i', '', $cleanBase);
define('BASE_URL', rtrim($cleanBase, '/'));
define('ROOT_PATH', dirname(__DIR__));

// Brand Palette (Derived precisely from OnBolt Actual Logo)
define('BRAND_PRIMARY', '#FF6702');    // Electric Bolt Orange
define('BRAND_SECONDARY', '#1E2530');  // Dark Graphite Charcoal
define('BRAND_ACCENT', '#FF4500');     // Intense Flare Accent
define('BRAND_DARK', '#0B111E');       // Obsidian Slate
define('BRAND_LIGHT', '#FDFBF7');      // Warm Clean Studio Light

// CSRF Protection
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// XSS Sanitizer
function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// Global settings loader from database
function getWebsiteSetting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM website_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // fallback
        }
    }
    return $settings[$key] ?? $default;
}

// Format Currency
function formatCurrency(float|int|string $amount): string {
    $symbol = getWebsiteSetting('currency_symbol', '₹');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

// Log admin/parent activity
function logActivity(string $action, string $module, ?int $userId = null, ?string $userType = null, ?string $details = null): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_type, action, module, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $userType ?? 'visitor',
            $action,
            $module,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (Exception $e) {
        // Silently continue if log fails
    }
}
