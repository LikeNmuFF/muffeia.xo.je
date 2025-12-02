<?php
session_start();
include '../includes/db.php';

// Pagination variables
$limit = 5; // Should match the limit in index.php
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch problems for the main feed, including the anonymous flag
$sql = "SELECT problems.id, problems.title, problems.description, users.username, problems.anonymous, problems.created_at 
        FROM problems 
        JOIN users ON problems.user_id = users.id 
        ORDER BY problems.created_at DESC
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total count of posts for infinite scroll
$count_sql = "SELECT COUNT(*) as total FROM problems";
$count_result = $conn->query($count_sql);
$total_posts = $count_result->fetch_assoc()['total'];
$hasMore = ($offset + $limit) < $total_posts;

$postsHTML = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $postsHTML .= '
        <div class="post-card">
            <h3>'.htmlspecialchars($row['title']).'</h3>
            <p>'.htmlspecialchars($row['description']).'</p>
            <p class="author">Posted by '.($row['anonymous'] ? 'Anonymous' : htmlspecialchars($row['username'])).' on '.$row['created_at'].'</p>
            <a href="view_problem.php?problem_id='.$row['id'].'" class="view-link">
                View Solutions
            </a>
        </div>';
    }
}

echo json_encode([
    'posts' => $postsHTML,
    'hasMore' => $hasMore
]);
?>