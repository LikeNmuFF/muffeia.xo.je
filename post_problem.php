<?php
session_start();
// Set timezone for consistency (Asia/Manila)


include 'includes/db.php';
include 'includes/moderation.php';

// Check if the user is logged in first
if (!isset($_SESSION['user_id'])) {
    error_log("Post Error: User tried to post without being logged in.");
    header("Location: auth/login.php");
    exit();
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ----------------------------------------------------------------
    // ❌ REMOVE: The DB timezone is already set in includes/db.php
    // $conn->query("SET time_zone = '+08:00'"); 
    // ----------------------------------------------------------------

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $user_id = $_SESSION['user_id']; 
    
    if (function_exists('moderate_text')) {
        foreach (['title' => $title, 'description' => $description] as $field => $value) {
            $mod = moderate_text($value);
            if (!empty($mod['flagged'])) {
                $_SESSION['post_error'] = ucfirst($field) . " contains language we don't allow. Please revise and try again.";
                header("Location: index.php");
                exit();
            }
        }
    }

    // Get current Manila time (optional, for debugging only)
    $current_time = date('Y-m-d H:i:s');

    // 1. Prepare the SQL statement - Use NOW() to store the current UTC time.
    // The database is configured to use UTC for storage.
    $sql = "INSERT INTO problems (title, description, anonymous, user_id, created_at, updated_at) 
             VALUES (?, ?, ?, ?, NOW(), NOW())"; // *** CORRECTED: Use NOW() ***
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $error_message = "Post Error: SQL Prepare Failed: " . $conn->error;
        error_log($error_message);
        $_SESSION['post_error'] = "Database error: Could not prepare post. Check logs.";
        header("Location: index.php");
        exit();
    }
    
    $stmt->bind_param("ssii", $title, $description, $anonymous, $user_id);
    
    // 2. Execute the statement and check for failure
    if ($stmt->execute()) {
        // Post successful
        $problem_id = $stmt->insert_id;

        // Get the actual timestamp that was stored (for debug)
        // This SELECT query will return a Manila time due to the SET time_zone fix in db.php
        $check_sql = "SELECT created_at FROM problems WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $problem_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $stored_time = $check_result->fetch_assoc()['created_at'];
        $check_stmt->close();
        
        error_log("Post Debug - PHP Time (Manila): $current_time, Stored Time (DB Session Manila): $stored_time");

        // Notification insertion - use NOW() here as well!
        $target_url = "pages/view_problem?problem_id=" . $problem_id;
        $notification_sql = "INSERT INTO notifications (user_id, message, target_url, created_at) 
                             VALUES (?, ?, ?, NOW())"; // *** CORRECTED: Use NOW() ***

        $notification_stmt = $conn->prepare($notification_sql);
        if ($notification_stmt) {
            $notification_message = "Your problem has been posted successfully!";
            $notification_stmt->bind_param("iss", $user_id, $notification_message, $target_url);
            $notification_stmt->execute();
            $notification_stmt->close();
        }
        
        $stmt->close();

        $_SESSION['post_success'] = "Your problem has been published successfully!";
        header("Location: index.php");
        exit(); 
        
    } else {
        $error_message = "Post Error: SQL Execute Failed: " . $stmt->error;
        error_log($error_message);
        $_SESSION['post_error'] = "Failed to save your post.";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}