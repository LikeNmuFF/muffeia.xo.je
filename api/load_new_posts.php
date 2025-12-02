<?php
session_start();
include '../includes/db.php';

$last_post_time = isset($_GET['last_post_time']) ? $_GET['last_post_time'] : '';

// Get new posts
$sql = "SELECT problems.id, problems.title, problems.description, users.username, problems.anonymous, problems.created_at 
        FROM problems 
        JOIN users ON problems.user_id = users.id 
        WHERE problems.created_at > ?
        ORDER BY problems.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $last_post_time);
$stmt->execute();
$result = $stmt->get_result();

$posts_html = '';
$latest_post_time = $last_post_time;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $posts_html .= '<div class="post-card">';
        $posts_html .= '<h3>'.htmlspecialchars($row['title']).'</h3>';
        $posts_html .= '<p>'.htmlspecialchars($row['description']).'</p>';
        $posts_html .= '<p class="author">Posted by '.($row['anonymous'] ? 'Anonymous' : htmlspecialchars($row['username'])).' on '.$row['created_at'].'</p>';
        $posts_html .= '<a href="view_problem.php?problem_id='.$row['id'].'" class="view-link">View Solutions</a>';
        $posts_html .= '</div>';
        
        // Update the latest post time
        if ($row['created_at'] > $latest_post_time) {
            $latest_post_time = $row['created_at'];
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'posts' => $posts_html,
    'latest_post_time' => $latest_post_time
]);
?>