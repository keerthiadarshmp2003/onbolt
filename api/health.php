<?php
header('Content-Type: text/plain');
echo "OnBolt Health Check\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PDO Drivers: " . implode(', ', class_exists('PDO') ? PDO::getAvailableDrivers() : ['No PDO']) . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ? getenv('DB_HOST') : 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ? getenv('DB_PORT') : 'NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ? getenv('DB_NAME') : 'NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ? getenv('DB_USER') : 'NOT SET') . "\n";

if (getenv('DB_HOST')) {
    try {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: 3306;
        $db = getenv('DB_NAME') ?: 'defaultdb';
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_TIMEOUT => 4
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        echo "Aiven Database Connection: SUCCESS!\n";
        echo "Total products in DB: " . $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
    } catch (Exception $e) {
        echo "Aiven Database Connection: FAILED (" . $e->getMessage() . ")\n";
    }
}
