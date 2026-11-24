<?php
/**
 * OnBolt Digital Platform - Newsletter API Endpoint
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/app.php';

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, status, created_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE status = 1");
    $stmt->execute([$email]);
    logActivity('newsletter_subscribe', 'marketing', null, 'visitor', "Subscribed: {$email}");
    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing to OnBolt updates!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Unable to record subscription. Please try again.']);
}
