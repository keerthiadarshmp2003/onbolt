<?php
/**
 * OnBolt Digital Platform - Store Root Entry
 * Directly routes to Schools Uniforms & Kits Ordering Portal
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

// Directly redirect root visits to the Schools ordering portal
header('Location: ' . BASE_URL . '/schools/', true, 302);
exit;
