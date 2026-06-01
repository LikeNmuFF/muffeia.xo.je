<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['bookmarked' => false]);
    exit();
}

require_once '../includes/db.php';

$problem_id = isset($_GET['problem_id']) ? intval($_GET['problem_id']) : 0;
if ($problem_id <= 0) {
    echo json_encode(['bookmarked' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$check = $conn->prepare("SELECT id FROM post_shares WHERE user_id = ? AND problem_id = ?");
$check->bind_param("ii", $user_id, $problem_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

echo json_encode(['bookmarked' => $existing ? true : false]);
