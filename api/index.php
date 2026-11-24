<?php
// Standalone OnBolt Health & Gateway on Vercel
$host = getenv('DB_HOST') ?: 'mysql-797983c-keerthiadarshmpoff-c7b3.f.aivencloud.com';
$port = getenv('DB_PORT') ?: 18911;
$db   = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::ATTR_TIMEOUT => 4
    ]);
    $prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $schoolCount = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
    
    echo "<!DOCTYPE html><html><head><title>OnBolt Live</title><style>body{font-family:'Segoe UI',sans-serif;background:#0B111E;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .card{background:#162033;padding:3rem;border-radius:20px;border:2px solid #FF6702;max-width:500px;text-align:center;} h1{color:#FF6702;margin-top:0;} .badge{background:#15803D;color:#fff;padding:0.4rem 1rem;border-radius:9999px;font-weight:700;display:inline-block;margin-bottom:1.5rem;}</style></head><body>";
    echo "<div class='card'>";
    echo "<div class='badge'>✓ SYSTEM LIVE</div>";
    echo "<h1>⚡ OnBolt Platform</h1>";
    echo "<p>Connected to Aiven Cloud MySQL (<code>$db</code>) successfully!</p>";
    echo "<p style='color:#94A3B8;'>Verified database contents:</p>";
    echo "<div style='font-size:1.1rem; font-weight:700; margin:1rem 0; color:#FFC857;'>$prodCount Products &bull; $schoolCount Partner Schools</div>";
    echo "<p style='font-size:0.85rem; color:#64748B;'>Server: Vercel Serverless PHP Runtime</p>";
    echo "</div></body></html>";
} catch (Exception $e) {
    echo "<h1>OnBolt Setup Notice</h1><p>DB Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
