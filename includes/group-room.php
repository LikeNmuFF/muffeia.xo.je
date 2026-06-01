<?php
/**
 * Group Chat Helper Functions
 * 
 * Usage: include 'includes/group-room.php';
 * 
 * Core functions for managing private group chats:
 * - createGroup($name, $userId)
 * - getUserGroups($userId)
 * - addGroupMember($groupId, $userId)
 * - removeGroupMember($groupId, $userId)
 * - sendGroupMessage($groupId, $senderId, $messageText)
 * - getGroupMessages($groupId, $limit, $offset)
 * - getGroupMembers($groupId)
 * - isUserInGroup($userId, $groupId)
 * - deleteGroup($groupId, $userId)
 */

// Note: Tables are created via migrate.php during initial setup to avoid InfinityFree 403 errors.

/**
 * Create a new group chat
 * @param string $name - Group name
 * @param int $userId - Creator's user ID
 * @param array $memberIds - Array of user IDs to add as members
 * @return array - ['success' => bool, 'group_id' => int, 'message' => string]
 */
if (!function_exists('createGroup')) {
    function createGroup($conn, $name, $userId, $memberIds = []) {
        // Validate input
        $name = trim($name);
        if (empty($name)) {
            return ['success' => false, 'message' => 'Group name is required'];
        }

        if (strlen($name) > 255) {
            return ['success' => false, 'message' => 'Group name is too long (max 255 characters)'];
        }

        // Create the group
        $sql = "INSERT INTO chat_groups (name, creator_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing group creation: " . $conn->error);
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param("si", $name, $userId);
        if (!$stmt->execute()) {
            error_log("Error executing group creation: " . $stmt->error);
            return ['success' => false, 'message' => 'Failed to create group'];
        }

        $groupId = $stmt->insert_id;
        $stmt->close();

        // Add creator as a member
        addGroupMember($conn, $groupId, $userId);

        // Add other members
        if (!empty($memberIds)) {
            foreach ($memberIds as $memberId) {
                if (intval($memberId) !== $userId) { // Don't add creator twice
                    addGroupMember($conn, $groupId, intval($memberId));
                }
            }
        }

        return [
            'success' => true,
            'group_id' => $groupId,
            'message' => 'Group created successfully'
        ];
    }
}

/**
 * Get all groups for a user
 * @param int $userId - User ID
 * @return array - Array of groups with member count
 */
if (!function_exists('getUserGroups')) {
    function getUserGroups($conn, $userId) {
        $sql = "SELECT g.id, g.name, g.creator_id, g.created_at,
                COUNT(m.id) as member_count,
                (SELECT message_text FROM chat_group_messages 
                 WHERE group_id = g.id 
                 ORDER BY sent_at DESC LIMIT 1) as last_message,
                (SELECT sent_at FROM chat_group_messages 
                 WHERE group_id = g.id 
                 ORDER BY sent_at DESC LIMIT 1) as last_message_time
                FROM chat_groups g
                JOIN chat_group_members cgm ON g.id = cgm.group_id
                LEFT JOIN chat_group_members m ON g.id = m.group_id
                WHERE cgm.user_id = ? AND g.is_active = 1
                GROUP BY g.id
                ORDER BY last_message_time DESC, g.created_at DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing getUserGroups: " . $conn->error);
            return [];
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $groups = [];

        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }

        $stmt->close();
        return $groups;
    }
}

/**
 * Add a member to a group
 * @param int $groupId - Group ID
 * @param int $userId - User ID to add
 * @return bool - Success status
 */
if (!function_exists('addGroupMember')) {
    function addGroupMember($conn, $groupId, $userId) {
        // Check if already a member
        $check_sql = "SELECT id FROM chat_group_members WHERE group_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) return false;

        $check_stmt->bind_param("ii", $groupId, $userId);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            return true; // Already a member
        }
        $check_stmt->close();

        // Add member
        $sql = "INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("ii", $groupId, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

/**
 * Remove a member from a group
 * @param int $groupId - Group ID
 * @param int $userId - User ID to remove
 * @return bool - Success status
 */
if (!function_exists('removeGroupMember')) {
    function removeGroupMember($conn, $groupId, $userId) {
        $sql = "DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("ii", $groupId, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

/**
 * Send a message to a group
 * @param int $groupId - Group ID
 * @param int $senderId - Sender user ID
 * @param string $messageText - Message content
 * @return array - ['success' => bool, 'message_id' => int/null]
 */
if (!function_exists('sendGroupMessage')) {
    function sendGroupMessage($conn, $groupId, $senderId, $messageText) {
        // Verify user is in group
        if (!isUserInGroup($conn, $senderId, $groupId)) {
            return ['success' => false, 'message' => 'User is not a member of this group'];
        }

        // Validate message
        $messageText = trim($messageText);
        if (empty($messageText)) {
            return ['success' => false, 'message' => 'Message cannot be empty'];
        }

        // Apply moderation if available
        if (function_exists('moderate_text') && function_exists('mask_text')) {
            $mod_check = moderate_text($messageText);
            if (!empty($mod_check['flagged'])) {
                $messageText = mask_text($messageText);
            }
        }

        // Sanitize message
        $messageText = htmlspecialchars($messageText);

        // Insert message
        $sql = "INSERT INTO chat_group_messages (group_id, sender_id, message_text) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing sendGroupMessage: " . $conn->error);
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param("iis", $groupId, $senderId, $messageText);
        if (!$stmt->execute()) {
            error_log("Error executing sendGroupMessage: " . $stmt->error);
            return ['success' => false, 'message' => 'Failed to send message'];
        }

        $messageId = $stmt->insert_id;
        $stmt->close();

        return ['success' => true, 'message_id' => $messageId];
    }
}

/**
 * Get messages for a group
 * @param int $groupId - Group ID
 * @param int $limit - Number of messages to fetch
 * @param int $offset - Offset for pagination
 * @return array - Array of messages
 */
if (!function_exists('getGroupMessages')) {
    function getGroupMessages($conn, $groupId, $limit = 50, $offset = 0) {
        $sql = "SELECT m.id, m.group_id, m.sender_id, m.message_text, m.sent_at, m.is_read,
                u.username, u.profile_pic
                FROM chat_group_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.group_id = ?
                ORDER BY m.sent_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing getGroupMessages: " . $conn->error);
            return [];
        }

        $stmt->bind_param("iii", $groupId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        $stmt->close();
        return array_reverse($messages); // Return in chronological order
    }
}

/**
 * Get members of a group
 * @param int $groupId - Group ID
 * @return array - Array of members
 */
if (!function_exists('getGroupMembers')) {
    function getGroupMembers($conn, $groupId) {
        $sql = "SELECT u.id as user_id, u.username, u.profile_pic, cgm.joined_at,
                CASE WHEN g.creator_id = u.id THEN 1 ELSE 0 END as is_creator
                FROM chat_group_members cgm
                JOIN users u ON cgm.user_id = u.id
                JOIN chat_groups g ON cgm.group_id = g.id
                WHERE cgm.group_id = ?
                ORDER BY cgm.joined_at ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Error preparing getGroupMembers: " . $conn->error);
            return [];
        }

        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];

        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }

        $stmt->close();
        return $members;
    }
}

/**
 * Search users by username (excludes current user)
 * @param int $conn - Database connection
 * @param string $query - Search query
 * @param int $excludeUserId - User ID to exclude
 * @param int $limit - Max results
 * @return array - Array of users
 */
if (!function_exists('searchUsers')) {
    function searchUsers($conn, $query, $excludeUserId, $limit = 20) {
        $sql = "SELECT id, username, profile_pic FROM users 
                WHERE username LIKE ? AND id != ?
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $searchTerm = '%' . $query . '%';
        $stmt->bind_param("sii", $searchTerm, $excludeUserId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        $stmt->close();
        return $users;
    }
}

/**
 * Get all users (except current user)
 * @param int $conn - Database connection
 * @param int $excludeUserId - User ID to exclude
 * @return array - Array of users
 */
if (!function_exists('getAllUsers')) {
    function getAllUsers($conn, $excludeUserId) {
        $sql = "SELECT id, username, profile_pic 
                FROM users 
                WHERE id != ?
                ORDER BY username ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param("i", $excludeUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        $stmt->close();
        return $users;
    }
}

/**
 * Check if user is a member of a group
 * @param int $userId - User ID
 * @param int $groupId - Group ID
 * @return bool - True if user is member
 */
if (!function_exists('isUserInGroup')) {
    function isUserInGroup($conn, $userId, $groupId) {
        $sql = "SELECT id FROM chat_group_members WHERE group_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("ii", $groupId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

/**
 * Get unread message count for user
 * @param int $userId - User ID
 * @return int - Total unread messages across all groups
 */
if (!function_exists('getUnreadGroupMessageCount')) {
    function getUnreadGroupMessageCount($conn, $userId) {
        $sql = "SELECT COUNT(*) as unread_count
                FROM chat_group_messages m
                JOIN chat_group_members cgm ON m.group_id = cgm.group_id
                WHERE cgm.user_id = ? AND m.sender_id != ? AND m.is_read = 0";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;

        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['unread_count'] ?? 0;
    }
}

/**
 * Mark messages as read
 * @param int $groupId - Group ID
 * @param int $userId - User ID
 * @return bool - Success status
 */
if (!function_exists('markGroupMessagesAsRead')) {
    function markGroupMessagesAsRead($conn, $groupId, $userId) {
        $sql = "UPDATE chat_group_messages 
                SET is_read = 1 
                WHERE group_id = ? AND sender_id != ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param("ii", $groupId, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

/**
 * Delete a group (only creator can delete)
 * @param int $groupId - Group ID
 * @param int $userId - User ID (must be creator)
 * @return array - ['success' => bool, 'message' => string]
 */
if (!function_exists('deleteGroup')) {
    function deleteGroup($conn, $groupId, $userId) {
        // Verify user is creator
        $sql = "SELECT creator_id FROM chat_groups WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        $stmt->close();

        if (!$group) {
            return ['success' => false, 'message' => 'Group not found'];
        }

        if ($group['creator_id'] != $userId) {
            return ['success' => false, 'message' => 'Only the group creator can delete the group'];
        }

        // Delete group (cascade will delete members and messages)
        $delete_sql = "DELETE FROM chat_groups WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        if (!$delete_stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $delete_stmt->bind_param("i", $groupId);
        if (!$delete_stmt->execute()) {
            error_log("Error deleting group: " . $delete_stmt->error);
            return ['success' => false, 'message' => 'Failed to delete group'];
        }
        $delete_stmt->close();

        return ['success' => true, 'message' => 'Group deleted successfully'];
    }
}

?>
