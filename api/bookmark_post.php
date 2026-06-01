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

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$problem_id = isset($_POST['problem_id']) ? intval($_POST['problem_id']) : 0;

if ($problem_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid problem ID']);
    exit();
}

// Check if already bookmarked
$check = $conn->prepare("SELECT id FROM post_shares WHERE user_id = ? AND problem_id = ?");
$check->bind_param("ii", $user_id, $problem_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    // Unbookmark
    $del = $conn->prepare("DELETE FROM post_shares WHERE user_id = ? AND problem_id = ?");
    $del->bind_param("ii", $user_id, $problem_id);
    $del->execute();
    $del->close();
    echo json_encode(['success' => true, 'bookmarked' => false, 'message' => 'Post removed from saved']);
} else {
    // Bookmark
    $ins = $conn->prepare("INSERT INTO post_shares (user_id, problem_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $problem_id);
    $ins->execute();
    $ins->close();
    echo json_encode(['success' => true, 'bookmarked' => true, 'message' => 'Post saved']);
}
