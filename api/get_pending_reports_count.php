<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$sql = "SELECT COUNT(*) as pending_count FROM moderation_queue WHERE status = 'pending'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

echo json_encode(['count' => $row['pending_count'] ?? 0]);
?>