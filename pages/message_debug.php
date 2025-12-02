<?php
// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// InfinityFree specific settings
ini_set('memory_limit', '64M');
set_time_limit(30);
ini_set('max_execution_time', 30);

// Check for required extensions
if (!extension_loaded('openssl')) {
    die('OpenSSL extension is required but not enabled on this server.');
}

session_start();

// Simple error handling
function handleError($message) {
    error_log("Message Error: " . $message);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        echo json_encode(['status' => 'error', 'message' => $message]);
    } else {
        echo "Error: " . $message;
    }
    exit();
}

// Check if required files exist
if (!file_exists('../includes/db.php')) {
    handleError('Database configuration missing');
}

if (!file_exists('../includes/encryption.php')) {
    handleError('Encryption library missing');
}

include '../includes/db.php';

// Check database connection
if (!$conn) {
    handleError('Database connection failed: ' . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// SIMPLIFIED VERSION - Remove complex features temporarily

// Update online status only
$update_sql = "UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
if (!$update_stmt) {
    handleError('Prepare failed: ' . $conn->error);
}
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Basic conversations query (simplified)
$conversations_query = "
    SELECT 
        c.id AS conversation_id,
        CASE 
            WHEN c.user1_id = ? THEN u2.username 
            ELSE u1.username 
        END AS other_user,
        CASE 
            WHEN c.user1_id = ? THEN u2.profile_pic 
            ELSE u1.profile_pic 
        END AS other_user_pic
    FROM conversations c
    LEFT JOIN users u1 ON u1.id = c.user1_id
    LEFT JOIN users u2 ON u2.id = c.user2_id
    WHERE c.user1_id = ? OR c.user2_id = ?";

$stmt = $conn->prepare($conversations_query);
if (!$stmt) {
    handleError('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations_result = $stmt->get_result();
$conversations = [];

while ($row = $conversations_result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();

// Handle AJAX requests simply
if (isset($_GET['conversation_id'])) {
    $conversation_id = intval($_GET['conversation_id']);
    
    $messages_query = "
        SELECT m.message_text, m.timestamp,
               IF(m.sender_id = ?, 'You', u.username) AS sender
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.timestamp ASC";
    
    $stmt = $conn->prepare($messages_query);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $conversation_id);
        $stmt->execute();
        $messages_result = $stmt->get_result();
        $messages = [];
        
        while ($row = $messages_result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        echo json_encode($messages);
        $stmt->close();
    }
    exit();
}

// Simple message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'])) {
    $conversation_id = intval($_POST['conversation_id']);
    $message_text = trim($_POST['message_text']);
    
    if (empty($message_text)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit();
    }
    
    // Simple insert without encryption for testing
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $conversation_id, $user_id, $message_text);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
        }
        $stmt->close();
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUFFEIA - Messages (Debug)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .conversation-item { padding: 10px; border-bottom: 1px solid #ccc; cursor: pointer; }
        .conversation-item:hover { background: #f5f5f5; }
        #messagesArea { border: 1px solid #ccc; padding: 10px; height: 400px; overflow-y: auto; margin: 10px 0; }
        .message { margin: 5px 0; padding: 5px; background: #f0f0f0; }
        .message.you { background: #d1ecf1; text-align: right; }
    </style>
</head>
<body>
    <h1>Messages - Debug Version</h1>
    
    <div style="display: flex;">
        <div style="width: 30%;">
            <h3>Conversations</h3>
            <div id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <p>No conversations yet</p>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-item" data-id="<?= $conversation['conversation_id'] ?>">
                            <strong><?= htmlspecialchars($conversation['other_user']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="width: 70%; padding-left: 20px;">
            <h3>Chat</h3>
            <div id="messagesArea">
                <p>Select a conversation to start messaging</p>
            </div>
            <div id="messageForm" style="display: none;">
                <textarea id="messageText" placeholder="Type your message..." rows="3" style="width: 100%;"></textarea>
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>

    <script>
    let activeConversationId = null;

    // Load messages when conversation is clicked
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.addEventListener('click', function() {
            activeConversationId = this.getAttribute('data-id');
            loadMessages(activeConversationId);
            document.getElementById('messageForm').style.display = 'block';
        });
    });

    async function loadMessages(conversationId) {
        try {
            const response = await fetch(`message_debug.php?conversation_id=${conversationId}`);
            const messages = await response.json();
            
            const messagesArea = document.getElementById('messagesArea');
            messagesArea.innerHTML = '';
            
            messages.forEach(msg => {
                const messageElement = document.createElement('div');
                messageElement.className = `message ${msg.sender === 'You' ? 'you' : 'other'}`;
                messageElement.innerHTML = `
                    <strong>${msg.sender}:</strong> ${msg.message_text}
                    <br><small>${msg.timestamp}</small>
                `;
                messagesArea.appendChild(messageElement);
            });
            
            messagesArea.scrollTop = messagesArea.scrollHeight;
        } catch (error) {
            console.error('Error loading messages:', error);
            alert('Error loading messages');
        }
    }

    async function sendMessage() {
        const text = document.getElementById('messageText').value.trim();
        if (text && activeConversationId) {
            try {
                const formData = new URLSearchParams();
                formData.append('conversation_id', activeConversationId);
                formData.append('message_text', text);

                const response = await fetch('message_debug.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('messageText').value = '';
                    loadMessages(activeConversationId);
                } else {
                    alert(result.message || 'Error sending message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message');
            }
        }
    }
    </script>
</body>
</html>