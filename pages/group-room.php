<?php
session_start();
include "../includes/db.php";
include "../includes/group-room.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$userGroups = getUserGroups($conn, $user_id);

$selectedGroupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$selectedGroup = null;
$groupMessages = [];
$groupMembers = [];

if ($selectedGroupId) {
    if (isUserInGroup($conn, $user_id, $selectedGroupId)) {
        $sql = "SELECT * FROM chat_groups WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selectedGroupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $selectedGroup = $result->fetch_assoc();
        $stmt->close();

        $groupMessages = getGroupMessages($conn, $selectedGroupId);
        $groupMembers = getGroupMembers($conn, $selectedGroupId);
    }
}

$user_sql = "SELECT username, profile_pic FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();
$user_stmt->close();

$allUsers = getAllUsers($conn, $user_id);
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
    <link rel="stylesheet" href="../css/group-room.css">
    <link rel="icon" href="/logo/m-blues.png" type="image/png">
    <title>Group Chats | MUFFEIA</title>
</head>
<body data-user-id="<?php echo $user_id; ?>">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="wrapper">
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
                <span class="theme-label">Dark Mode</span>
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
                </a>
                <a href="group-room.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Group Chats</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
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

        <div class="main-content">
            <header class="top-nav">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="top-nav-content">
                    <h1>Group Chats</h1>
                </div>
            </header>

            <div class="group-chat-wrapper">
                <div class="group-list-sidebar">
                    <div class="group-list-header">
                        <h2>Groups</h2>
                        <button class="btn-create-group" id="btnCreateGroup" title="Create new group">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="group-list" id="groupList">
                        <?php if (!empty($userGroups)): ?>
                            <?php foreach ($userGroups as $group): ?>
                                <a href="?group_id=<?php echo $group['id']; ?>"
                                   class="group-item <?php echo $selectedGroupId === $group['id'] ? 'active' : ''; ?>">
                                    <div class="group-item-avatar">
                                        <?php echo strtoupper(substr($group['name'], 0, 1)); ?>
                                    </div>
                                    <div class="group-item-body">
                                        <div class="group-item-name"><?php echo htmlspecialchars($group['name']); ?></div>
                                        <div class="group-item-preview">
                                            <?php
                                            if ($group['last_message']) {
                                                echo htmlspecialchars(substr($group['last_message'], 0, 50));
                                            } else {
                                                echo 'No messages yet';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: var(--space-5); text-align: center; color: var(--clr-text-tertiary);">
                                <p>No groups yet.</p>
                                <button class="group-list-empty-btn" id="btnCreateGroupEmpty">
                                    <i class="fas fa-plus"></i> Create Group
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="group-chat-area">
                    <?php if ($selectedGroup): ?>
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <h3><?php echo htmlspecialchars($selectedGroup['name']); ?></h3>
                                <p><?php echo count($groupMembers); ?> members</p>
                            </div>
                            <div class="chat-actions">
                                <button class="chat-action-btn" id="btnShowMembers" title="Show members">
                                    <i class="fas fa-users"></i>
                                </button>
                                <button class="chat-action-btn" id="btnAddMember" title="Add member">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <?php if ($selectedGroup['creator_id'] === $user_id): ?>
                                    <button class="chat-action-btn" id="btnDeleteGroup" title="Delete group" style="color: #e74c3c;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="messages-container" id="messagesContainer">
                            <?php if (!empty($groupMessages)): ?>
                                <?php foreach ($groupMessages as $message): ?>
                                    <div class="message-item <?php echo $message['sender_id'] === $user_id ? 'own' : ''; ?>">
                                        <?php if ($message['sender_id'] !== $user_id): ?>
                                            <div class="message-avatar" title="<?php echo htmlspecialchars($message['username']); ?>">
                                                <?php if (!empty($message['profile_pic'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($message['profile_pic']); ?>" alt="">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($message['username'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-content">
                                            <div class="message-text"><?php echo htmlspecialchars($message['message_text']); ?></div>
                                            <div class="message-meta">
                                                <span><?php echo htmlspecialchars($message['username']); ?></span>
                                                <span><?php echo date('M j, Y g:i A', strtotime($message['sent_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-comments"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="message-input-area">
                            <form class="message-input-form" id="messageForm">
                                <textarea
                                    id="messageInput"
                                    placeholder="Type your message..."
                                    rows="1"
                                    required></textarea>
                                <button type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                    Send
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="no-group-selected">
                            <i class="fas fa-comments"></i>
                            <p>Select a group to start chatting</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="createGroupModal">
        <div class="modal">
            <div class="modal-header">
                <h2>New Group</h2>
                <button type="button" class="modal-close" id="btnCancelCreateGroup">&times;</button>
            </div>
            <form id="createGroupForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="groupName">Group Name</label>
                        <input type="text" id="groupName" name="name" placeholder="Enter group name..." required>
                    </div>
                    <div class="form-group">
                        <label>Add Members</label>
                        <div class="users-list" id="usersList">
                            <?php if (!empty($allUsers)): ?>
                                <?php foreach ($allUsers as $u): ?>
                                    <label class="user-item">
                                        <input type="checkbox" name="members" value="<?php echo $u['id']; ?>">
                                        <span class="check-mark"><i class="fas fa-check"></i></span>
                                        <div class="message-avatar">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <span class="user-item-name"><?php echo htmlspecialchars($u['username']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="padding: var(--space-4); text-align: center; color: var(--clr-text-tertiary);">No other users found</p>
                            <?php endif; ?>
                        </div>
                        <div class="selected-count" id="selectedCount">0 selected</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="btnCancelCreateGroupFooter">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnCreateGroupSubmit">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="membersModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Group Members</h2>
                <button type="button" class="modal-close" id="btnCloseMembersModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="users-list" id="membersList">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btnCloseMembersModalFooter">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="addMemberModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add Member</h2>
                <button type="button" class="modal-close" id="btnCancelAddMember">&times;</button>
            </div>
            <form id="addMemberForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="userSelect">Select User</label>
                        <select id="userSelect" name="user_id" required>
                            <option value="">Choose a user...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="btnCancelAddMemberFooter">Cancel</button>
                    <button type="submit" class="btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/mode.js"></script>
    <script src="../js/badword-filter.js"></script>
    <script src="../js/group-room.js"></script>
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>
