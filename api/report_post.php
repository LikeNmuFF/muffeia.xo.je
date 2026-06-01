<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

require_once '../includes/db.php';

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$problem_id = isset($_POST['problem_id']) ? intval($_POST['problem_id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$details = isset($_POST['details']) ? trim($_POST['details']) : '';
$user_id = $_SESSION['user_id'];

if ($problem_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid problem ID']);
    exit();
}

$valid_reasons = ['spam', 'harassment', 'inappropriate', 'off_topic', 'other'];
if (!in_array($reason, $valid_reasons)) {
    $reason = 'other';
}

$stmt = $conn->prepare("SELECT id FROM problems WHERE id = ?");
$stmt->bind_param("i", $problem_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Problem not found']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO moderation_queue (content_type, content_id, reason, reported_by, details, status, created_at) VALUES ('post', ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iiss", $problem_id, $reason, $user_id, $details);
$stmt->execute();

$admin_stmt = $conn->prepare("SELECT id FROM users WHERE is_admin = 1 AND id != ?");
$admin_stmt->bind_param("i", $user_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

$message = "New report on post #{$problem_id} (reason: {$reason})";
$target_url = "pages/admin_dashboard.php";

$notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, target_url, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
while ($admin = $admin_result->fetch_assoc()) {
    $notif_stmt->bind_param("iss", $admin['id'], $message, $target_url);
    $notif_stmt->execute();
}

echo json_encode(['success' => true, 'message' => 'Report submitted. An admin will review it.']);
