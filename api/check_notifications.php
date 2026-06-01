<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['count' => 0]));
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['count' => $row['count']]);
?>