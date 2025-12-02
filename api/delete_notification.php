<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo 'error';
    exit();
}

if (!isset($_POST['id'])) {
    echo 'error';
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = $_POST['id'];

$sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    echo 'success';
} else {
    echo 'error';
}

$stmt->close();
$conn->close();
?>