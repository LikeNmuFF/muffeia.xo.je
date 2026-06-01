<?php
/**
 * Database Migrations for MUFFEIA v3
 * Creates tables for:
 * - Post categories/tags
 * - User reputation and badges
 * - Email verification
 * - Admin features
 */

function runMigrations($conn) {
    $errors = [];
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // 1. Create categories table
    $sql_categories = "CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        icon VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_categories)) {
        $errors[] = "Categories table: " . $conn->error;
    }
    
    // 2. Create tags table
    $sql_tags = "CREATE TABLE IF NOT EXISTS tags (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        slug VARCHAR(100) NOT NULL UNIQUE,
        usage_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_tags)) {
        $errors[] = "Tags table: " . $conn->error;
    }
    
    // 3. Create problem_tags junction table
    $sql_problem_tags = "CREATE TABLE IF NOT EXISTS problem_tags (
        id INT PRIMARY KEY AUTO_INCREMENT,
        problem_id INT NOT NULL,
        tag_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_problem_tag (problem_id, tag_id)
    )";
    if (!$conn->query($sql_problem_tags)) {
        $errors[] = "Problem tags table: " . $conn->error;
    }
    
    // 4. Add category_id to problems table if not exists
    $check_category = $conn->query("SHOW COLUMNS FROM problems LIKE 'category_id'");
    if ($check_category && $check_category->num_rows === 0) {
        $sql_alter_problems = "ALTER TABLE problems ADD COLUMN category_id INT";
        if (!$conn->query($sql_alter_problems)) {
            $errors[] = "Alter problems table: " . $conn->error;
        }
    }
    
    // 5. Create reputation_points table
    $sql_reputation = "CREATE TABLE IF NOT EXISTS reputation_points (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        points INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_reputation)) {
        $errors[] = "Reputation points table: " . $conn->error;
    }
    
    // 6. Create user_badges table
    $sql_badges = "CREATE TABLE IF NOT EXISTS user_badges (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        badge_id INT NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_badge (user_id, badge_id)
    )";
    if (!$conn->query($sql_badges)) {
        $errors[] = "User badges table: " . $conn->error;
    }
    
    // 7. Create badges table (reference)
    $sql_badge_definitions = "CREATE TABLE IF NOT EXISTS badges (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50),
        criteria TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_badge_definitions)) {
        $errors[] = "Badge definitions table: " . $conn->error;
    }
    
    // 8. Add individual user columns if not exists
    $user_columns = [
        'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE",
        'verification_token' => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) UNIQUE",
        'verification_token_expires_at' => "ALTER TABLE users ADD COLUMN verification_token_expires_at TIMESTAMP NULL",
        'reputation_score' => "ALTER TABLE users ADD COLUMN reputation_score INT DEFAULT 0",
        'is_banned' => "ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE",
    ];
    
    foreach ($user_columns as $col_name => $alter_sql) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col_name'");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query($alter_sql)) {
                $errors[] = "Add $col_name column: " . $conn->error;
            }
        }
    }
    
    // 9. Create email_verifications table
    $sql_email_verifications = "CREATE TABLE IF NOT EXISTS email_verifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_email_verifications)) {
        $errors[] = "Email verifications table: " . $conn->error;
    }
    
    // 10. Add is_admin column to users if not exists
    $check_admin = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if ($check_admin && $check_admin->num_rows === 0) {
        $sql_alter_admin = "ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE";
        if (!$conn->query($sql_alter_admin)) {
            $errors[] = "Alter users admin column: " . $conn->error;
        }
    }
    
    // 11. Create admin_logs table
    $sql_admin_logs = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50),
        target_id INT,
        description TEXT,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_admin_logs)) {
        $errors[] = "Admin logs table: " . $conn->error;
    }
    
    // 12. Create moderation_queue table
    $sql_moderation = "CREATE TABLE IF NOT EXISTS moderation_queue (
        id INT PRIMARY KEY AUTO_INCREMENT,
        content_type VARCHAR(50) NOT NULL,
        content_id INT NOT NULL,
        reason VARCHAR(255),
        reported_by INT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        reviewed_by INT,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql_moderation)) {
        $errors[] = "Moderation queue table: " . $conn->error;
    }
    
    // 13. Create group chat tables
    $sql_chat_groups = "CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        creator_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX (creator_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql_chat_groups)) {
        $errors[] = "Chat groups table: " . $conn->error;
    }
    
    $sql_chat_members = "CREATE TABLE IF NOT EXISTS chat_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_group_user (group_id, user_id),
        INDEX (group_id),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql_chat_members)) {
        $errors[] = "Chat group members table: " . $conn->error;
    }
    
    $sql_chat_messages = "CREATE TABLE IF NOT EXISTS chat_group_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        sender_id INT NOT NULL,
        message_text TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        INDEX (group_id),
        INDEX (sender_id),
        INDEX (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql_chat_messages)) {
        $errors[] = "Chat group messages table: " . $conn->error;
    }

    // 14. Create problem_views table
    $sql_problem_views = "CREATE TABLE IF NOT EXISTS problem_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        problem_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at DATETIME NOT NULL,
        UNIQUE KEY uniq_problem_user (problem_id, user_id),
        INDEX (user_id),
        INDEX (problem_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql_problem_views)) {
        $errors[] = "Problem views table: " . $conn->error;
    }

    // 15. Create rate_limits table
    $sql_rate_limits = "CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        request_count INT DEFAULT 1,
        window_start INT NOT NULL,
        is_blocked TINYINT(1) DEFAULT 0,
        blocked_until INT DEFAULT 0,
        INDEX idx_ip (ip_address),
        INDEX idx_window (window_start),
        INDEX idx_blocked (is_blocked)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql_rate_limits)) {
        $errors[] = "Rate limits table: " . $conn->error;
    }

    // 16. Create rate_limit_404 table
    $sql_rate_404 = "CREATE TABLE IF NOT EXISTS rate_limit_404 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        request_path TEXT,
        hit_count INT DEFAULT 1,
        first_hit INT NOT NULL,
        last_hit INT NOT NULL,
        INDEX idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql_rate_404)) {
        $errors[] = "Rate limit 404 table: " . $conn->error;
    }

    // 17. Create chatbot_responses table
    $sql_chatbot = "CREATE TABLE IF NOT EXISTS chatbot_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50),
        pattern TEXT,
        response TEXT,
        usage_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql_chatbot)) {
        $errors[] = "Chatbot responses table: " . $conn->error;
    }

    // 18. Create password_resets table
    $sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(100) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used TINYINT(1) NOT NULL DEFAULT 0,
        INDEX (email),
        INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql_password_resets)) {
        $errors[] = "Password resets table: " . $conn->error;
    }
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    return $errors;
}

// Helper function to seed initial data
function seedInitialData($conn) {
    $errors = [];
    
    // Seed categories (skip if table doesn't exist)
    $check_cat = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($check_cat && $check_cat->num_rows > 0) {
        $categories = [
            ['Personal', 'personal', 'Personal life and relationships', '❤️'],
            ['Career', 'career', 'Job and career advice', '💼'],
            ['Education', 'education', 'Academic and learning', '📚'],
            ['Health', 'health', 'Health and wellness', '🏥'],
            ['Technology', 'technology', 'Tech and programming', '💻'],
            ['Finance', 'finance', 'Money and finance', '💰'],
            ['Family', 'family', 'Family matters', '👨‍👩‍👧‍👦'],
            ['Other', 'other', 'Other topics', '🔀'],
        ];
        
        foreach ($categories as $cat) {
            $stmt = $conn->prepare("INSERT IGNORE INTO categories (name, slug, description, icon) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $cat[0], $cat[1], $cat[2], $cat[3]);
                if (!$stmt->execute()) {
                    $errors[] = "Seed categories: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    // Seed badges (skip if table doesn't exist)
    $check_badges = $conn->query("SHOW TABLES LIKE 'badges'");
    if ($check_badges && $check_badges->num_rows > 0) {
        $badges = [
            ['First Problem', 'Posted your first problem', '🎯', 'Posted a problem'],
            ['Problem Solver', 'Provided 5 solutions', '🧩', 'Posted 5 solutions'],
            ['Helper', 'Received 10 likes on solutions', '🤝', 'Got 10 likes on solutions'],
            ['Popular', 'Got 50 likes on a problem', '⭐', 'Got 50 likes on single problem'],
            ['Contributor', 'Reputation score of 100+', '🏆', 'Reputation >= 100'],
            ['Super Helper', 'Received 50 likes on solutions', '🌟', 'Got 50 likes on solutions'],
        ];
        
        foreach ($badges as $badge) {
            $stmt = $conn->prepare("INSERT IGNORE INTO badges (name, description, icon, criteria) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $badge[0], $badge[1], $badge[2], $badge[3]);
                if (!$stmt->execute()) {
                    $errors[] = "Seed badges: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    // 16. Add is_accepted column to solutions table if missing
    $check = $conn->query("SHOW COLUMNS FROM solutions LIKE 'is_accepted'");
    if ($check && $check->num_rows === 0) {
        if (!$conn->query("ALTER TABLE solutions ADD COLUMN is_accepted TINYINT(1) DEFAULT 0 AFTER solution_text")) {
            $errors[] = "Add is_accepted column: " . $conn->error;
        }
    }

    // 17. Add details column to moderation_queue if missing
    $check = $conn->query("SHOW COLUMNS FROM moderation_queue LIKE 'details'");
    if ($check && $check->num_rows === 0) {
        if (!$conn->query("ALTER TABLE moderation_queue ADD COLUMN details TEXT AFTER reason")) {
            $errors[] = "Add details column: " . $conn->error;
        }
    }
    
    return $errors;
}
?>
