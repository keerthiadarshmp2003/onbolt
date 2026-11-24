<?php
/**
 * OnBolt Digital Platform - Database Configuration
 * Pure Core PHP & PDO MySQL Connection
 */

declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: (strpos(getenv('DB_HOST') ?: '', 'aivencloud.com') !== false ? '18911' : '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (strpos(getenv('DB_HOST') ?: '', 'aivencloud.com') !== false ? 'defaultdb' : 'onbolt_management'));
define('DB_USER', getenv('DB_USER') ?: (strpos(getenv('DB_HOST') ?: '', 'aivencloud.com') !== false ? 'avnadmin' : 'root'));
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

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
                '<p style="color:#9a3412; font-size:0.88rem;">Please check your Vercel Environment Variables: <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_PORT</code>, <code>DB_NAME</code>.</p>' .
                '</div>');
        }
    }
    return $pdo;
}
