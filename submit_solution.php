<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$solution_text = $_POST['solution_text'];
$problem_id = $_POST['problem_id'];

// Insert the solution
$stmt = $conn->prepare("INSERT INTO solutions (problem_id, user_id, solution_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $problem_id, $user_id, $solution_text);
$stmt->execute();
$solution_id = $stmt->insert_id;
$stmt->close();

// Get the problem creator
$query = "SELECT user_id FROM problems WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $problem_id);
$stmt->execute();
$stmt->bind_result($problem_creator_id);
$stmt->fetch();
$stmt->close();

// Insert a notification
$notification_text = "A new solution has been posted for your problem.";
$target_url = "solution.php?id=" . $solution_id;

$stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_text, target_url) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $problem_creator_id, $notification_text, $target_url);
$stmt->execute();
$stmt->close();

header("Location: problem.php?id=" . $problem_id);
exit();
?>
