<?php
session_start();
include '../includes/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['is_admin']) {
    die("Access Denied: Admin privileges required.");
}
$stmt->close();

// Handle actions
$action_message = '';
$action_error = '';

// CSRF validation helper
function validateAdminCsrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security token validation failed.");
    }
}

// Delete user action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    validateAdminCsrf();
    $target_user_id = intval($_POST['user_id']);
    
    // Prevent self-deletion
    if ($target_user_id === $_SESSION['user_id']) {
        $action_error = "Cannot delete your own admin account!";
    } else {
        $conn->begin_transaction();
        try {
            // Delete associated data
            $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR recipient_id = ?");
            $stmt->bind_param("ii", $target_user_id, $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM post_likes WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM post_shares WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM solutions WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM problems WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
            
            // Log action
            $action_type = 'user_delete';
            $description = "Deleted user ID $target_user_id";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address) VALUES (?, ?, 'user', ?, ?, ?)");
            $log_stmt->bind_param("isiss", $_SESSION['user_id'], $action_type, $target_user_id, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
            
            $conn->commit();
            $action_message = "User deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $action_error = "Error deleting user.";
            error_log("Admin user delete error: " . $e->getMessage());
        }
    }
}

// Ban user action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'ban_user') {
    validateAdminCsrf();
    $target_user_id = intval($_POST['user_id']);
    
    if ($target_user_id === $_SESSION['user_id']) {
        $action_error = "Cannot ban your own admin account!";
    } else {
        $is_banned = 1;
        $stmt = $conn->prepare("UPDATE users SET is_banned = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_banned, $target_user_id);
        if ($stmt->execute()) {
            $action_message = "User banned successfully!";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address) VALUES (?, 'user_ban', 'user', ?, 'Banned user', ?)");
            $log_stmt->bind_param("iii", $_SESSION['user_id'], $target_user_id, $ip_address);
            $log_stmt->execute();
        } else {
            $action_error = "Error performing action. Please try again.";
            error_log("Admin ban error: " . $conn->error);
        }
        $stmt->close();
    }
}

// Delete post action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_post') {
    validateAdminCsrf();
    $post_id = intval($_POST['post_id']);
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM post_likes WHERE problem_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM post_shares WHERE problem_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM solutions WHERE problem_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM problems WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
        
        // Log action
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address) VALUES (?, 'post_delete', 'post', ?, 'Deleted post', ?)");
        $log_stmt->bind_param("iii", $_SESSION['user_id'], $post_id, $ip_address);
        $log_stmt->execute();
        
        $conn->commit();
        $action_message = "Post deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $action_error = "Error deleting post.";
        error_log("Admin post delete error: " . $e->getMessage());
    }
}

// Approve/Reject moderation queue items
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve_mod', 'reject_mod'])) {
    validateAdminCsrf();
    $mod_id = intval($_POST['mod_id']);
    $status = $_POST['action'] === 'approve_mod' ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE moderation_queue SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $mod_id);
    if ($stmt->execute()) {
        $action_message = "Moderation item updated!";
    } else {
        $action_error = "Error updating moderation.";
        error_log("Admin moderation error: " . $conn->error);
    }
    $stmt->close();
}

// Get dashboard statistics
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM problems");
$stats['total_posts'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM solutions");
$stats['total_solutions'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM moderation_queue WHERE status = 'pending'");
$stats['pending_moderation'] = $result->fetch_assoc()['total'];

// Get recent users
$recent_users_result = $conn->query("SELECT id, username, email, created_at, is_admin FROM users ORDER BY created_at DESC");

// Get flagged content
$flagged_content = $conn->query("SELECT * FROM moderation_queue WHERE status = 'pending' LIMIT 20");

// Get recent admin logs
$admin_logs = $conn->query("SELECT al.*, u.username FROM admin_logs al JOIN users u ON al.admin_id = u.id ORDER BY al.created_at DESC LIMIT 15");

// Get reported posts
$reported_posts = $conn->query("
    SELECT mq.*, p.title, p.description, u.username 
    FROM moderation_queue mq 
    LEFT JOIN problems p ON mq.content_type = 'post' AND mq.content_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE mq.content_type = 'post' AND mq.status = 'pending'
    LIMIT 10
");

// Get reported users
$reported_users = $conn->query("
    SELECT mq.*, u.username, u.email, u.created_at
    FROM moderation_queue mq 
    LEFT JOIN users u ON mq.content_type = 'user' AND mq.content_id = u.id
    WHERE mq.content_type = 'user' AND mq.status = 'pending'
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MUFFEIA</title>
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <style>
        :root {
            --primary: #6366f1;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }
        
        .admin-header h1 {
            font-size: 28px;
            font-weight: 700;
        }
        
        .admin-nav {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th {
            background: var(--bg);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        tr:hover {
            background: var(--bg);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-admin {
            background: #dbeafe;
            color: #0c4a6e;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>⚙️ Admin Dashboard</h1>
            <div class="admin-nav">
                <a href="../index.php" class="btn btn-primary">← Back to Feed</a>
            </div>
        </div>
        
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($action_message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($action_error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($action_error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_posts']; ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_solutions']; ?></div>
                <div class="stat-label">Total Solutions</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?php echo $stats['pending_moderation']; ?></div>
                <div class="stat-label">Pending Moderation</div>
            </div>
        </div>
        
        <!-- Recent Users Section -->
        <div class="section">
            <div class="section-title">👥 All Users (<?php echo $stats['total_users']; ?>)</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $recent_users_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span>User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Ban this user?');">
                                        <input type="hidden" name="action" value="ban_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-warning" style="font-size: 12px;">Ban</button>
                                    </form>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 12px;">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Flagged Posts Section -->
        <div class="section">
            <div class="section-title">🚩 Flagged Posts</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Post Title</th>
                            <th>Author</th>
                            <th>Reason</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($reported_posts) $reported_posts->data_seek(0);
                        while ($post = ($reported_posts ? $reported_posts->fetch_assoc() : null)): 
                        ?>
                        <?php if ($post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($post['title'] ?? 'N/A', 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($post['reason'] ?? 'No reason'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="approve_mod">
                                        <input type="hidden" name="mod_id" value="<?php echo $post['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-success" style="font-size: 12px;">Approve</button>
                                    </form>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="reject_mod">
                                        <input type="hidden" name="mod_id" value="<?php echo $post['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 12px;">Reject</button>
                                    </form>
                                    <?php if ($post['content_id']): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this post?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?php echo $post['content_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 12px;">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Admin Activity Log -->
        <div class="section">
            <div class="section-title">📋 Recent Admin Actions</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Description</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = ($admin_logs ? $admin_logs->fetch_assoc() : null)): ?>
                        <?php if ($log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><span class="badge badge-pending"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['target_type'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                            <td><?php echo date('M d H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>
