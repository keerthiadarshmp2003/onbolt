<?php
/**
 * OnBolt Digital Experience Platform - Admin Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

logoutAdmin();
header('Location: ' . BASE_URL . '/admin/login.php?msg=' . urlencode('Logged out successfully.'));
exit;
