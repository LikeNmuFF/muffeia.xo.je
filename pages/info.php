<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(404);
    die("Page not found.");
}

$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !$user['is_admin']) {
    http_response_code(404);
    die("Page not found.");
}
$stmt->close();

phpinfo();
