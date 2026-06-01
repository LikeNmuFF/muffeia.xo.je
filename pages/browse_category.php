<?php
session_start();
include '../includes/db.php';
include '../includes/categories_tags.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

if (!$category_id) {
    header("Location: ../index.php");
    exit();
}

$category = getCategoryById($conn, $category_id);
if (!$category) {
    die("Category not found");
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get problems in this category
$problems = getProblemsByCategory($conn, $category_id, $limit, $offset, $_SESSION['user_id']);
$total_count = getProblemCountByCategory($conn, $category_id);
$total_pages = ceil($total_count / $limit);

// Get tags for each problem
foreach ($problems as &$problem) {
    $problem['tags'] = getProblemTags($conn, $problem['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - MUFFEIA</title>
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <style>
        .browse-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .category-header {
            background: linear-gradient(135deg, <?php echo generateCategoryColor($category_id); ?> 0%, rgba(99, 102, 241, 0.1) 100%);
            padding: 40px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            color: white;
        }
        
        .category-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }
        
        .category-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .category-stats {
            margin-top: 15px;
            display: flex;
            gap: 20px;
            opacity: 0.95;
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
        
        <div class="category-header">
            <h1><?php echo htmlspecialchars($category['name']); ?></h1>
            <p><?php echo htmlspecialchars($category['description']); ?></p>
            <div class="category-stats">
                <div>
                    <strong><?php echo $total_count; ?></strong> posts
                </div>
            </div>
        </div>
        
        <?php if (empty($problems)): ?>
            <div class="empty-state">
                <p>No posts in this category yet. Be the first to share!</p>
                <a href="../index.php" style="color: var(--primary); text-decoration: none;">← Go back</a>
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
                            <?php foreach ($problem['tags'] as $tag): ?>
                                <a href="browse_tags.php?tag_id=<?php echo $tag['id']; ?>" class="tag-chip">#<?php echo htmlspecialchars($tag['name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="post-footer">
                        <div class="post-stats">
                            <span>❤️ <?php echo $problem['like_count']; ?> likes</span>
                            <span>💬 <?php echo $problem['like_count']; ?> responses</span>
                        </div>
                        <a href="view_problem.php?problem_id=<?php echo $problem['id']; ?>" style="color: var(--primary); text-decoration: none;">View →</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?category_id=<?php echo $category_id; ?>&page=1">« First</a>
                        <a href="?category_id=<?php echo $category_id; ?>&page=<?php echo $page - 1; ?>">‹ Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?category_id=<?php echo $category_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?category_id=<?php echo $category_id; ?>&page=<?php echo $page + 1; ?>">Next ›</a>
                        <a href="?category_id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
