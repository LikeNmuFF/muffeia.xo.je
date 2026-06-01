<?php
/**
 * Setup Admin Account
 * Run this to create or promote an admin user
 * Access via: /setup_admin.php
 */

session_start();
include 'includes/db.php';

$message = '';
$error = '';
$admin_created = false;

// Ensure all new columns exist (in case migrations haven't run yet)
$columns_to_check = [
    'is_admin' => "ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE",
    'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE",
    'reputation_score' => "ALTER TABLE users ADD COLUMN reputation_score INT DEFAULT 0",
    'is_banned' => "ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE",
];

foreach ($columns_to_check as $col => $alter_sql) {
    $result = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($result->num_rows === 0) {
        $conn->query($alter_sql);
    }
}

// Fix AUTO_INCREMENT on users.id if missing
$autoinc_check = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'id' AND Extra LIKE '%auto_increment%'");
if ($autoinc_check->num_rows === 0) {
    // Get max id to set auto_increment properly
    $max_id_result = $conn->query("SELECT MAX(id) as max_id FROM users");
    $max_id = $max_id_result->fetch_assoc()['max_id'] ?? 0;
    $next_id = $max_id + 1;
    $conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    // If there are existing rows, fix them
    $conn->query("ALTER TABLE users AUTO_INCREMENT = $next_id");
}

// Check if any admin exists
$admin_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = TRUE");
$admin_exists = $admin_check->fetch_assoc()['count'] > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'promote_admin') {
        $email = $_POST['email'];
        
        // Find user by email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Promote to admin
            $update_stmt = $conn->prepare("UPDATE users SET is_admin = TRUE WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            if ($update_stmt->execute()) {
                $message = "✓ User '$email' has been promoted to admin!";
                $admin_created = true;
            } else {
                $error = "Error updating user: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error = "Error updating user.";
            error_log("Admin promotion error: " . $conn->error);
        }
    } 
    elseif ($action === 'create_admin') {
        $admin_email = $_POST['admin_email'];
        $admin_pass = $_POST['admin_password'];
        $admin_user = $_POST['admin_username'];
        
        // Validate
        if (strlen($admin_pass) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            // Check if email exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $admin_email);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Email already exists.";
            } else {
                // Create admin user
                $pass_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, is_admin, email_verified) VALUES (?, ?, ?, TRUE, TRUE)");
                $insert_stmt->bind_param("sss", $admin_user, $admin_email, $pass_hash);
                if ($insert_stmt->execute()) {
                    $message = "✓ Admin account created successfully!<br>
                    Email: " . htmlspecialchars($admin_email) . "<br>
                    Username: " . htmlspecialchars($admin_user);
                    $admin_created = true;
                } else {
                    $error = "Error creating admin.";
                    error_log("Admin creation error: " . $conn->error);
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
}

// Get list of existing users
$users_result = $conn->query("SELECT id, username, email, is_admin FROM users ORDER BY created_at DESC LIMIT 20");
$users = $users_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin - MUFFEIA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: #6366f1;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #1e293b;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        button {
            width: 100%;
            padding: 12px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            background: #4f46e5;
        }
        .admin-list {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        .admin-list h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #475569;
        }
        .user-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .user-item:last-child {
            border-bottom: none;
        }
        .admin-badge {
            background: #dbeafe;
            color: #0c4a6e;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 30px 0;
        }
        .help-text {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 12px;
            margin-top: 15px;
            font-size: 13px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Setup Admin Account</h1>
            <p>Configure administrator access for MUFFEIA</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!$admin_exists): ?>
                <!-- Create New Admin -->
                <div class="section">
                    <h2>🆕 Create New Admin Account</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_admin">
                        <div class="form-group">
                            <label>Admin Username</label>
                            <input type="text" name="admin_username" required minlength="3" placeholder="e.g., admin">
                        </div>
                        <div class="form-group">
                            <label>Admin Email</label>
                            <input type="email" name="admin_email" required placeholder="e.g., admin@example.com">
                        </div>
                        <div class="form-group">
                            <label>Admin Password</label>
                            <input type="password" name="admin_password" required minlength="8" placeholder="Min 8 characters">
                        </div>
                        <button type="submit">Create Admin Account</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="section">
                    <div class="message success">✓ Admin account(s) already exist. You can now use migration tools.</div>
                </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <!-- Promote Existing User -->
            <div class="section">
                <h2>👥 Promote Existing User to Admin</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="promote_admin">
                    <div class="form-group">
                        <label>User Email to Promote</label>
                        <select name="email" required>
                            <option value="">-- Select a user --</option>
                            <?php foreach ($users as $user): ?>
                                <?php if (!$user['is_admin']): ?>
                                    <option value="<?php echo htmlspecialchars($user['email']); ?>">
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Promote to Admin</button>
                </form>
            </div>
            
            <!-- Existing Users List -->
            <?php if (!empty($users)): ?>
                <div class="admin-list">
                    <h3>📋 Existing Users (<?php echo count($users); ?>)</h3>
                    <?php foreach ($users as $user): ?>
                        <div class="user-item">
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <?php echo htmlspecialchars($user['email']); ?>
                            <?php if ($user['is_admin']): ?>
                                <span class="admin-badge">ADMIN</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="help-text">
                <strong>💡 Next Steps:</strong><br>
                1. Create or promote an admin account above<br>
                2. Login with the admin account<br>
                3. Visit <code>/migrate.php</code> to run database migrations
            </div>
        </div>
    </div>
</body>
</html>
