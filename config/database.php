<?php
/**
 * OnBolt Digital Platform - Database Configuration
 * Pure Core PHP & PDO MySQL Connection
 */

declare(strict_types=1);

// Detect connection parameters from individual env vars or complete DATABASE_URL / MYSQL_URL
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$parsedHost = null;
$parsedPort = null;
$parsedName = null;
$parsedUser = null;
$parsedPass = null;

if ($dbUrl) {
    $parts = parse_url($dbUrl);
    if ($parts) {
        $parsedHost = $parts['host'] ?? null;
        $parsedPort = isset($parts['port']) ? (string)$parts['port'] : null;
        $parsedUser = isset($parts['user']) ? urldecode($parts['user']) : null;
        $parsedPass = isset($parts['pass']) ? urldecode($parts['pass']) : null;
        $parsedName = isset($parts['path']) ? ltrim($parts['path'], '/') : null;
    }
}

$isCpanel = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'store.onboltlive.com') !== false) || is_dir('/home/ghfer22df43gf12');
if ($isCpanel) {
    $rawHost = getenv('DB_HOST') ?: 'localhost';
    $rawPort = getenv('DB_PORT') ?: '3306';
    $rawName = getenv('DB_NAME') ?: 'ghfer22df43gf12_onbolt';
    $rawUser = getenv('DB_USER') ?: 'ghfer22df43gf12_onbolt_usr';
    $rawPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'OnBoltLive#2026!Db';
} else {
    $rawHost = getenv('DB_HOST') ?: ($parsedHost ?: '127.0.0.1');
    $rawPort = getenv('DB_PORT') ?: ($parsedPort ?: (strpos($rawHost, 'aivencloud.com') !== false ? '18911' : '3306'));
    $rawName = getenv('DB_NAME') ?: ($parsedName ?: (strpos($rawHost, 'aivencloud.com') !== false ? 'defaultdb' : 'onbolt_management'));
    $rawUser = getenv('DB_USER') ?: ($parsedUser ?: (strpos($rawHost, 'aivencloud.com') !== false ? 'avnadmin' : 'root'));
    $rawPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($parsedPass ?? '');
}

define('DB_HOST', $rawHost);
define('DB_PORT', $rawPort);
define('DB_NAME', $rawName);
define('DB_USER', $rawUser);
define('DB_PASS', $rawPass);
define('DB_CHARSET', 'utf8mb4');

if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];
            if (getenv('MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('MYSQL_ATTR_SSL_CA');
            }
            if (getenv('DB_SSL') === 'true' || getenv('DB_SSL') === '1' || strpos(DB_HOST, 'aivencloud.com') !== false) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('OnBolt DB Connection Error: ' . $e->getMessage());
                die('<div style="font-family:sans-serif; padding:2rem; max-width:600px; margin:2rem auto; border:1px solid #fed7aa; background:#fff7ed; border-radius:12px;">' .
                    '<h2 style="color:#c2410c; margin-top:0;">Database Configuration Notice</h2>' .
                    '<p style="color:#7c2d12;">Could not connect to database on <strong>' . htmlspecialchars((string)DB_HOST) . ':' . htmlspecialchars((string)DB_PORT) . '</strong>: ' . htmlspecialchars($e->getMessage()) . '</p>' .
                    '<p style="color:#9a3412; font-size:0.88rem;">Please check your Railway / Cloud Environment Variables: <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code> (or <code>MYSQL_URL</code>).</p>' .
                    '</div>');
            }
        }
        return $pdo;
    }
}
