<?php
session_start();
include '../includes/db.php';
include '../includes/encryption.php';
include '../includes/moderation.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize encryption keys for user if not exists
$key_check_sql = "SELECT encryption_key FROM users WHERE id = ?";
$key_stmt = $conn->prepare($key_check_sql);
$key_stmt->bind_param("i", $user_id);
$key_stmt->execute();
$key_result = $key_stmt->get_result();
$user_data = $key_result->fetch_assoc();

if (!$user_data || !$user_data['encryption_key']) {
    // Generate and store encryption key for user
    $encryption_key = MessageEncryption::generateEncryptionKey();
    $update_key_sql = "UPDATE users SET encryption_key = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_key_sql);
    $update_stmt->bind_param("si", $encryption_key, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Update user's online status
$update_sql = "UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Clean up old online statuses
$cleanup_sql = "UPDATE users SET is_online = FALSE WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$conn->query($cleanup_sql);

// FIXED: COMPLETELY REVISED CONVERSATION QUERY TO PREVENT DUPLICATES
$conversations_query = "
    SELECT 
        c.id AS conversation_id,
        CASE 
            WHEN c.user1_id = ? THEN c.user2_id 
            ELSE c.user1_id 
        END AS other_user_id,
        CASE 
            WHEN c.user1_id = ? THEN u2.username 
            ELSE u1.username 
        END AS other_user,
        CASE 
            WHEN c.user1_id = ? THEN u2.profile_pic 
            ELSE u1.profile_pic 
        END AS other_user_pic,
        CASE 
            WHEN c.user1_id = ? THEN u2.is_online 
            ELSE u1.is_online 
        END AS other_user_online,
        CASE 
            WHEN c.user1_id = ? THEN u2.last_seen 
            ELSE u1.last_seen 
        END AS other_user_last_seen,
        (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY timestamp DESC LIMIT 1) AS last_message,
        (SELECT is_encrypted FROM messages WHERE conversation_id = c.id ORDER BY timestamp DESC LIMIT 1) AS last_message_encrypted,
        (SELECT timestamp FROM messages WHERE conversation_id = c.id ORDER BY timestamp DESC LIMIT 1) AS last_message_time
    FROM conversations c
    LEFT JOIN users u1 ON u1.id = c.user1_id
    LEFT JOIN users u2 ON u2.id = c.user2_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC";

$stmt = $conn->prepare($conversations_query);
$stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations_result = $stmt->get_result();
$conversations = [];

while ($row = $conversations_result->fetch_assoc()) {
    // For conversation list, show generic preview
    if ($row['last_message_encrypted'] && !empty($row['last_message'])) {
        $row['last_message'] = '🔒 Encrypted message';
    } else if (empty($row['last_message'])) {
        $row['last_message'] = 'Start a conversation';
    }
    $conversations[] = $row;
}
$stmt->close();

// Search users based on input
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $search_query = "
        SELECT id, username, profile_pic, is_online, last_seen 
        FROM users 
        WHERE username LIKE CONCAT('%', ?, '%') AND id != ?";
    $stmt = $conn->prepare($search_query);
    $stmt->bind_param("si", $search, $user_id);
    $stmt->execute();
    $search_result = $stmt->get_result();
    $users = [];
    while ($row = $search_result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit();
}

// Check or create conversation - FIXED TO PREVENT DUPLICATE CONVERSATIONS
if (isset($_GET['user_id'])) {
    $other_user_id = intval($_GET['user_id']);

    // Check if conversation exists - IMPROVED QUERY
    $check_conversation_query = "
        SELECT id 
        FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
        LIMIT 1";
    $stmt = $conn->prepare($check_conversation_query);
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $conversation = $result->fetch_assoc();
        echo json_encode(['status' => 'existing', 'conversation_id' => $conversation['id']]);
    } else {
        // Create a new conversation - ADDED DUPLICATE CHECK BEFORE INSERT
        $check_again_sql = "SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)";
        $check_stmt = $conn->prepare($check_again_sql);
        $check_stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing = $check_result->fetch_assoc();
            echo json_encode(['status' => 'existing', 'conversation_id' => $existing['id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $other_user_id);
            if ($stmt->execute()) {
                $new_conversation_id = $stmt->insert_id;
                echo json_encode(['status' => 'new', 'conversation_id' => $new_conversation_id]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create conversation']);
            }
        }
        $check_stmt->close();
    }
    exit();
}

if (isset($_GET['conversation_id'])) {
    $conversation_id = intval($_GET['conversation_id']);

    // DEBUG: Log the conversation ID being requested
    error_log("Fetching messages for conversation_id: " . $conversation_id);

    $messages_query = "
        SELECT m.message_text, m.timestamp, m.is_encrypted, m.sender_id,
               IF(m.sender_id = ?, 'You', u.username) AS sender,
               u.profile_pic,
               m.sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.timestamp ASC";
    $stmt = $conn->prepare($messages_query);
    $stmt->bind_param("ii", $user_id, $conversation_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    $messages = [];
    
    // DEBUG: Log number of messages found
    error_log("Number of messages found: " . $messages_result->num_rows);
    
    while ($row = $messages_result->fetch_assoc()) {
        // Handle message decryption
        if ($row['is_encrypted'] && !empty($row['message_text'])) {
            try {
                // Use conversation-based shared key
                $shared_key = MessageEncryption::generateConversationKey($conversation_id);
                $decrypted_text = MessageEncryption::decryptMessage($row['message_text'], $shared_key);
                $row['message_text'] = $decrypted_text;
            } catch (Exception $e) {
                // If decryption fails, show encrypted indicator but keep original for potential recovery
                $row['message_text'] = "🔒 [Encrypted message]";
                error_log("Decryption error for user $user_id in conversation $conversation_id: " . $e->getMessage());
            }
        }
        $messages[] = $row;
    }
    
    // DEBUG: Log final messages array
    error_log("Final messages array count: " . count($messages));
    
    echo json_encode($messages);
    exit();
}

// Get user status
if (isset($_GET['get_status'])) {
    $other_user_id = intval($_GET['get_status']);
    $status_sql = "SELECT is_online, last_seen FROM users WHERE id = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("i", $other_user_id);
    $status_stmt->execute();
    $result = $status_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        echo json_encode([
            'is_online' => $user_data['is_online'],
            'last_seen' => $user_data['last_seen'],
            'status_text' => getStatusText($user_data['is_online'], $user_data['last_seen'])
        ]);
    }
    exit();
}

// Send a message (PROPER SHARED ENCRYPTION)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conversation_id = intval($_POST['conversation_id']);
    $message_text = trim($_POST['message_text']);
    
    // Validate message is not empty before processing
    if (empty($message_text)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit();
    }

    // Server-side moderation: mask bad words instead of blocking
    $mod = moderate_text($message_text);
    if (!empty($mod['flagged'])) {
        $message_text = mask_text($message_text);
    }

    // FIXED: XSS Prevention - Sanitize message text
    $message_text = htmlspecialchars($message_text, ENT_QUOTES, 'UTF-8');

    try {
        // Use conversation-based shared key (same for all users in this conversation)
        $shared_key = MessageEncryption::generateConversationKey($conversation_id);
        $encrypted_message = MessageEncryption::encryptMessage($message_text, $shared_key);
        
        // Store encrypted message
        $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, is_encrypted) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iis", $conversation_id, $user_id, $encrypted_message);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Encryption failed: ' . $e->getMessage()]);
    }
    exit();
}

// Helper function to format time
function formatTime($timestamp) {
    if (empty($timestamp)) return 'No messages yet';
    
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    } else {
        return date('M j', $time);
    }
}

// Helper function to format last seen time
function formatLastSeen($last_seen) {
    if (empty($last_seen)) return 'Long time ago';
    
    $last_seen_time = strtotime($last_seen);
    $now = time();
    $diff = $now - $last_seen_time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

// Helper function to get status text
function getStatusText($is_online, $last_seen) {
    if ($is_online) {
        return 'Online';
    } else {
        return formatLastSeen($last_seen);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark-mode')</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
<link rel="icon" href="../logo/m-blues.png" type="image/png">
<title>Muffeia - Messages</title>
<style>
/* Mobile sidebar overrides */
@media (max-width: 768px) {
    .menu-toggle { z-index: 1001; position: relative; }
    .sidebar.active { transform: translateX(0); box-shadow: 0 0 20px rgba(0,0,0,0.3); }
    .sidebar-overlay.active { display: block; opacity: 1; }
    .conversations-sidebar.hidden { display: none; }
    .chat-area { display: none; }
    .chat-area.active { display: flex; }
}

@media (max-width: 480px) {
    .messages-container {
        height: calc(100vh - var(--topnav-height) - var(--space-4));
        border-radius: 0;
    }
    .conversation-item {
        padding: 10px 12px !important;
        margin: 0 6px 8px 6px !important;
    }
    .conversation-avatar {
        width: 36px !important;
        height: 36px !important;
        font-size: 0.75rem !important;
    }
    .conversation-item h4 {
        font-size: 0.85rem !important;
    }
    .conversation-item .conversation-preview {
        font-size: 0.75rem !important;
    }
    .conversation-item .conversation-time {
        font-size: 10px !important;
    }
    .conversations-header {
        padding: 12px 14px !important;
    }
    .conversations-header h2 {
        font-size: 1rem !important;
    }
    .search-container {
        padding: 8px 10px !important;
    }
    .chat-header {
        padding: 12px 14px !important;
    }
    .chat-header-avatar {
        width: 34px !important;
        height: 34px !important;
    }
    .chat-header-info h3 {
        font-size: 0.9rem !important;
    }
    .message-form {
        padding: 10px !important;
    }
    .message-input {
        padding: 10px 12px !important;
        font-size: 0.85rem !important;
        min-height: 38px !important;
    }
    .send-button {
        width: 38px !important;
        height: 38px !important;
    }
    .mobile-chat-header {
        padding: 10px 12px !important;
    }
    .status-text.online {
        font-size: 9px !important;
        padding: 1px 6px !important;
    }
    .status-text.offline {
        font-size: 9px !important;
    }
}

@media (max-width: 400px) {
    .top-nav-content h1 {
        font-size: 0.85rem !important;
    }
    .top-nav-content h1 .encryption-badge {
        display: none !important;
    }
    .conversations-header {
        padding: 8px 10px !important;
    }
    .conversations-header h2 {
        font-size: 0.9rem !important;
    }
    .conversations-header h2 i {
        font-size: 0.85rem !important;
    }
    .search-container {
        padding: 6px 8px !important;
    }
    .conversation-item {
        padding: 8px 8px !important;
        margin: 0 4px 6px 4px !important;
        gap: 6px !important;
    }
    .conversation-avatar {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.65rem !important;
    }
    .conversation-item h4 {
        font-size: 0.78rem !important;
    }
    .conversation-item .conversation-preview {
        font-size: 0.7rem !important;
    }
    .conversation-item .conversation-time {
        font-size: 9px !important;
    }
    .conversation-item .status-text.online {
        font-size: 8px !important;
        padding: 1px 5px !important;
    }
    .conversation-item .status-text.offline {
        font-size: 8px !important;
    }
    .chat-header {
        padding: 10px 10px !important;
    }
    .chat-header-avatar {
        width: 30px !important;
        height: 30px !important;
    }
    .chat-header-info h3 {
        font-size: 0.8rem !important;
    }
    .messages-area {
        padding: 6px !important;
    }
    .mobile-chat-header {
        padding: 8px 10px !important;
    }
    .message-form {
        padding: 8px !important;
    }
    .message-input {
        padding: 8px 10px !important;
        font-size: 0.8rem !important;
        min-height: 34px !important;
    }
    .send-button {
        width: 34px !important;
        height: 34px !important;
        font-size: 0.8rem !important;
    }
    .encryption-info {
        font-size: 10px !important;
    }
    .empty-chat {
        padding: var(--space-4) !important;
    }
    .empty-chat h3 {
        font-size: 1rem !important;
    }
    .empty-chat p {
        font-size: 0.75rem !important;
    }
    .empty-chat > div:first-child {
        width: 60px !important;
        height: 60px !important;
    }
    .empty-chat > div:first-child i {
        font-size: 24px !important;
    }
    .security-features {
        padding: var(--space-4) !important;
    }
    .security-features h4 {
        font-size: 0.75rem !important;
    }
}

.encryption-status i { color: var(--clr-secondary); }
.encryption-badge {
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: white; padding: 2px 8px; border-radius: var(--radius-full);
    font-size: 10px; margin-left: 6px; font-weight: 600;
}
.decryption-error { color: #ef4444; font-style: italic; font-size: var(--text-xs); }
.loading-messages { display: flex; flex-direction: column; align-items: center; padding: var(--space-6); color: var(--clr-text-tertiary); }
.auto-refresh-indicator {
    position: fixed; bottom: 20px; right: 20px;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: white; padding: 8px 14px; border-radius: var(--radius-full);
    font-size: 12px; display: flex; align-items: center; gap: 6px;
    z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0; transition: opacity 0.3s;
}
.auto-refresh-indicator.show { opacity: 1; }
@keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.7; } 100% { transform: scale(1); opacity: 1; } }
.new-message-indicator {
    background: var(--clr-primary); color: white; border-radius: 50%;
    width: 8px; height: 8px; position: absolute; top: 5px; right: 5px;
    animation: pulse 1.5s infinite;
}
.debug-button {
    position: fixed; top: 10px; right: 10px; background: var(--clr-primary);
    color: white; border: none; padding: 8px 12px; border-radius: var(--radius-sm);
    cursor: pointer; z-index: 10000; font-size: 12px;
}
.empty-conversations {
    text-align: center; padding: var(--space-8) var(--space-4);
    color: var(--clr-text-tertiary);
}
.empty-conversations i { font-size: 2.5rem; margin-bottom: var(--space-3); opacity: 0.4; }
.empty-conversations h3 { margin-bottom: var(--space-2); color: var(--clr-text-secondary); font-family: var(--font-heading); }
.empty-conversations p { font-size: var(--text-sm); }
</style>
<script>
function handleImageError(img) {
    var fallback = img.nextElementSibling;
    if (fallback && fallback.classList.contains('avatar-fallback')) {
        img.style.display = 'none';
        fallback.style.display = 'flex';
    }
}
</script>
</head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                    <img src="../logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
                    <span>Muffeia</span>
                </div>
                <button class="sidebar-close" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="theme-toggle">
                    <input type="checkbox" id="theme-toggle" />
                    <span class="slider">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </span>
                </label>
                <span class="theme-label">Light/Dark Mode</span>
            </div>
            
             <div class="nav-items">
                 <a href="../index.php" class="nav-item">
                     <i class="fas fa-home"></i>
                     <span>Home</span>
                 </a>
                 <a href="profile.php" class="nav-item">
                     <i class="fas fa-user"></i>
                     <span>Profile</span>
                 </a>
                 <a href="message.php" class="nav-item active">
                     <i class="fas fa-envelope"></i>
                     <span>Messages</span>
                     <span class="badge" id="messageBadge" style="display: none;">0</span>
                 </a>
                 <a href="group-room.php" class="nav-item">
                     <i class="fas fa-users"></i>
                     <span>Group Chats</span>
                     <span class="badge" id="groupChatBadge" style="display: none;">0</span>
                 </a>
                 <a href="notifications.php" class="nav-item">
                     <i class="fas fa-bell"></i>
                     <span>Notifications</span>
                     <span class="badge" id="notificationBadge" style="display: none;">0</span>
                 </a>

                 <a href="settings.php" class="nav-item">
                     <i class="fas fa-cog"></i>
                     <span>Settings</span>
                 </a>
                    <a href="../auth/logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
             </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation Bar -->
            <header class="top-nav">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="top-nav-content">
                    <h1>Messages <span class="encryption-badge">End-to-End Encrypted</span></h1>
                    <div class="user-actions">
                        <a href="notifications.php" class="icon-btn notification-btn" role="button">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot" style="display: none;"></span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Messages Container -->
            <div class="messages-container">
                <!-- Conversations Sidebar -->
                <div class="conversations-sidebar" id="conversationsSidebar">
                    <div class="conversations-header" style="padding: var(--space-4) var(--space-5); border-bottom: 2px solid var(--clr-border-theme); background: linear-gradient(90deg, rgba(212, 74, 108, 0.03) 0%, rgba(42, 157, 143, 0.03) 100%);">
                        <h2 style="font-family: var(--font-heading); font-size: var(--text-lg); font-weight: 700; color: var(--clr-text-primary); margin: 0; display: flex; align-items: center; gap: var(--space-2);">
                            <i class="fas fa-comments" style="color: var(--clr-primary);"></i>
                            Conversations
                        </h2>
                    </div>

                    <div class="search-container" style="padding: var(--space-3) var(--space-4);">
                        <div class="search-wrapper" style="position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--clr-text-tertiary); font-size: var(--text-sm); z-index: 1;"></i>
                            <input type="text" id="searchInput" placeholder="Search users..." style="width: 100%; padding: var(--space-2) var(--space-2) var(--space-2) 36px; border: 2px solid var(--clr-border-theme); border-radius: var(--radius-md); background: var(--clr-surface-theme); color: var(--clr-text-primary); font-family: var(--font-body); font-size: var(--text-sm); transition: all 0.2s ease;">
                            <div class="search-results-container" id="searchResultsContainer">
                                <!-- Search results will appear here -->
                            </div>
                        </div>
                    </div>

                    <div class="conversations-list" id="conversationsList">
                        <?php if (empty($conversations)): ?>
                            <div class="empty-conversations">
                                <i class="fas fa-comments"></i>
                                <h3>No conversations yet</h3>
                                <p>Start a conversation by searching for users above</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            // FIXED: Use conversation_id as unique identifier instead of user_id
                            $displayed_conversations = [];
                            foreach ($conversations as $conversation): 
                                // Skip if we've already displayed this conversation
                                if (in_array($conversation['conversation_id'], $displayed_conversations)) {
                                    continue;
                                }
                                $displayed_conversations[] = $conversation['conversation_id'];
                                
                                $profile_pic_url = (!empty($conversation['other_user_pic']) && $conversation['other_user_pic'] != 'default.png') 
                                    ? "../" . $conversation['other_user_pic']
                                    : null;
                                
                                // Format last message time
                                $last_message_time = formatTime($conversation['last_message_time']);
                                
                                // Get status text
                                $status_text = $conversation['other_user_online'] ? 'Online' : formatLastSeen($conversation['other_user_last_seen']);
                            ?>
                                 <div class="conversation-item card-elevated-sm" style="display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-4); margin-bottom: var(--space-3); border-radius: var(--radius-lg); border: 1px solid var(--clr-border-theme); background: var(--clr-surface-theme); cursor: pointer; transition: all 0.2s ease; position: relative; overflow: hidden;" data-conversation-id="<?= htmlspecialchars($conversation['conversation_id']); ?>" data-user-id="<?= htmlspecialchars($conversation['other_user_id']); ?>">
                                      <div class="conversation-avatar" style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: var(--text-sm); flex-shrink: 0; position: relative; box-shadow: 0 2px 8px rgba(212, 74, 108, 0.15);">
                                          <?php if ($profile_pic_url): ?>
                                              <img src="<?= htmlspecialchars($profile_pic_url); ?>" alt="<?= htmlspecialchars($conversation['other_user']); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="handleImageError(this)">
                                              <div class="avatar-fallback" style="display: none;"><?= strtoupper(substr(htmlspecialchars($conversation['other_user']), 0, 1)); ?></div>
                                          <?php else: ?>
                                              <?= strtoupper(substr(htmlspecialchars($conversation['other_user']), 0, 1)); ?>
                                          <?php endif; ?>
                                          <?php if ($conversation['other_user_online']): ?>
                                              <span class="online-indicator" style="position: absolute; bottom: -1px; right: -1px; width: 12px; height: 12px; border-radius: 50%; background: var(--clr-primary); border: 2px solid var(--clr-surface-theme); box-shadow: 0 0 0 2px rgba(212, 74, 108, 0.15);"></span>
                                          <?php endif; ?>
                                      </div>
                                      <div class="conversation-info" style="flex: 1; min-width: 0;">
                                          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-2); margin-bottom: 4px;">
                                              <h4 style="font-weight: 600; color: var(--clr-text-primary); margin: 0; font-size: var(--text-base);"><?= htmlspecialchars($conversation['other_user']); ?></h4>
                                              <?php if ($conversation['other_user_online']): ?>
                                                  <span class="status-text online" style="font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; padding: 2px 8px; border-radius: var(--radius-full); background: linear-gradient(135deg, rgba(212, 74, 108, 0.1) 0%, rgba(42, 157, 143, 0.1) 100%); color: var(--clr-secondary); white-space: nowrap;">Online</span>
                                              <?php else: ?>
                                                  <span class="status-text offline" style="font-size: 10px; color: var(--clr-text-tertiary);"><?= $status_text; ?></span>
                                              <?php endif; ?>
                                          </div>
                                          <p class="conversation-preview" style="font-weight: 400; color: var(--clr-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; margin-bottom: 4px; font-size: var(--text-sm);">
                                              <?= !empty($conversation['last_message']) ? htmlspecialchars($conversation['last_message']) : 'Start a conversation'; ?>
                                          </p>
                                          <div class="conversation-time" style="font-size: 11px; color: var(--clr-text-tertiary);"><?= $last_message_time; ?></div>
                                      </div>
                                  </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area" id="chatArea">
                    <!-- Mobile Chat Header -->
                    <div class="mobile-chat-header" id="mobileChatHeader" style="display: none; padding: var(--space-4); background: var(--clr-surface-theme); border-bottom: 1px solid var(--clr-border-theme); align-items: center; gap: var(--space-3);">
                        <button class="back-to-conversations" id="backToConversations" style="background: none; border: none; color: var(--clr-primary); font-size: var(--text-lg); cursor: pointer; padding: var(--space-2);">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="chat-header-info">
                            <h3 id="mobileChatName" style="font-family: var(--font-heading); font-size: var(--text-base); font-weight: 600; color: var(--clr-text-primary); margin: 0;">Select a conversation</h3>
                        </div>
                    </div>

                    <!-- Desktop Chat Header -->
                    <div class="chat-header" id="chatHeader" style="padding: var(--space-4) var(--space-5); background: linear-gradient(90deg, rgba(212, 74, 108, 0.03) 0%, rgba(42, 157, 143, 0.03) 100%); border-bottom: 2px solid var(--clr-border-theme); display: flex; align-items: center; gap: var(--space-4);">
                        <div class="chat-header-avatar" id="headerAvatar" style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; box-shadow: 0 2px 8px rgba(212, 74, 108, 0.2);">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="chat-header-info">
                            <h3 id="headerName" style="font-family: var(--font-heading); font-size: var(--text-base); font-weight: 600; color: var(--clr-text-primary); margin: 0; margin-bottom: 4px;">Select a conversation</h3>
                            <div class="chat-status" id="headerStatus" style="font-size: var(--text-xs); color: var(--clr-text-tertiary);">Start chatting</div>
                        </div>
                    </div>

                    <div class="messages-area" id="messagesArea">
                        <div class="empty-chat" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--space-6) var(--space-4); color: var(--clr-text-tertiary); text-align: center;">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, rgba(212, 74, 108, 0.1) 0%, rgba(42, 157, 143, 0.1) 100%); display: flex; align-items: center; justify-content: center; margin-bottom: var(--space-5);">
                                <i class="fas fa-comment-dots" style="font-size: 32px; background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                            </div>
                            <h3 style="font-family: var(--font-heading); font-size: var(--text-xl); font-weight: 600; color: var(--clr-text-primary); margin: 0; margin-bottom: var(--space-2);">No conversation selected</h3>
                            <p style="color: var(--clr-text-secondary); max-width: 300px; line-height: 1.6;">Choose a conversation from the list to start messaging</p>
                            
                            <div class="security-features" style="margin-top: var(--space-6); padding: var(--space-5); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); background: linear-gradient(135deg, rgba(212, 74, 108, 0.03) 0%, rgba(42, 157, 143, 0.03) 100%); max-width: 420px;">
                                <h4 style="font-family: var(--font-heading); font-size: var(--text-sm); font-weight: 600; color: var(--clr-text-primary); margin: 0; margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                                    <i class="fas fa-shield-alt" style="color: var(--clr-primary);"></i>
                                    Security Features
                                </h4>
                                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--space-2);">
                                    <li style="font-size: var(--text-xs); color: var(--clr-text-secondary); display: flex; align-items: center; gap: var(--space-2);"><i class="fas fa-lock" style="color: var(--clr-primary); font-size: 10px;"></i> End-to-End Encryption — Messages are encrypted before leaving your device</li>
                                    <li style="font-size: var(--text-xs); color: var(--clr-text-secondary); display: flex; align-items: center; gap: var(--space-2);"><i class="fas fa-database" style="color: var(--clr-secondary); font-size: 10px;"></i> Secure Storage — Encrypted messages stored in database</li>
                                    <li style="font-size: var(--text-xs); color: var(--clr-text-secondary); display: flex; align-items: center; gap: var(--space-2);"><i class="fas fa-key" style="color: var(--clr-primary); font-size: 10px;"></i> Private Keys — Each user has unique encryption keys</li>
                                    <li style="font-size: var(--text-xs); color: var(--clr-text-secondary); display: flex; align-items: center; gap: var(--space-2);"><i class="fas fa-sync" style="color: var(--clr-secondary); font-size: 10px;"></i> Real-time Decryption — Messages decrypted only when displayed</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Encryption Status Indicator -->
                    <div class="encryption-status" id="encryptionStatus" style="display: none; padding: var(--space-2) var(--space-4); background: linear-gradient(135deg, rgba(212, 74, 108, 0.05) 0%, rgba(42, 157, 143, 0.05) 100%); border-bottom: 1px solid var(--clr-border-theme); align-items: center; gap: var(--space-2); font-size: var(--text-xs); color: var(--clr-text-secondary);">
                        <i class="fas fa-lock" style="color: var(--clr-primary); font-size: 10px;"></i>
                        <span>End-to-End Encrypted • Messages secured with AES-256</span>
                    </div>

                    <div class="message-form" id="messageForm" style="display: none; padding: var(--space-4); border-top: 2px solid var(--clr-border-theme); background: linear-gradient(180deg, var(--clr-surface-theme) 0%, rgba(212, 74, 108, 0.02) 100%);">
                        <div class="message-input-wrapper" style="display: flex; gap: var(--space-2); align-items: flex-end;">
                            <textarea class="message-input" id="messageText" placeholder="Type your encrypted message..." rows="1" data-badword-action="delete" style="flex: 1; padding: var(--space-3) var(--space-4); border: 2px solid var(--clr-border-theme); border-radius: var(--radius-lg); background: var(--clr-surface-theme); color: var(--clr-text-primary); font-family: var(--font-body); font-size: var(--text-sm); resize: none; min-height: 44px; max-height: 120px; transition: border-color 0.2s ease;"></textarea>
                            <button class="send-button btn btn-primary" id="sendMessage" style="width: 44px; height: 44px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; padding: 0; margin-bottom: 1px;">
                                <i class="fas fa-paper-plane" style="font-size: var(--text-sm);"></i>
                            </button>
                        </div>
                        <div class="encryption-info" style="display: flex; align-items: center; gap: var(--space-2); margin-top: var(--space-2); font-size: 11px; color: var(--clr-text-tertiary);">
                            <i class="fas fa-lock" style="color: var(--clr-primary); font-size: 9px;"></i> Your message will be encrypted before sending
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    <div class="auto-refresh-indicator" id="autoRefreshIndicator">
        <i class="fas fa-sync-alt fa-spin"></i>
        <span>Auto-refreshing messages...</span>
    </div>



    <script src="../js/mode.js"></script>
    <script src="../js/badword-filter.js"></script>
    <script>
    // Global variables for auto-refresh
    let autoRefreshInterval = null;
    let lastMessageCount = 0;
    let isAutoRefreshing = false;
    let activeConversationId = null;
    let activeUserId = null;
    
    // Debug function
    function debugInfo() {
        console.log('=== DEBUG INFO ===');
        console.log('Active Conversation ID:', activeConversationId);
        console.log('Active User ID:', activeUserId);
        console.log('Is Auto Refreshing:', isAutoRefreshing);
        console.log('Last Message Count:', lastMessageCount);
        
        // Check if conversation items have correct data attributes
        const conversationItems = document.querySelectorAll('.conversation-item');
        console.log('Conversation Items:', conversationItems.length);
        conversationItems.forEach((item, index) => {
            console.log(`Item ${index}:`, {
                conversationId: item.getAttribute('data-conversation-id'),
                userId: item.getAttribute('data-user-id'),
                text: item.querySelector('h4').textContent
            });
        });
    }

    // Format last seen time for JavaScript
    function formatLastSeen(timestamp) {
        if (!timestamp) return 'a long time ago';
        
        const lastSeen = new Date(timestamp);
        const now = new Date();
        const diff = now - lastSeen;
        
        if (diff < 60000) {
            return 'just now';
        } else if (diff < 3600000) {
            return Math.floor(diff / 60000) + ' min ago';
        } else if (diff < 86400000) {
            const hours = Math.floor(diff / 3600000);
            return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        } else {
            const days = Math.floor(diff / 86400000);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        }
    }

    // Update message count function
    function updateMessageCount() {
        fetch('../api/get_message_count.php')
            .then(response => response.json())
            .then(data => {
                const messageBadge = document.getElementById('messageBadge');
                const sidebarMessageBadge = document.querySelector('.nav-item[href="message.php"] .badge');
                
                if (data.count > 0) {
                    if (messageBadge) messageBadge.textContent = data.count;
                    if (sidebarMessageBadge) sidebarMessageBadge.textContent = data.count;
                    
                    // Show badges
                    if (messageBadge) messageBadge.style.display = 'inline';
                    if (sidebarMessageBadge) sidebarMessageBadge.style.display = 'inline';
                } else {
                    // Hide badges
                    if (messageBadge) messageBadge.style.display = 'none';
                    if (sidebarMessageBadge) sidebarMessageBadge.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching message count:', error);
            });
    }

    // Update notification count function
    function updateNotificationCount() {
        fetch('../api/get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                const notificationBadge = document.getElementById('notificationBadge');
                const sidebarNotificationBadge = document.querySelector('.nav-item[href="notifications.php"] .badge');
                const topNavNotificationDot = document.querySelector('.notification-btn .notification-dot');
                
                if (data.count > 0) {
                    if (notificationBadge) notificationBadge.textContent = data.count;
                    if (sidebarNotificationBadge) sidebarNotificationBadge.textContent = data.count;
                    
                    // Show badges and dot
                    if (notificationBadge) notificationBadge.style.display = 'inline';
                    if (sidebarNotificationBadge) sidebarNotificationBadge.style.display = 'inline';
                    if (topNavNotificationDot) topNavNotificationDot.style.display = 'block';
                } else {
                    // Hide badges and dot
                    if (notificationBadge) notificationBadge.style.display = 'none';
                    if (sidebarNotificationBadge) sidebarNotificationBadge.style.display = 'none';
                    if (topNavNotificationDot) topNavNotificationDot.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching notification count:', error);
            });
    }

    // FIXED: XSS Prevention function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Encryption status functions
    function showEncryptionStatus() {
        const encryptionStatus = document.getElementById('encryptionStatus');
        if (encryptionStatus && activeConversationId) {
            encryptionStatus.style.display = 'flex';
        }
    }

    function hideEncryptionStatus() {
        const encryptionStatus = document.getElementById('encryptionStatus');
        if (encryptionStatus) {
            encryptionStatus.style.display = 'none';
        }
    }

    // Update message display to show encryption indicators
    function updateMessageEncryptionIndicators() {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            const messageText = message.querySelector('.message-text');
            if (messageText) {
                if (messageText.textContent.includes('[Encrypted message]')) {
                    messageText.innerHTML = '<i class="fas fa-lock"></i> Encrypted message';
                    messageText.classList.add('decryption-error');
                } else if (messageText.textContent.includes('[Unable to decrypt this message]')) {
                    messageText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Unable to decrypt this message';
                    messageText.classList.add('decryption-error');
                }
            }
        });
    }

    // Auto-refresh functions
    function startAutoRefresh() {
        if (activeConversationId && !isAutoRefreshing) {
            // Refresh every 3 seconds when a conversation is active
            autoRefreshInterval = setInterval(() => {
                if (activeConversationId) {
                    refreshMessages(activeConversationId);
                }
            }, 3000);
            isAutoRefreshing = true;
            
            // Show indicator for a moment
            showAutoRefreshIndicator();
        }
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            isAutoRefreshing = false;
        }
    }
    
    function showAutoRefreshIndicator() {
        const indicator = document.getElementById('autoRefreshIndicator');
        if (indicator) {
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
    }
    
    // Check for new messages and update if needed
    async function refreshMessages(conversationId) {
        if (!conversationId) return;
        
        try {
            const response = await fetch(`message.php?conversation_id=${conversationId}`);
            const messages = await response.json();
            
            // Check if message count has changed
            if (messages.length !== lastMessageCount) {
                // Only reload if we have new messages
                if (messages.length > lastMessageCount) {
                    loadMessages(conversationId, false); // false = don't show loading indicator
                    showAutoRefreshIndicator();
                    
                    // Update conversation list preview
                    if (messages.length > 0) {
                        updateConversationPreview(conversationId, messages[messages.length - 1]);
                    }
                }
                lastMessageCount = messages.length;
            }
        } catch (error) {
            console.error('Error refreshing messages:', error);
        }
    }
    
    // Update conversation preview in the sidebar
    function updateConversationPreview(conversationId, lastMessage) {
        const conversationItem = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
        if (conversationItem) {
            const preview = conversationItem.querySelector('.conversation-preview');
            const time = conversationItem.querySelector('.conversation-time');
            
            if (preview) {
                let previewText = lastMessage.message_text;
                if (lastMessage.is_encrypted) {
                    previewText = '🔒 Encrypted message';
                }
                preview.textContent = previewText;
            }
            
            if (time) {
                time.textContent = formatTime(new Date(lastMessage.timestamp));
            }
            
            // Add new message indicator
            if (!conversationItem.classList.contains('active')) {
                if (!conversationItem.querySelector('.new-message-indicator')) {
                    const indicator = document.createElement('div');
                    indicator.className = 'new-message-indicator';
                    conversationItem.appendChild(indicator);
                }
            }
        }
    }

    // Format time for display
    function formatTime(timestamp) {
        const now = new Date();
        const diff = now - timestamp;
        
        if (diff < 60000) {
            return 'Just now';
        } else if (diff < 3600000) {
            return Math.floor(diff / 60000) + 'm ago';
        } else if (diff < 86400000) {
            return Math.floor(diff / 3600000) + 'h ago';
        } else {
            return timestamp.toLocaleDateString();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        let isMobile = window.innerWidth <= 768;
        window.addEventListener('resize', function() {
            isMobile = window.innerWidth <= 768;
        });
        const conversationsSidebar = document.getElementById('conversationsSidebar');
        const chatArea = document.getElementById('chatArea');
        const mobileChatHeader = document.getElementById('mobileChatHeader');
        const backToConversations = document.getElementById('backToConversations');
        const searchInput = document.getElementById('searchInput');
        const searchResultsContainer = document.getElementById('searchResultsContainer');
        const conversationsList = document.getElementById('conversationsList');
        const messagesArea = document.getElementById('messagesArea');
        const messageForm = document.getElementById('messageForm');
        const messageText = document.getElementById('messageText');
        const sendButton = document.getElementById('sendMessage');
        const headerName = document.getElementById('headerName');
        const headerAvatar = document.getElementById('headerAvatar');
        const headerStatus = document.getElementById('headerStatus');
        const mobileChatName = document.getElementById('mobileChatName');
        const encryptionStatus = document.getElementById('encryptionStatus');
        
        // FIXED: Mobile menu functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebar = document.getElementById('sidebar');

        // Initialize mobile menu event listeners
        function initMobileMenu() {
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.remove('active');
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar when clicking on nav items (mobile)
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            });
        }

        // Initialize counts
        updateMessageCount();
        updateNotificationCount();
        
        // Update counts every 30 seconds
        setInterval(updateMessageCount, 30000);
        setInterval(updateNotificationCount, 30000);
        
        // Update counts when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateMessageCount();
                updateNotificationCount();
                // Also refresh messages if we have an active conversation
                if (activeConversationId) {
                    refreshMessages(activeConversationId);
                }
            }
        });

        // Update online status periodically
        function startOnlineStatusUpdater() {
            // Update user's own status every 2 minutes
            setInterval(() => {
                fetch('message.php?update_online_status=1')
                    .catch(err => console.log('Status update failed:', err));
            }, 120000);
            
            // Update status for active conversation every 30 seconds
            setInterval(updateActiveUserStatus, 30000);
        }

        // Update status for the currently active user
        async function updateActiveUserStatus() {
            if (activeUserId) {
                try {
                    const response = await fetch(`message.php?get_status=${activeUserId}`);
                    const statusData = await response.json();
                    
                    // Update chat header status
                    const statusElement = document.getElementById('headerStatus');
                    if (statusElement) {
                        statusElement.innerHTML = `
                            <span class="status-dot ${statusData.is_online ? 'online' : 'offline'}"></span>
                            ${escapeHtml(statusData.status_text)}
                        `;
                        statusElement.className = `chat-status ${statusData.is_online ? 'online' : 'offline'}`;
                    }
                    
                    // Update mobile header
                    const mobileHeader = document.getElementById('mobileChatHeader');
                    if (mobileHeader && mobileHeader.style.display !== 'none') {
                        const existingStatus = mobileHeader.querySelector('.mobile-status');
                        if (existingStatus) {
                            existingStatus.remove();
                        }
                        const statusDiv = document.createElement('div');
                        statusDiv.className = `mobile-status chat-status ${statusData.is_online ? 'online' : 'offline'}`;
                        statusDiv.style.fontSize = '0.8rem';
                        statusDiv.style.marginTop = '0.25rem';
                        statusDiv.innerHTML = `
                            <span class="status-dot ${statusData.is_online ? 'online' : 'offline'}"></span>
                            ${escapeHtml(statusData.status_text)}
                        `;
                        mobileHeader.querySelector('.chat-header-info').appendChild(statusDiv);
                    }
                    
                    // Update conversation list status
                    const conversationItem = document.querySelector(`.conversation-item[data-user-id="${activeUserId}"]`);
                    if (conversationItem) {
                        const statusElement = conversationItem.querySelector('.status-text');
                        if (statusElement) {
                            statusElement.textContent = statusData.status_text;
                            statusElement.className = `status-text ${statusData.is_online ? 'online' : 'offline'}`;
                        }
                        
                        // Update online indicator
                        const avatar = conversationItem.querySelector('.conversation-avatar');
                        let indicator = avatar.querySelector('.online-indicator');
                        if (statusData.is_online) {
                            if (!indicator) {
                                indicator = document.createElement('div');
                                indicator.className = 'online-indicator';
                                avatar.appendChild(indicator);
                            }
                        } else if (indicator) {
                            indicator.remove();
                        }
                    }
                    
                } catch (error) {
                    console.error('Error updating status:', error);
                }
            }
        }

        // Mobile navigation functions
        function showConversations() {
            if (isMobile) {
                conversationsSidebar.classList.remove('hidden');
                chatArea.classList.remove('active');
                mobileChatHeader.style.display = 'none';
                hideEncryptionStatus();
                stopAutoRefresh();
            }
        }

        function showChat() {
            if (isMobile) {
                conversationsSidebar.classList.add('hidden');
                chatArea.classList.add('active');
                mobileChatHeader.style.display = 'flex';
                showEncryptionStatus();
                startAutoRefresh();
            }
        }

        // Set initial state
        if (isMobile) {
            showConversations();
        }

        // Back button handler
        backToConversations.addEventListener('click', showConversations);

        // FIXED: Handle conversation selection - IMPROVED VERSION
        conversationsList.addEventListener('click', function(e) {
            const conversationItem = e.target.closest('.conversation-item');
            if (conversationItem) {
                console.log('Conversation clicked:', conversationItem);
                
                // Get the conversation data - FIXED: using data-conversation-id
                const conversationId = conversationItem.getAttribute('data-conversation-id');
                const userId = conversationItem.getAttribute('data-user-id');
                
                console.log('Conversation ID:', conversationId);
                console.log('User ID:', userId);
                
                if (!conversationId) {
                    console.error('No conversation ID found!');
                    alert('Error: No conversation ID found. Please try again.');
                    return;
                }
                
                // Remove active class from all items
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                    // Remove new message indicator when clicked
                    const indicator = item.querySelector('.new-message-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                });
                
                // Add active class to clicked item
                conversationItem.classList.add('active');
                
                // Update active conversation
                activeConversationId = conversationId;
                activeUserId = userId;
                
                // Update chat header
                const userName = conversationItem.querySelector('h4').textContent;
                const userAvatar = conversationItem.querySelector('.conversation-avatar').innerHTML;
                const statusText = conversationItem.querySelector('.status-text').textContent;
                const isOnline = conversationItem.querySelector('.status-text').classList.contains('online');
                
                headerName.textContent = userName;
                mobileChatName.textContent = userName;
                headerAvatar.innerHTML = userAvatar;
                headerStatus.innerHTML = `
                    <span class="status-dot ${isOnline ? 'online' : 'offline'}"></span>
                    ${escapeHtml(statusText)}
                `;
                headerStatus.className = `chat-status ${isOnline ? 'online' : 'offline'}`;
                
                // Show message form and hide empty state
                messageForm.style.display = 'block';
                messagesArea.innerHTML = '';
                
                // Handle mobile navigation
                showChat();
                
                // Show encryption status
                showEncryptionStatus();
                
                // Load messages
                loadMessages(activeConversationId);
                
                // Start status updates for this user
                updateActiveUserStatus();
                
                // Start auto-refresh for this conversation
                startAutoRefresh();
            }
        });

        // FIXED: Load messages for a conversation - IMPROVED VERSION
        async function loadMessages(conversationId, showLoading = true) {
            console.log('Loading messages for conversation:', conversationId);
            
            try {
                if (showLoading) {
                    messagesArea.innerHTML = `
                        <div class="loading-messages">
                            <i class="fas fa-spinner fa-spin"></i>
                            <h3>Loading messages...</h3>
                        </div>
                    `;
                }
                
                const response = await fetch(`message.php?conversation_id=${conversationId}`);
                console.log('API Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const messages = await response.json();
                console.log('Messages received:', messages);
                
                // Update last message count for auto-refresh
                lastMessageCount = messages.length;
                
                if (messages.length === 0) {
                    console.log('No messages found for this conversation');
                    messagesArea.innerHTML = `
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <h3>No messages yet</h3>
                            <p>Start the conversation by sending a message</p>
                        </div>
                    `;
                    return;
                }
                
                // Clear and populate messages area
                messagesArea.innerHTML = '';
                messages.forEach((msg, index) => {
                    console.log(`Message ${index}:`, msg);
                    
                    const messageElement = document.createElement('div');
                    messageElement.className = `message ${msg.sender === 'You' ? 'you' : 'other'}`;
                    
                    const timestamp = new Date(msg.timestamp);
                    const timeString = formatTime(timestamp);
                    
                    // Handle encrypted message display
                    let messageContent = escapeHtml(msg.message_text);
                    
                    const avatarContent = msg.sender === 'You' 
                        ? '<i class="fas fa-user"></i>'
                        : (msg.profile_pic && msg.profile_pic !== 'default.png' 
                            ? `<img src="../${escapeHtml(msg.profile_pic)}" alt="${escapeHtml(msg.sender)}" onerror="handleImageError(this)"><div class="avatar-fallback">${escapeHtml(msg.sender.charAt(0).toUpperCase())}</div>`
                            : `<span>${escapeHtml(msg.sender.charAt(0).toUpperCase())}</span>`);
                    
                    messageElement.innerHTML = `
                        <div class="message-avatar">
                            ${avatarContent}
                        </div>
                        <div class="message-content">
                            ${msg.sender !== 'You' ? `<div class="message-sender">${escapeHtml(msg.sender)}</div>` : ''}
                            <div class="message-text">${messageContent}</div>
                            <div class="message-time">${escapeHtml(timeString)}</div>
                        </div>
                    `;
                    
                    messagesArea.appendChild(messageElement);
                });
                
                // Scroll to bottom
                messagesArea.scrollTop = messagesArea.scrollHeight;
                
                // Update encryption indicators
                updateMessageEncryptionIndicators();
                
            } catch (error) {
                console.error('Error loading messages:', error);
                messagesArea.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error loading messages</h3>
                        <p>${error.message}</p>
                        <button onclick="loadMessages('${conversationId}')" class="retry-btn" style="margin-top: 10px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                    </div>
                `;
            }
        }

        // Handle sending messages
        sendButton.addEventListener('click', sendMessage);
        messageText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        async function sendMessage() {
            const text = messageText.value.trim();
            if (text && activeConversationId) {
                try {
                    const formData = new URLSearchParams();
                    formData.append('conversation_id', activeConversationId);
                    formData.append('message_text', text);

                    const response = await fetch('message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        messageText.value = '';
                        messageText.style.height = 'auto';
                        
                        // Reload messages to show the new one
                        loadMessages(activeConversationId, false);
                        
                        // Update message count
                        updateMessageCount();
                    } else if (result.status === 'error') {
                        alert(result.message || 'Error sending message');
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    alert('Error sending message. Please try again.');
                }
            }
        }

        // Auto-resize textarea
        messageText.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Search functionality
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });

        async function performSearch() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                searchResultsContainer.classList.remove('active');
                return;
            }

            try {
                searchResultsContainer.classList.add('loading');
                
                const response = await fetch(`message.php?search=${encodeURIComponent(query)}`);
                const users = await response.json();
                
                if (users.length === 0) {
                    searchResultsContainer.innerHTML = `
                        <div class="search-result-item">
                            <div class="search-result-info">
                                <span>No users found</span>
                            </div>
                        </div>
                    `;
                } else {
                    searchResultsContainer.innerHTML = users.map(user => `
                        <div class="search-result-item" data-user-id="${user.id}">
                            <div class="search-result-avatar">
                                ${user.profile_pic && user.profile_pic !== 'default.png' 
                                    ? `<img src="../${escapeHtml(user.profile_pic)}" alt="${escapeHtml(user.username)}" onerror="handleImageError(this)"><div class="avatar-fallback">${escapeHtml(user.username.charAt(0).toUpperCase())}</div>`
                                    : `<span>${escapeHtml(user.username.charAt(0).toUpperCase())}</span>`}
                                ${user.is_online ? '<div class="online-indicator"></div>' : ''}
                            </div>
                            <div class="search-result-info">
                                <span>${escapeHtml(user.username)}</span>
                                <div class="search-result-status ${user.is_online ? 'online' : 'offline'}">
                                    ${user.is_online ? 'Online' : 'Last seen ' + formatLastSeen(user.last_seen)}
                                </div>
                            </div>
                            <button class="start-conversation-btn">Message</button>
                        </div>
                    `).join('');
                }
                
                searchResultsContainer.classList.add('active');
                searchResultsContainer.classList.remove('loading');
            } catch (error) {
                console.error('Error searching users:', error);
                searchResultsContainer.innerHTML = `
                    <div class="search-result-item">
                        <div class="search-result-info">
                            <span>Error searching users</span>
                        </div>
                    </div>
                `;
                searchResultsContainer.classList.remove('loading');
            }
        }

        // Handle starting conversation from search results
        searchResultsContainer.addEventListener('click', async function(e) {
            if (e.target.classList.contains('start-conversation-btn')) {
                const searchItem = e.target.closest('.search-result-item');
                const userId = searchItem.getAttribute('data-user-id');
                const userName = searchItem.querySelector('span').textContent;
                const userAvatar = searchItem.querySelector('.search-result-avatar').innerHTML;
                
                try {
                    const response = await fetch(`message.php?user_id=${userId}`);
                    const result = await response.json();
                    
                    if (result.status === 'existing' || result.status === 'new') {
                        // FIXED: Check if conversation already exists by user ID, not conversation ID
                        let existingItem = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
                        
                        if (existingItem) {
                            // Conversation exists, just select it
                            existingItem.click();
                        } else {
                            // Create new conversation item
                            const conversationItem = document.createElement('div');
                            conversationItem.className = 'conversation-item active';
                            conversationItem.setAttribute('data-conversation-id', result.conversation_id);
                            conversationItem.setAttribute('data-user-id', userId);
                            
                            conversationItem.innerHTML = `
                                <div class="conversation-avatar">
                                    ${userAvatar}
                                </div>
                                <div class="conversation-info">
                                    <h4>${escapeHtml(userName)}</h4>
                                    <p class="conversation-preview">Start a conversation</p>
                                    <div class="conversation-time">Just now</div>
                                </div>
                                <div class="conversation-status">
                                    <span class="status-text offline">Online</span>
                                </div>
                            `;
                            
                            // Add to top of conversations list
                            const emptyState = conversationsList.querySelector('.empty-conversations');
                            if (emptyState) {
                                emptyState.remove();
                            }
                            
                            // FIXED: Remove any existing conversation with same user before adding new one
                            const existingByUser = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
                            if (existingByUser) {
                                existingByUser.remove();
                            }
                            
                            conversationsList.insertBefore(conversationItem, conversationsList.firstChild);
                            
                            // Select the new conversation
                            conversationItem.click();
                        }
                        
                        // Clear search
                        searchInput.value = '';
                        searchResultsContainer.classList.remove('active');
                    }
                } catch (error) {
                    console.error('Error starting conversation:', error);
                    alert('Error starting conversation. Please try again.');
                }
            }
        });

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResultsContainer.contains(e.target)) {
                searchResultsContainer.classList.remove('active');
            }
        });

        // FIXED: Initialize mobile menu
        initMobileMenu();

        // Start the online status updater
        startOnlineStatusUpdater();

        // Initialize encryption status
        hideEncryptionStatus();

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                // Reset mobile states when switching to desktop
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    </script>
</body>
</html>