<?php
/**
 * Group Room Database Initialization
 * This file creates the necessary tables for the group chat feature
 * Run once to initialize the database
 */

session_start();
include 'db.php';

// Create chat_groups table
$create_groups_sql = "CREATE TABLE IF NOT EXISTS chat_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    creator_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (creator_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_groups_sql)) {
    error_log("✓ chat_groups table created/verified successfully");
} else {
    error_log("✗ Error creating chat_groups table: " . $conn->error);
}

// Create chat_group_members table
$create_members_sql = "CREATE TABLE IF NOT EXISTS chat_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_user (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (group_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_members_sql)) {
    error_log("✓ chat_group_members table created/verified successfully");
} else {
    error_log("✗ Error creating chat_group_members table: " . $conn->error);
}

// Create chat_group_messages table
$create_messages_sql = "CREATE TABLE IF NOT EXISTS chat_group_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text LONGTEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (group_id),
    INDEX (sender_id),
    INDEX (sent_at),
    INDEX (group_id_sent_at) UNIQUE KEY (group_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_messages_sql)) {
    error_log("✓ chat_group_messages table created/verified successfully");
} else {
    error_log("✗ Error creating chat_group_messages table: " . $conn->error);
}

echo json_encode(['success' => true, 'message' => 'Group room tables initialized successfully']);
?>
