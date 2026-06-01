<?php
/**
 * Group Chat API Endpoint
 * Handles AJAX requests for group chat operations
 * 
 * Supported actions:
 * - create_group: Create a new group chat
 * - get_groups: Get all groups for the current user
 * - get_messages: Get messages for a specific group
 * - send_message: Send a message to a group
 * - add_member: Add a member to a group
 * - remove_member: Remove a member from a group
 * - get_members: Get members of a group
 * - delete_group: Delete a group (creator only)
 */

session_start();
include '../includes/db.php';
include '../includes/group-room.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : null);

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

switch ($action) {
    case 'create_group':
        handleCreateGroup();
        break;
    
    case 'get_groups':
        handleGetGroups();
        break;
    
    case 'get_messages':
        handleGetMessages();
        break;
    
    case 'send_message':
        handleSendMessage();
        break;
    
    case 'add_member':
        handleAddMember();
        break;
    
    case 'remove_member':
        handleRemoveMember();
        break;
    
    case 'get_members':
        handleGetMembers();
        break;
    
    case 'delete_group':
        handleDeleteGroup();
        break;
    
    case 'get_group_message_count':
        handleGetGroupMessageCount();
        break;
    
    case 'search_users':
        handleSearchUsers();
        break;
    
    case 'get_all_users':
        handleGetAllUsers();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

/**
 * Create a new group chat
 */
function handleCreateGroup() {
    global $conn, $user_id;
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $memberIds = isset($_POST['member_ids']) ? explode(',', $_POST['member_ids']) : [];
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Group name is required']);
        return;
    }
    
    $result = createGroup($conn, $name, $user_id, $memberIds);
    echo json_encode($result);
}

/**
 * Get all groups for the current user
 */
function handleGetGroups() {
    global $conn, $user_id;
    
    $groups = getUserGroups($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'groups' => $groups
    ]);
}

/**
 * Get messages for a specific group
 */
function handleGetMessages() {
    global $conn, $user_id;
    
    $groupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        return;
    }
    
    // Verify user is in group
    if (!isUserInGroup($conn, $user_id, $groupId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Mark messages as read
    markGroupMessagesAsRead($conn, $groupId, $user_id);
    
    $messages = getGroupMessages($conn, $groupId, $limit, $offset);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}

/**
 * Send a message to a group
 */
function handleSendMessage() {
    global $conn, $user_id;
    
    $groupId = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $messageText = isset($_POST['message']) ? $_POST['message'] : '';
    
    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        return;
    }
    
    if (empty($messageText)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        return;
    }
    
    // Verify user is in group
    if (!isUserInGroup($conn, $user_id, $groupId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $result = sendGroupMessage($conn, $groupId, $user_id, $messageText);
    
    if ($result['success']) {
        // Get the message details
        $messages = getGroupMessages($conn, $groupId, 1, 0);
        if (!empty($messages)) {
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_data' => $messages[0]
            ]);
        } else {
            echo json_encode($result);
        }
    } else {
        echo json_encode($result);
    }
}

/**
 * Add a member to a group
 */
function handleAddMember() {
    global $conn, $user_id;
    
    $groupId = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $newMemberId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    if (!$groupId || !$newMemberId) {
        echo json_encode(['success' => false, 'message' => 'Group ID and User ID are required']);
        return;
    }
    
    // Verify current user is in group
    if (!isUserInGroup($conn, $user_id, $groupId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Check if user is creator
    $sql = "SELECT creator_id FROM chat_groups WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if ($group['creator_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Only the group creator can add members']);
        return;
    }
    
    $success = addGroupMember($conn, $groupId, $newMemberId);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Member added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add member']);
    }
}

/**
 * Remove a member from a group
 */
function handleRemoveMember() {
    global $conn, $user_id;
    
    $groupId = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $memberToRemove = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    if (!$groupId || !$memberToRemove) {
        echo json_encode(['success' => false, 'message' => 'Group ID and User ID are required']);
        return;
    }
    
    // Verify current user is in group
    if (!isUserInGroup($conn, $user_id, $groupId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Allow user to remove themselves or creator can remove anyone
    $sql = "SELECT creator_id FROM chat_groups WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if ($group['creator_id'] != $user_id && $memberToRemove != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $success = removeGroupMember($conn, $groupId, $memberToRemove);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Member removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove member']);
    }
}

/**
 * Get members of a group
 */
function handleGetMembers() {
    global $conn, $user_id;
    
    $groupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
    
    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        return;
    }
    
    // Verify user is in group
    if (!isUserInGroup($conn, $user_id, $groupId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $members = getGroupMembers($conn, $groupId);
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
}

/**
 * Delete a group
 */
function handleDeleteGroup() {
    global $conn, $user_id;
    
    $groupId = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
    
    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID is required']);
        return;
    }
    
    $result = deleteGroup($conn, $groupId, $user_id);
    echo json_encode($result);
}

/**
 * Get unread group message count for current user
 */
function handleGetGroupMessageCount() {
    global $conn, $user_id;
    
    $count = getUnreadGroupMessageCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
}

/**
 * Search users by username
 */
function handleSearchUsers() {
    global $conn, $user_id;
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (strlen($query) < 1) {
        echo json_encode(['success' => false, 'message' => 'Search query too short']);
        return;
    }
    
    $users = searchUsers($conn, $query, $user_id);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

/**
 * Get all users (except current user)
 */
function handleGetAllUsers() {
    global $conn, $user_id;
    
    $users = getAllUsers($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

?>
