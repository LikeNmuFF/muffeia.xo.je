<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$user_id = $_SESSION['user_id'];

$messages_query = "
    SELECT m.id, m.message_text, m.created_at, m.is_anonymous, u.username AS sender_username
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.recipient_id = ? OR m.sender_id = ? 
    ORDER BY m.created_at ASC";
$stmt = $conn->prepare($messages_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$messages_result = $stmt->get_result();
$stmt->close();

while ($message = $messages_result->fetch_assoc()) {
    $is_sent = $message['sender_id'] == $user_id;
    echo '<div class="message-card ' . ($is_sent ? 'sent' : 'received') . '">';
    echo '<strong>' . ($message['is_anonymous'] ? 'Anonymous' : htmlspecialchars($message['sender_username'])) . '</strong>';
    echo '<small style="float: right;">' . $message['created_at'] . '</small>';
    echo '<div class="message-body">' . htmlspecialchars($message['message_text']) . '</div>';
    echo '</div>';
}
?>
