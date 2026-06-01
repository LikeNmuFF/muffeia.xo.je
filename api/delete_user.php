<?php
session_start();
include '../includes/db.php';

// Ensure only admin can delete users
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin_check = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin_check || !$admin_check['is_admin']) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // Delete user from database
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        echo "Error deleting user.";
    }
}
?>
