<?php
/**
 * Migration Runner
 * Run this once during setup to initialize all new tables and data
 * Access via: /migrate.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
include 'includes/db.php';
include 'includes/migrations.php';

// Get database name from connection
$dbname_result = $conn->query("SELECT DATABASE() as dbname");
$dbname = $dbname_result->fetch_assoc()['dbname'];

// Check if migrations have already been run
$has_run = false;
$check_new_tables = $conn->query("SHOW TABLES LIKE 'categories'");
if ($check_new_tables && $check_new_tables->num_rows > 0) {
    $has_run = true;
} else {
    // Fallback: some hosts restrict SHOW TABLES
    $check_new_tables = @$conn->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'categories'");
    $has_run = $check_new_tables && $check_new_tables->fetch_assoc()['count'] > 0;
}

// Protection: Allow if first-time setup OR localhost OR logged in admin
$is_first_setup = !$has_run;
$is_localhost = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === 'localhost' || $_SERVER['REMOTE_ADDR'] === '::1';
$is_admin = false;

if (isset($_SESSION['user_id'])) {
    // Check if is_admin column exists first
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if ($col_check && $col_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $is_admin = $user && $user['is_admin'];
        $stmt->close();
    }
}

// Allow access if: first setup OR localhost OR admin
if (!($is_first_setup || $is_localhost || $is_admin)) {
    die('<h2>❌ Error: Migrations can only be run during first setup (localhost) or while logged in as admin</h2>
    <p>To set up manually, run in MySQL:</p>
    <code>UPDATE users SET is_admin = TRUE WHERE email = "admin@admin.com";</code>');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUFFEIA Database Migrations</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🔧 MUFFEIA Database Migrations</h1>
    
    <div class="section">
        <h2>Migration Status</h2>
        <?php
        echo "<h3>Running Migrations...</h3>";
        flush();
        
        // Run migrations
        try {
            $migration_errors = runMigrations($conn);
            
            if (empty($migration_errors)) {
                echo "<p class='success'>✓ All database tables created successfully!</p>";
            } else {
                echo "<p class='warning'>⚠ Some migrations encountered issues:</p>";
                echo "<ul>";
                foreach ($migration_errors as $error) {
                    echo "<li class='error'>$error</li>";
                }
                echo "</ul>";
            }
        } catch (Throwable $e) {
            echo "<p class='error'>❌ Fatal error during migrations: " . htmlspecialchars($e->getMessage()) . " in " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
        }
        
        echo "<h3>Seeding Initial Data...</h3>";
        flush();
        
        // Seed initial data
        try {
            $seed_errors = seedInitialData($conn);
            
            if (empty($seed_errors)) {
                echo "<p class='success'>✓ All seed data inserted successfully!</p>";
            } else {
                echo "<p class='warning'>⚠ Some seed operations encountered issues:</p>";
                echo "<ul>";
                foreach ($seed_errors as $error) {
                    echo "<li class='error'>$error</li>";
                }
                echo "</ul>";
            }
        } catch (Throwable $e) {
            echo "<p class='error'>❌ Fatal error during seeding: " . htmlspecialchars($e->getMessage()) . " in " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>What Was Added</h2>
        <ul>
            <li><strong>Categories:</strong> 8 default categories (Personal, Career, Education, etc.)</li>
            <li><strong>Tags:</strong> Tagging system for problems</li>
            <li><strong>Reputation System:</strong> Point tracking for user actions</li>
            <li><strong>Badges:</strong> 6 achievement badges</li>
            <li><strong>Email Verification:</strong> Email verification tokens and flow</li>
            <li><strong>Admin Features:</strong> Admin flag, admin logs, moderation queue</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>Next Steps</h2>
        <ol>
            <li>Admin Dashboard will be available at <code>/pages/admin_dashboard.php</code></li>
            <li>Post creation will support categories/tags</li>
            <li>User profiles will show reputation and badges</li>
            <li>Email verification will be enforced on signup</li>
        </ol>
        <p><a href="index.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">← Back to Dashboard</a></p>
    </div>
    
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>
