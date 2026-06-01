<?php
session_start();
include '../includes/db.php';

$last_post_time = isset($_GET['last_post_time']) ? $_GET['last_post_time'] : '';

// Check if there are newer posts
$sql = "SELECT COUNT(*) as count FROM problems WHERE created_at > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $last_post_time);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['count'];

header('Content-Type: application/json');
echo json_encode(['new_posts' => $count > 0]);
?>