<?php
session_start();
include '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'schedule') {
    // Schedule account for deletion
    $deletion_time = date('Y-m-d H:i:s', strtotime('+3 hours'));
    
    // Check if there's already a pending deletion
    $check_sql = "SELECT * FROM account_deletions WHERE user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Account deletion already scheduled']);
        exit();
    }
    
    // Schedule deletion
    $sql = "INSERT INTO account_deletions (user_id, scheduled_time, status) VALUES (?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $deletion_time);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Account scheduled for deletion. You have 3 hours to cancel if you change your mind.',
            'deletion_time' => $deletion_time
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error scheduling deletion']);
    }
} elseif ($action === 'cancel') {
    // Check if cancellation is still possible (within 3 hours)
    $check_sql = "SELECT * FROM account_deletions WHERE user_id = ? AND status = 'pending' AND scheduled_time > NOW()";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $deletion_request = $check_stmt->get_result()->fetch_assoc();
    
    if (!$deletion_request) {
        echo json_encode(['success' => false, 'message' => 'No active deletion request found or grace period exceeded']);
        exit();
    }
    
    $sql = "UPDATE account_deletions SET status = 'cancelled' WHERE user_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account deletion cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error cancelling deletion']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>