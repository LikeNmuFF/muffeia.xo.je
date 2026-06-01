<?php
session_start();

include 'includes/db.php';
include 'includes/moderation.php';
include 'includes/categories_tags.php';

if (!isset($_SESSION['user_id'])) {
    error_log("Post Error: User tried to post without being logged in.");
    header("Location: auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_feature_available = categoryFeatureAvailable($conn);

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['post_error'] = 'Security token validation failed. Please try again.';
        header("Location: index.php");
        exit();
    }

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $user_id = $_SESSION['user_id']; 

    if ($category_feature_available) {
        $detected_tags = extractTagsFromText($description);
        $detected_category = extractCategoryFromText($description);

        $category_id = null;
        if ($detected_category) {
            $cat_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            $cat_stmt->bind_param("s", $detected_category);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result()->fetch_assoc();
            $cat_stmt->close();
            if ($cat_result) {
                $category_id = $cat_result['id'];
            }
        }
    } else {
        $category_id = null;
        $detected_tags = [];
    }
    
    $title_mod = moderate_text($title);
    if (!empty($title_mod['flagged'])) {
        $title = mask_text($title);
    }
    $desc_mod = moderate_text($description);
    if (!empty($desc_mod['flagged'])) {
        $description = mask_text($description);
    }

    $current_time = date('Y-m-d H:i:s');

    if ($category_feature_available) {
        $sql = "INSERT INTO problems (title, description, anonymous, user_id, category_id, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    } else {
        $sql = "INSERT INTO problems (title, description, anonymous, user_id, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, NOW(), NOW())";
    }
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $error_message = "Post Error: Could not prepare post.";
        error_log("SQL Prepare Failed: " . $conn->error);
        $_SESSION['post_error'] = "Database error: Could not prepare post. Check logs.";
        header("Location: index.php");
        exit();
    }
    
    if ($category_feature_available) {
        $stmt->bind_param("ssiii", $title, $description, $anonymous, $user_id, $category_id);
    } else {
        $stmt->bind_param("ssii", $title, $description, $anonymous, $user_id);
    }
    
    if ($stmt->execute()) {
        $problem_id = $stmt->insert_id;

        if ($category_feature_available && !empty($detected_tags)) {
            $tag_ids = processTagsFromString($conn, implode(',', $detected_tags));
            linkTagsToProblem($conn, $problem_id, $tag_ids);
        }

        $check_sql = "SELECT created_at FROM problems WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $problem_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $stored_time = $check_result->fetch_assoc()['created_at'];
        $check_stmt->close();
        
        error_log("Post Debug - PHP Time (Manila): $current_time, Stored Time (DB Session Manila): $stored_time");

        $target_url = "pages/view_problem?problem_id=" . $problem_id;
        $notification_sql = "INSERT INTO notifications (user_id, message, target_url, created_at) 
                             VALUES (?, ?, ?, NOW())";

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
