<?php
/**
 * OnBolt Digital Platform - Vercel Serverless Master Router
 */

if (isset($_GET['test'])) {
    header('Content-Type: text/plain');
    echo "HELLO ONBOLT - PHP RUNTIME IS WORKING!\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "\n";
    exit;
}

// Enable detailed error output so any issues are immediately visible in browser
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Enable temporary session directory for serverless environments
if (is_dir('/tmp') && is_writable('/tmp')) {
    @session_save_path('/tmp');
}

// Normalize requested URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = trim($parsedUrl['path'] ?? '/', '/');

// Set base directory
$rootDir = dirname(__DIR__);

// Helper to safely require a target file
function serveFile(string $filePath, array $queryParams = []) {
    foreach ($queryParams as $k => $v) {
        $_GET[$k] = $v;
    }
    // Change working directory to file's directory so relative includes work
    chdir(dirname($filePath));
    require $filePath;
    exit;
}

// 1. Root / Homepage
if ($path === '' || $path === 'index.php') {
    serveFile($rootDir . '/index.php');
}

// 2. Exact PHP files on disk (e.g., admin/login.php, admin/catalog_overview.php, schools/payment_callback.php)
if (is_file($rootDir . '/' . $path)) {
    serveFile($rootDir . '/' . $path);
}

if (is_file($rootDir . '/' . $path . '.php')) {
    serveFile($rootDir . '/' . $path . '.php');
}

// 3. Pages directory rewrites
$pageMap = [
    'about'      => $rootDir . '/pages/about.php',
    'categories' => $rootDir . '/pages/categories.php',
    'portfolio'  => $rootDir . '/pages/portfolio.php',
    'corporate'  => $rootDir . '/pages/corporate.php',
    'gifts'      => $rootDir . '/pages/gifts.php',
    'contact'    => $rootDir . '/pages/contact.php',
    'privacy'    => $rootDir . '/pages/privacy.php',
    'terms'      => $rootDir . '/pages/terms.php',
];

if (isset($pageMap[$path])) {
    serveFile($pageMap[$path]);
}

// Check root equivalents (about.php, categories.php, etc.)
if (is_file($rootDir . '/' . $path . '.php')) {
    serveFile($rootDir . '/' . $path . '.php');
}

// 4. Portal Shortcuts
if ($path === 'login') {
    serveFile($rootDir . '/schools/login.php');
}
if ($path === 'portal') {
    serveFile($rootDir . '/schools/order.php');
}
if ($path === 'schools/payment-success') {
    serveFile($rootDir . '/schools/payment-success.php');
}
if ($path === 'schools/payment_callback') {
    serveFile($rootDir . '/schools/payment_callback.php');
}
if ($path === 'schools/track-order') {
    serveFile($rootDir . '/schools/track-order.php');
}
if ($path === 'schools' || $path === 'schools/') {
    serveFile($rootDir . '/schools/index.php');
}

// 5. School Portal Subpaths (/schools/{slug}/{action})
if (preg_match('#^schools/([^/]+)/login/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/login.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/logout/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/logout.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/dashboard/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/dashboard.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/order/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/order.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/cart/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/cart.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/checkout/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/checkout.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/orders/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/orders.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/profile/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/profile.php', ['school' => $m[1]]);
}
if (preg_match('#^schools/([^/]+)/?$#', $path, $m)) {
    serveFile($rootDir . '/schools/details.php', ['school' => $m[1]]);
}

// 6. Direct directory index check
if (is_dir($rootDir . '/' . $path) && is_file($rootDir . '/' . $path . '/index.php')) {
    serveFile($rootDir . '/' . $path . '/index.php');
}

// 404 Fallback
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:sans-serif; text-align:center; padding:5rem;'><h1>404 - Page Not Found</h1><p>The requested URL was not found on this server.</p><a href='/'>Return to Homepage</a></body></html>";
exit;
