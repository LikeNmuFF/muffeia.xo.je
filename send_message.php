<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = $_SESSION['user_id'];
    $recipient_id = $_POST['recipient_id'];
    $message_text = $_POST['message_text'];
    $is_anonymous = $_POST['is_anonymous'];

    if (!empty($recipient_id) && !empty($message_text)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, message_text, is_anonymous) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $sender_id, $recipient_id, $message_text, $is_anonymous);
        $stmt->execute();
        $stmt->close();
    }
}
?>
