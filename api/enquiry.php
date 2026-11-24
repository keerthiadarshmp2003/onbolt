<?php
/**
 * OnBolt Digital Platform - Contact & Bulk Institutional Inquiry API
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/app.php';

$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? 'Institutional Inquiry');
$message = trim($_POST['message'] ?? '');

if (empty($name) || !$email || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Please fill in all mandatory fields with valid data.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO contact_enquiries (full_name, email, phone, subject, message, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, 'New', NOW())");
    $stmt->execute([$name, $email, $phone, $subject, $message]);
    
    // Create admin notification
    $pdo->prepare("INSERT INTO notifications (type, title, message, link, is_read, created_at) 
                   VALUES ('enquiry', ?, ?, 'enquiries.php', 0, NOW())")
        ->execute(["New Inquiry: {$subject}", "Received from {$name} ({$email}, {$phone})"]);

    logActivity('contact_enquiry', 'contact', null, 'visitor', "Inquiry from {$name} ({$email})");
    echo json_encode(['success' => true, 'message' => 'Thank you! Your inquiry has been dispatched to the OnBolt Institutional Desk.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'An error occurred while saving your inquiry: ' . $e->getMessage()]);
}
