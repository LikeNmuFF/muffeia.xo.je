<?php
// pages/chatbot.php
session_start();
require_once '../includes/db.php';
require_once '../includes/env_loader.php';
require_once '../includes/FreeHuggingFaceChatbot.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = '';
$ai_response = '';

try {
    $chatbot = new FreeHuggingFaceChatbot();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && empty($error)) {
    $user_message = trim($_POST['message']);
    
    if (!empty($user_message)) {
        // Initialize chat history if not exists
        if (!isset($_SESSION['chat_history'])) {
            $_SESSION['chat_history'] = [];
        }
        
        // Add user message to history
        $_SESSION['chat_history'][] = [
            'role' => 'user',
            'message' => $user_message,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // Get AI response
        try {
            $ai_response = $chatbot->sendMessage($user_message);
            
            // Add AI response to history
            $_SESSION['chat_history'][] = [
                'role' => 'assistant', 
                'message' => $ai_response,
                'time' => date('Y-m-d H:i:s')
            ];
            
            // Keep only last 10 messages to manage session size
            if (count($_SESSION['chat_history']) > 10) {
                $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
            }
            
        } catch (Exception $e) {
            $error = "Failed to get AI response: " . $e->getMessage();
        }
    }
}

// Clear chat history
if (isset($_GET['clear'])) {
    unset($_SESSION['chat_history']);
    header("Location: chatbot.php");
    exit();
}

$chat_history = $_SESSION['chat_history'] ?? [];
$chat_history_count = count($chat_history);
$chat_session_state = $chat_history_count > 0 ? 'Active session' : 'Fresh start';
$chat_username = $_SESSION['username'] ?? 'You';
$assistant_status = empty($error) ? 'Online' : 'Offline';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/modern-theme.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <title>MUFFEIA - AI Assistant</title>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                <img src="../logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                <!-- Dark mode logo -->
                <img src="../logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
                    <span>MUFFEIA</span>
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
                <a href="message.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge" id="messageBadge" style="display: none;">0</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <span class="badge" id="notificationBadge" style="display: none;">0</span>
                </a>
                <a href="chatbot.php" class="nav-item active">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
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
                    <h1>AI Assistant</h1>
                    <div class="user-actions">
                        <a href="message.php" class="icon-btn message-btn" role="button">
                            <i class="fas fa-envelope"></i>
                        </a>
                        <a href="notifications.php" class="icon-btn notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot" style="display: none;"></span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content chatbot-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <section class="chatbot-hero">
                    <div class="hero-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="hero-text">
                        <h2>MUFFEIA AI Assistant</h2>
                        <p>Your always-on teammate for solving problems, drafting ideas, and navigating every corner of MUFFEIA.</p>
                        <div class="hero-tags">
                            <span class="chatbot-pill"><i class="fas fa-bolt"></i> DialoGPT</span>
                            <span class="chatbot-pill"><i class="fas fa-layer-group"></i> Platform-Aware Replies</span>
                            <span class="chatbot-pill"><i class="fas fa-shield-alt"></i> Secure Session</span>
                            <span class="chatbot-pill"><i class="fas fa-infinity"></i> Available 24/7</span>
                        </div>
                    </div>
                    <div class="hero-metrics">
                        <div class="hero-metric">
                            <span class="metric-label">Messages</span>
                            <span class="metric-value"><?php echo $chat_history_count; ?></span>
                        </div>
                        <div class="hero-metric">
                            <span class="metric-label">Context Limit</span>
                            <span class="metric-value">10</span>
                        </div>
                        <div class="hero-metric">
                            <span class="metric-label">Session</span>
                            <span class="metric-value"><?php echo htmlspecialchars($chat_session_state); ?></span>
                        </div>
                        <div class="hero-metric">
                            <span class="metric-label">Status</span>
                            <span class="metric-value"><?php echo htmlspecialchars($assistant_status); ?></span>
                        </div>
                    </div>
                </section>

                <div class="chatbot-wrapper">
                    <!-- Sidebar -->
                    <aside class="chatbot-sidebar chatbot-panel">
                        <div class="ai-profile">
                            <div class="ai-avatar">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="ai-name">MUFFEIA AI</div>
                            <div class="ai-title">Problem-Solving Assistant</div>
                            <div class="chatbot-badge">
                                <i class="fas fa-bolt"></i> Always Free
                            </div>
                        </div>

                        <div class="chatbot-card">
                            <h4>Assistant Stats</h4>
                            <div class="chatbot-stats">
                                <div class="stat-item">
                                    <span>Model</span>
                                    <span class="stat-value">DialoGPT</span>
                                </div>
                                <div class="stat-item">
                                    <span>Status</span>
                                    <span class="stat-value" style="color: var(--success);"><?php echo htmlspecialchars($assistant_status); ?></span>
                                </div>
                                <div class="stat-item">
                                    <span>Powered by</span>
                                    <span class="stat-value">Muffeia</span>
                                </div>
                                <div class="stat-item">
                                    <span>Context Stored</span>
                                    <span class="stat-value"><?php echo $chat_history_count; ?>/10</span>
                                </div>
                                <div class="stat-item">
                                    <span>Specialized Prompts</span>
                                    <span class="stat-value">Community, Motivation, Self-care</span>
                                </div>
                            </div>
                        </div>

                        <div class="chatbot-card">
                            <h4>Quick Tips</h4>
                            <ul class="chatbot-tips">
                                <li><i class="fas fa-check-circle"></i><span>Ask follow-up questions so the bot can craft platform-ready posts or DMs with you.</span></li>
                                <li><i class="fas fa-check-circle"></i><span>Mention words like "post", "community", or "burnout" to unlock MUFFEIA-specific comfort replies.</span></li>
                                <li><i class="fas fa-check-circle"></i><span>Click the trash icon anytime to reset context and start a fresh support session.</span></li>
                            </ul>
                        </div>

                        <div class="chatbot-notice">
                            <i class="fas fa-shield-alt"></i>
                            Only the latest 10 messages are kept locally for context.
                        </div>
                    </aside>

                    <!-- Main Chat Area -->
                    <section class="chatbot-main chatbot-panel">
                        <div class="chatbot-main-header">
                            <div>
                                <h2>Live Conversation</h2>
                                <p><?php echo htmlspecialchars($chat_username); ?> chatting with MUFFEIA AI</p>
                            </div>
                            <div class="chat-actions">
                                <button type="button" class="icon-btn" onclick="clearChat()" title="Clear chat">
                                    <i class="fas fa-trash"></i>

                            </div>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php if ($chat_history_count === 0): ?>
                                <div class="chatbot-empty">
                                    <i class="fas fa-robot"></i>
                                    <h3>Hello! I'm MUFFEIA AI Assistant</h3>
                                    <p>Share a challenge, draft a message, or ask for guidance—I'll respond instantly with helpful context.</p>
                                    <div class="hero-tags">
                                        <span class="chatbot-pill"><i class="fas fa-brain"></i> Smart Assistant</span>
                                        <span class="chatbot-pill"><i class="fas fa-lock"></i> Private Session</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($chat_history as $msg): ?>
                                    <div class="message <?php echo $msg['role'] === 'user' ? 'user' : 'assistant'; ?>">
                                        <div class="message-avatar">
                                            <?php if ($msg['role'] === 'user'): ?>
                                                <i class="fas fa-user"></i>
                                            <?php else: ?>
                                                <i class="fas fa-robot"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-bubble">
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('g:i A', strtotime($msg['time'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="chat-input-area">
                            <form method="POST" class="input-wrapper" id="chatForm">
                                <textarea 
                                    name="message" 
                                    class="chat-input" 
                                    placeholder="Ask me anything about problems, solutions, or the platform..." 
                                    required
                                    id="messageInput"
                                    rows="1"
                                ></textarea>
                                <button type="submit" class="send-button" id="sendButton">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            const messageInput = document.getElementById('messageInput');
            const sendButton = document.getElementById('sendButton');
            const chatForm = document.getElementById('chatForm');
            
            // Auto-scroll to bottom
            function scrollToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            scrollToBottom();
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Handle form submission
            chatForm.addEventListener('submit', function(e) {
                const message = messageInput.value.trim();
                if (message === '') {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                sendButton.disabled = true;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Add typing indicator
                const typingIndicator = document.createElement('div');
                typingIndicator.className = 'message assistant';
                typingIndicator.innerHTML = `
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="typing-indicator">
                        <span>AI is thinking</span>
                        <div class="typing-dots">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                `;
                chatMessages.appendChild(typingIndicator);
                scrollToBottom();
            });
            
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function openSidebar() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            menuToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);
            
            // Chat actions
            window.clearChat = function() {
                if (confirm('Clear all chat history?')) {
                    window.location.href = '?clear=true';
                }
            };
            
            window.exportChat = function() {
                alert('Export feature coming soon!');
            };
            
            // Focus on input when page loads
            messageInput.focus();
            
            // Update message and notification counts
            function updateMessageCount() {
                fetch('../api/get_message_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const messageBadge = document.getElementById('messageBadge');
                        if (data.count > 0 && messageBadge) {
                            messageBadge.textContent = data.count;
                            messageBadge.style.display = 'inline';
                        }
                    });
            }
            
            function updateNotificationCount() {
                fetch('../api/get_notification_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const notificationBadge = document.getElementById('notificationBadge');
                        const notificationDot = document.querySelector('.notification-dot');
                        
                        if (data.count > 0) {
                            if (notificationBadge) {
                                notificationBadge.textContent = data.count;
                                notificationBadge.style.display = 'inline';
                            }
                            if (notificationDot) {
                                notificationDot.style.display = 'block';
                            }
                        }
                    });
            }
            
            // Initialize counts
            updateMessageCount();
            updateNotificationCount();
        });
    </script>
</body>
</html>