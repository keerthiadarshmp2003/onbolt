<?php
/**
 * OnBolt Digital Experience Platform - School Parent Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

$schoolSlug = trim($_GET['school'] ?? '');
logoutParent($schoolSlug ?: 'international-school');
