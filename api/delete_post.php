<?php
session_start();
include "../includes/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

if (!isset($_POST['problem_id']) || !is_numeric($_POST['problem_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid problem ID']);
    exit();
}

$problem_id = intval($_POST['problem_id']);
$user_id = $_SESSION['user_id'];

// First check if the user owns this post or is an admin
$check_sql = "SELECT user_id FROM problems WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $problem_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$problem = $result->fetch_assoc();

if (!$problem) {
    echo json_encode(['success' => false, 'message' => 'Problem not found']);
    exit();
}

if ($problem['user_id'] !== $user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authorized to delete this post']);
    exit();
}

// Start transaction to ensure all related data is deleted
$conn->begin_transaction();

try {
    // Delete related records first due to foreign key constraints
    
    // Delete notifications related to this problem
    $notifications_sql = "DELETE FROM notifications WHERE target_url LIKE ?";
    $notifications_stmt = $conn->prepare($notifications_sql);
    $pattern = "%problem_id=" . $problem_id . "%";
    $notifications_stmt->bind_param("s", $pattern);
    $notifications_stmt->execute();

    // Delete post likes
    $likes_sql = "DELETE FROM post_likes WHERE problem_id = ?";
    $likes_stmt = $conn->prepare($likes_sql);
    $likes_stmt->bind_param("i", $problem_id);
    $likes_stmt->execute();

    // Delete post shares
    $shares_sql = "DELETE FROM post_shares WHERE problem_id = ?";
    $shares_stmt = $conn->prepare($shares_sql);
    $shares_stmt->bind_param("i", $problem_id);
    $shares_stmt->execute();

    // Delete solution reactions and replies
    $solution_ids_sql = "SELECT id FROM solutions WHERE problem_id = ?";
    $solution_ids_stmt = $conn->prepare($solution_ids_sql);
    $solution_ids_stmt->bind_param("i", $problem_id);
    $solution_ids_stmt->execute();
    $solution_ids_result = $solution_ids_stmt->get_result();
    
    while ($solution = $solution_ids_result->fetch_assoc()) {
        // Delete solution reactions
        $reactions_sql = "DELETE FROM solution_reactions WHERE solution_id = ?";
        $reactions_stmt = $conn->prepare($reactions_sql);
        $reactions_stmt->bind_param("i", $solution['id']);
        $reactions_stmt->execute();

        // Delete solution replies
        $replies_sql = "DELETE FROM solution_replies WHERE solution_id = ?";
        $replies_stmt = $conn->prepare($replies_sql);
        $replies_stmt->bind_param("i", $solution['id']);
        $replies_stmt->execute();
    }

    // Delete solutions
    $solutions_sql = "DELETE FROM solutions WHERE problem_id = ?";
    $solutions_stmt = $conn->prepare($solutions_sql);
    $solutions_stmt->bind_param("i", $problem_id);
    $solutions_stmt->execute();

    // Finally delete the problem itself
    $problem_sql = "DELETE FROM problems WHERE id = ?";
    $problem_stmt = $conn->prepare($problem_sql);
    $problem_stmt->bind_param("i", $problem_id);
    $problem_stmt->execute();

    // If we got here, everything succeeded
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);

} catch (Exception $e) {
    // Something went wrong, rollback the transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting post: ' . $e->getMessage()]);
}