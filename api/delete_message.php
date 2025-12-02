<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$message_id = intval($_POST['message_id']);

// Verify message ownership
$query = "
    SELECT sender_id 
    FROM messages 
    WHERE id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $message_id);
$stmt->execute();
$stmt->bind_result($sender_id);
$stmt->fetch();
$stmt->close();

if ($sender_id != $user_id) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'You are not allowed to delete this message']);
    exit();
}

// Delete the message
$query = "DELETE FROM messages WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $message_id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success']);
?>
