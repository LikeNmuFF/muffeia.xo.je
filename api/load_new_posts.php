<?php
session_start();
include '../includes/db.php';
include '../includes/categories_tags.php';

$last_post_time = isset($_GET['last_post_time']) ? $_GET['last_post_time'] : '';
$user_id = $_SESSION['user_id'] ?? 0;
$category_feature_available = categoryFeatureAvailable($conn);

// Get new posts
$category_select = $category_feature_available
    ? "p.category_id, c.name as category_name, c.slug as category_slug, c.description as category_description, c.icon as category_icon,"
    : "NULL as category_id, NULL as category_name, NULL as category_slug, NULL as category_description, NULL as category_icon,";
$category_join = $category_feature_available ? "LEFT JOIN categories c ON p.category_id = c.id" : "";

$sql = "SELECT p.id, p.title, p.description, $category_select u.username, u.profile_pic, p.anonymous, p.created_at,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id AND user_id = ?) as user_liked
        FROM problems p
        JOIN users u ON p.user_id = u.id
        $category_join
        WHERE p.created_at > ?
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $last_post_time);
$stmt->execute();
$result = $stmt->get_result();

$posts_html = '';
$latest_post_time = $last_post_time;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $username = htmlspecialchars($row['username']);
        $title = htmlspecialchars($row['title']);
        $description = nl2br(htmlspecialchars($row['description']));
        $author = $row['anonymous'] ? 'Anonymous' : '<a href="pages/profile.php?user_id='.$row['user_id'].'" style="color:inherit;text-decoration:none;">'.$username.'</a>';
        $initials = $row['anonymous'] ? 'A' : strtoupper(substr($username, 0, 1));
        $likedClass = $row['user_liked'] ? 'liked' : '';
        $heartIcon = $row['user_liked'] ? 'fas' : 'far';
        $likeCount = (int)$row['like_count'];
        $category = ($category_feature_available && !empty($row['category_id'])) ? [
            'id' => $row['category_id'],
            'name' => $row['category_name'],
            'slug' => $row['category_slug'],
            'description' => $row['category_description'],
            'icon' => $row['category_icon']
        ] : null;
        $tags = $category_feature_available ? getProblemTags($conn, $row['id']) : [];
        $taxonomyHTML = '';
        if ($category || !empty($tags)) {
            $taxonomyHTML = '<div class="post-taxonomy" style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 10px;">'
                . renderCategoryBadge($category)
                . renderProblemTags($tags)
                . '</div>';
        }

        $avatarHTML = '<div class="post-avatar" style="overflow:hidden;">';
        if (!$row['anonymous'] && !empty($row['profile_pic'])) {
            $pic = htmlspecialchars($row['profile_pic']);
            $avatarHTML .= '<img src="'.$pic.'" alt="'.$username.'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" onerror="this.style.display=\'none\'; this.parentNode.querySelector(\'.post-avatar-fallback\').style.display=\'flex\';">';
            $avatarHTML .= '<div class="post-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--clr-primary),var(--clr-secondary));color:#fff;font-weight:600;font-size:14px;border-radius:50%;">'.$initials.'</div>';
        } else {
            $avatarHTML .= $initials;
        }
        $avatarHTML .= '</div>';

        $posts_html .= '
        <div class="post-card card-elevated-md animate-in" data-problem-id="'.$row['id'].'">
            <div class="post-header">
                '.$avatarHTML.'
                <div class="post-meta">
                    <div class="post-author">'.$author.'</div>
                    <div class="post-time">'.date('M j, Y \a\t g:i A', strtotime($row['created_at'])).'</div>
                </div>
            </div>
            <div>
                <h3 class="post-title">'.$title.'</h3>
                '.$taxonomyHTML.'
                <p class="post-description">'.$description.'</p>
            </div>
            <div class="post-footer">
                <a href="/pages/view_problem.php?problem_id='.$row['id'].'" class="post-action">
                    <i class="fas fa-comments"></i> <span>View Solutions</span>
                </a>
                <button class="post-action like-btn '.$likedClass.'" data-problem-id="'.$row['id'].'" title="Like">
                    <i class="'.$heartIcon.' fa-heart"></i>
                    <span class="like-count">'.$likeCount.'</span>
                </button>
                <button class="post-action share-btn" data-problem-id="'.$row['id'].'" data-title="'.htmlspecialchars($row['title']).'" data-description="'.htmlspecialchars(substr($row['description'], 0, 500)).'" title="Share">
                    <i class="fas fa-share-alt"></i> <span>Share</span>
                </button>
            </div>
        </div>';
        
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
