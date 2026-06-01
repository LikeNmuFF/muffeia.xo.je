<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// First, count unread notifications before clearing
$count_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$unread_count = $count_row['count'];
$count_stmt->close();

// Clear all unread notifications by marking them as read
$clear_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$clear_stmt = $conn->prepare($clear_sql);
$clear_stmt->bind_param("i", $user_id);
$success = $clear_stmt->execute();
$clear_stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'cleared_count' => $unread_count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error clearing notifications']);
}
?>