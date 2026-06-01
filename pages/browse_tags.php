<?php
session_start();
include '../includes/db.php';
include '../includes/categories_tags.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$tag_id = isset($_GET['tag_id']) ? intval($_GET['tag_id']) : null;

if (!$tag_id) {
    // Show all tags
    $show_all_tags = true;
    $tags = getPopularTags($conn, 50);
} else {
    $show_all_tags = false;
    $tag_stmt = $conn->prepare("SELECT * FROM tags WHERE id = ?");
    $tag_stmt->bind_param("i", $tag_id);
    $tag_stmt->execute();
    $tag = $tag_stmt->get_result()->fetch_assoc();
    $tag_stmt->close();
    if (!$tag) {
        die("Tag not found");
    }
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get problems with this tag
    $problems = getProblemsByTag($conn, $tag_id, $limit, $offset, $_SESSION['user_id']);
    $total_count = getProblemCountByTag($conn, $tag_id);
    $total_pages = ceil($total_count / $limit);
    
    // Get tags for each problem
    foreach ($problems as &$problem) {
        $problem['tags'] = getProblemTags($conn, $problem['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_all_tags ? 'Browse Tags' : htmlspecialchars($tag['name']); ?> - MUFFEIA</title>
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <style>
        .browse-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header {
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        
        .header p {
            color: var(--clr-text-secondary-theme);
            margin: 0;
        }
        
        .tags-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin: 30px 0;
        }
        
        .tag-card {
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .tag-card:hover {
            border-color: var(--clr-primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }
        
        .tag-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--clr-primary);
            margin-bottom: 5px;
        }
        
        .tag-count {
            font-size: 13px;
            color: var(--clr-text-secondary-theme);
        }
        
        .post-card {
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .post-card:hover {
            border-color: var(--clr-primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .post-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--clr-text-theme);
            text-decoration: none;
            margin: 0;
        }
        
        .post-meta {
            font-size: 13px;
            color: var(--clr-text-secondary-theme);
            margin-top: 8px;
        }
        
        .post-excerpt {
            color: var(--clr-text-secondary-theme);
            margin: 12px 0;
            line-height: 1.5;
        }
        
        .post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--clr-border-theme);
        }
        
        .post-stats {
            display: flex;
            gap: 20px;
            font-size: 13px;
        }
        
        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .tag-chip {
            display: inline-block;
            background: var(--clr-bg-alt-theme);
            color: var(--clr-primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .tag-chip:hover {
            background: var(--clr-primary);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 30px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--clr-border-theme);
            border-radius: 4px;
            text-decoration: none;
            color: var(--clr-primary);
        }
        
        .pagination a:hover {
            background: var(--clr-primary);
            color: white;
        }
        
        .pagination .active {
            background: var(--clr-primary);
            color: white;
            border-color: var(--clr-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--clr-text-secondary-theme);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--clr-primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="browse-container">
        <a href="../index.php" class="back-link">← Back to Feed</a>
        
        <?php if ($show_all_tags): ?>
            <div class="header">
                <h1>Browse Tags</h1>
                <p>Explore popular topics and discussions</p>
            </div>
            
            <div class="tags-grid">
                <?php foreach ($tags as $t): ?>
                    <a href="?tag_id=<?php echo $t['id']; ?>" class="tag-card">
                        <div class="tag-name">#<?php echo htmlspecialchars($t['name']); ?></div>
                        <div class="tag-count"><?php echo $t['usage_count'] ?? 0; ?> posts</div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>#<?php echo htmlspecialchars($tag['name']); ?></h1>
                <p><?php echo $total_count; ?> posts with this tag</p>
            </div>
            
            <?php if (empty($problems)): ?>
                <div class="empty-state">
                    <p>No posts with this tag yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($problems as $problem): ?>
                    <div class="post-card">
                        <div class="post-header">
                            <div style="flex: 1;">
                                <a href="view_problem.php?problem_id=<?php echo $problem['id']; ?>" class="post-title">
                                    <?php echo htmlspecialchars(substr($problem['title'], 0, 100)); ?>
                                </a>
                                <div class="post-meta">
                                    by <strong><?php echo htmlspecialchars($problem['anonymous'] ? 'Anonymous' : $problem['username']); ?></strong>
                                    • <?php echo date('M d, Y', strtotime($problem['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="post-excerpt">
                            <?php echo nl2br(htmlspecialchars(substr($problem['description'], 0, 150))); ?>...
                        </div>
                        
                        <?php if (!empty($problem['tags'])): ?>
                            <div class="post-tags">
                                <?php foreach ($problem['tags'] as $t): ?>
                                    <a href="?tag_id=<?php echo $t['id']; ?>" class="tag-chip">#<?php echo htmlspecialchars($t['name']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-footer">
                            <div class="post-stats">
                                <span>❤️ <?php echo $problem['like_count']; ?> likes</span>
                            </div>
                            <a href="view_problem.php?problem_id=<?php echo $problem['id']; ?>" style="color: var(--primary); text-decoration: none;">View →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?tag_id=<?php echo $tag_id; ?>&page=1">« First</a>
                            <a href="?tag_id=<?php echo $tag_id; ?>&page=<?php echo $page - 1; ?>">‹ Prev</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?tag_id=<?php echo $tag_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?tag_id=<?php echo $tag_id; ?>&page=<?php echo $page + 1; ?>">Next ›</a>
                            <a href="?tag_id=<?php echo $tag_id; ?>&page=<?php echo $total_pages; ?>">Last »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
