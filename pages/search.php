<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$total_results = 0;
$results = [];
$total_pages = 0;

if (!empty($search_query) && strlen($search_query) >= 2) {
    $query_param = "%" . $conn->real_escape_string($search_query) . "%";
    
    if ($search_type === 'all' || $search_type === 'users') {
        $user_results = [];
        $user_stmt = $conn->prepare("
            SELECT id, username, profile_pic, reputation_score, (SELECT COUNT(*) FROM user_badges WHERE user_id = users.id) as badge_count
            FROM users 
            WHERE username LIKE ? OR email LIKE ?
            ORDER BY reputation_score DESC
            LIMIT ? OFFSET ?
        ");
        $user_stmt->bind_param("ssii", $query_param, $query_param, $limit, $offset);
        $user_stmt->execute();
        $user_results = $user_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $user_stmt->close();
        
        $user_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username LIKE ? OR email LIKE ?");
        $user_count_stmt->bind_param("ss", $query_param, $query_param);
        $user_count_stmt->execute();
        $user_count = $user_count_stmt->get_result()->fetch_assoc()['count'];
        $user_count_stmt->close();
        
        $results['users'] = $user_results;
        $results['user_count'] = $user_count;
    }
    
    if ($search_type === 'all' || $search_type === 'posts') {
        $post_results = [];
        $post_stmt = $conn->prepare("
            SELECT p.id, p.title, p.description, p.user_id, p.anonymous, p.created_at, u.username, u.profile_pic,
                   (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM solutions WHERE problem_id = p.id) as solution_count
            FROM problems p
            JOIN users u ON p.user_id = u.id
            WHERE p.title LIKE ? OR p.description LIKE ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $post_stmt->bind_param("ssii", $query_param, $query_param, $limit, $offset);
        $post_stmt->execute();
        $post_results = $post_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $post_stmt->close();
        
        $post_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM problems WHERE title LIKE ? OR description LIKE ?");
        $post_count_stmt->bind_param("ss", $query_param, $query_param);
        $post_count_stmt->execute();
        $post_count = $post_count_stmt->get_result()->fetch_assoc()['count'];
        $post_count_stmt->close();
        
        $results['posts'] = $post_results;
        $results['post_count'] = $post_count;
    }
    
    $total_results = ($results['user_count'] ?? 0) + ($results['post_count'] ?? 0);
    $total_pages = ceil($total_results / $limit);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark-mode')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search — MUFFEIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <link rel="icon" href="/logo/m-blues.png" type="image/png">
    <style>
        .search-page {
            min-height: 100vh;
            background: var(--clr-bg-theme);
            position: relative;
        }

        .search-page::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -30%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(212,74,108,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .search-page::after {
            content: '';
            position: fixed;
            bottom: -30%;
            left: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(42,157,143,0.05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .search-wrap {
            position: relative;
            z-index: 1;
            max-width: 960px;
            margin: 0 auto;
            padding: 40px 24px 80px;
        }

        .search-top {
            margin-bottom: 48px;
            animation: fadeSlideDown 0.6s ease both;
        }

        .search-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--clr-text-tertiary-theme);
            margin-bottom: 20px;
            font-family: var(--font-heading, Outfit);
            letter-spacing: 0.3px;
        }

        .search-breadcrumb a {
            color: var(--clr-text-secondary-theme);
            text-decoration: none;
            transition: color 0.2s;
        }

        .search-breadcrumb a:hover {
            color: var(--clr-primary);
        }

        .search-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: clamp(28px, 4vw, 44px);
            color: var(--clr-text-theme);
            margin: 0 0 8px;
            line-height: 1.15;
        }

        .search-title i {
            color: var(--clr-primary);
            opacity: 0.7;
            font-size: 0.8em;
            margin-right: 8px;
        }

        .search-sub {
            font-size: 15px;
            color: var(--clr-text-secondary-theme);
            font-weight: 400;
            font-family: var(--font-heading, Outfit);
            margin-bottom: 32px;
        }

        .search-bar-wrap {
            position: relative;
            display: flex;
            gap: 12px;
            align-items: stretch;
        }

        .search-field {
            flex: 1;
            position: relative;
        }

        .search-field .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-text-tertiary-theme);
            font-size: 16px;
            pointer-events: none;
            transition: color 0.3s;
            z-index: 2;
        }

        .search-field:focus-within .search-icon {
            color: var(--clr-primary);
        }

        .search-input {
            width: 100%;
            padding: 16px 18px 16px 50px;
            font-size: 16px;
            font-family: var(--font-heading, Outfit);
            font-weight: 400;
            background: var(--clr-surface-theme);
            border: 2px solid var(--clr-border-theme);
            border-radius: 14px;
            color: var(--clr-text-theme);
            outline: none;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .search-input::placeholder {
            color: var(--clr-text-tertiary-theme);
            font-weight: 300;
        }

        .search-input:focus {
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 4px rgba(212,74,108,0.1), 0 4px 20px rgba(212,74,108,0.08);
        }

        .search-btn {
            padding: 16px 32px;
            background: var(--clr-primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            font-family: var(--font-heading, Outfit);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(212,74,108,0.25);
        }

        .search-btn:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(212,74,108,0.35);
        }

        .search-btn:active {
            transform: translateY(0);
        }

        .filter-strip {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 24px;
            animation: fadeSlideDown 0.6s 0.15s ease both;
        }

        .filter-pill {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-heading, Outfit);
            border: 1.5px solid var(--clr-border-theme);
            background: var(--clr-surface-theme);
            color: var(--clr-text-secondary-theme);
            border-radius: 100px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.2px;
            cursor: pointer;
        }

        .filter-pill:hover {
            border-color: var(--clr-primary-light);
            color: var(--clr-primary);
            background: var(--clr-primary-lighter);
        }

        .filter-pill.active {
            background: var(--clr-primary);
            border-color: var(--clr-primary);
            color: #fff;
            box-shadow: 0 2px 10px rgba(212,74,108,0.2);
        }

        .filter-pill i {
            margin-right: 6px;
            font-size: 12px;
        }

        .results-area {
            margin-top: 8px;
            animation: fadeSlideUp 0.5s 0.25s ease both;
        }

        .results-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0 12px;
            border-bottom: 1.5px solid var(--clr-border-theme);
            margin-bottom: 28px;
        }

        .results-count {
            font-size: 14px;
            color: var(--clr-text-secondary-theme);
            font-family: var(--font-heading, Outfit);
        }

        .results-count strong {
            color: var(--clr-text-theme);
            font-weight: 600;
        }

        .section-head {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--clr-text-theme);
            margin: 36px 0 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-head::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, var(--clr-border-theme), transparent);
        }

        .section-head .count-badge {
            font-family: var(--font-heading, Outfit);
            font-size: 12px;
            font-weight: 600;
            background: var(--clr-primary-lighter);
            color: var(--clr-primary);
            padding: 2px 10px;
            border-radius: 100px;
            margin-left: 4px;
        }

        .result-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .result-card {
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 14px;
            padding: 20px 24px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeSlideUp 0.4s ease both;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }

        .result-card:hover {
            border-color: var(--clr-primary-light);
            box-shadow: 0 8px 28px rgba(212,74,108,0.08), 0 2px 8px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }

        .result-card.user-card {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .result-card.user-card .user-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(212,74,108,0.15);
        }

        .result-card.user-card .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .result-card.user-card .user-body {
            flex: 1;
            min-width: 0;
        }

        .result-card.user-card .user-body h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            font-family: var(--font-heading, Outfit);
        }

        .result-card.user-card .user-body h3 a {
            color: var(--clr-text-theme);
            text-decoration: none;
        }

        .result-card.user-card .user-body h3 a:hover {
            color: var(--clr-primary);
        }

        .result-card.user-card .user-body .user-stats {
            font-size: 13px;
            color: var(--clr-text-secondary-theme);
            margin-top: 4px;
            display: flex;
            gap: 16px;
        }

        .result-card.user-card .user-body .user-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .result-card.user-card .user-body .user-stats i {
            font-size: 12px;
            opacity: 0.7;
        }

        .user-view-link {
            padding: 8px 18px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-heading, Outfit);
            color: var(--clr-primary);
            background: var(--clr-primary-lighter);
            text-decoration: none;
            transition: all 0.25s;
            white-space: nowrap;
        }

        .user-view-link:hover {
            background: var(--clr-primary);
            color: #fff;
        }

        .post-result .post-title {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 600;
            font-family: var(--font-heading, Outfit);
            line-height: 1.3;
        }

        .post-result .post-title a {
            color: var(--clr-text-theme);
            text-decoration: none;
        }

        .post-result .post-title a:hover {
            color: var(--clr-primary);
        }

        .post-excerpt {
            font-size: 14px;
            color: var(--clr-text-secondary-theme);
            line-height: 1.6;
            margin: 0 0 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-footer {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: var(--clr-text-tertiary-theme);
            font-family: var(--font-heading, Outfit);
            flex-wrap: wrap;
        }

        .post-footer span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .post-footer i {
            font-size: 11px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px 60px;
        }

        .empty-state .empty-icon {
            font-size: 48px;
            color: var(--clr-border-theme);
            margin-bottom: 20px;
            display: block;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--clr-text-theme);
            margin: 0 0 8px;
        }

        .empty-state p {
            color: var(--clr-text-secondary-theme);
            font-size: 15px;
            max-width: 400px;
            margin: 0 auto;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin: 48px 0 0;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination .page-current {
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            font-family: var(--font-heading, Outfit);
            text-decoration: none;
            transition: all 0.25s;
            min-width: 40px;
            text-align: center;
        }

        .pagination a {
            color: var(--clr-text-secondary-theme);
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
        }

        .pagination a:hover {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        .pagination .page-current {
            background: var(--clr-primary);
            color: #fff;
            border: 1px solid var(--clr-primary);
            box-shadow: 0 2px 10px rgba(212,74,108,0.2);
        }

        .pagination .page-nav {
            font-weight: 600;
        }

        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .search-wrap { padding: 24px 16px 60px; }
            .search-bar-wrap { flex-direction: column; }
            .search-btn { justify-content: center; padding: 14px; }
            .result-card { padding: 16px; }
            .result-card.user-card { flex-wrap: wrap; }
            .search-top { margin-bottom: 32px; }
            .results-meta { flex-direction: column; gap: 8px; align-items: flex-start; }
        }
    </style>
</head>
<body class="search-page">
    <div class="search-wrap">
        <div class="search-top">
            <div class="search-breadcrumb">
                <a href="../index.php"><i class="fas fa-home"></i></a>
                <span>/</span>
                <span>Search</span>
            </div>

            <h1 class="search-title"><i class="fas fa-search"></i>Discover</h1>
            <p class="search-sub">Find people, conversations, and insights across Muffeia</p>

            <form method="GET" class="search-bar-wrap">
                <div class="search-field">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="query" class="search-input" placeholder="Search for users, posts, or topics..." value="<?php echo htmlspecialchars($search_query); ?>" autofocus>
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-arrow-right"></i> Search
                </button>
            </form>

            <div class="filter-strip">
                <a href="?query=<?php echo urlencode($search_query); ?>&type=all" class="filter-pill <?php echo $search_type === 'all' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> All</a>
                <a href="?query=<?php echo urlencode($search_query); ?>&type=users" class="filter-pill <?php echo $search_type === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a>
                <a href="?query=<?php echo urlencode($search_query); ?>&type=posts" class="filter-pill <?php echo $search_type === 'posts' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Posts</a>
            </div>
        </div>

        <div class="results-area">
            <?php if (empty($search_query)): ?>
                <div class="empty-state" style="animation: fadeSlideUp 0.5s 0.3s ease both;">
                    <span class="empty-icon"><i class="fas fa-compass"></i></span>
                    <h3>What are you looking for?</h3>
                    <p>Type a keyword above to search through users, posts, and discussions across the community.</p>
                </div>
            <?php elseif ($total_results === 0): ?>
                <div class="empty-state" style="animation: fadeSlideUp 0.5s 0.3s ease both;">
                    <span class="empty-icon"><i class="fas fa-search-minus"></i></span>
                    <h3>No results found</h3>
                    <p>Nothing matches "<?php echo htmlspecialchars($search_query); ?>" — try different keywords or browse by category.</p>
                </div>
            <?php else: ?>
                <div class="results-meta">
                    <span class="results-count"><strong><?php echo $total_results; ?></strong> result<?php echo $total_results !== 1 ? 's' : ''; ?> for "<?php echo htmlspecialchars($search_query); ?>"</span>
                </div>

                <?php if (!empty($results['users']) && ($search_type === 'all' || $search_type === 'users')): ?>
                    <div class="section-head">
                        <i class="fas fa-users" style="color: var(--clr-primary);"></i> Users
                        <span class="count-badge"><?php echo $results['user_count']; ?></span>
                    </div>
                    <div class="result-grid">
                        <?php $delay = 0; foreach ($results['users'] as $user): ?>
                            <div class="result-card user-card" style="animation-delay: <?php echo $delay; ?>s">
                                <div class="user-avatar">
                                    <?php if ($user['profile_pic'] && file_exists("../uploads/profile_pics/" . $user['profile_pic'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-body">
                                    <h3><a href="profile.php?user_id=<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></a></h3>
                                    <div class="user-stats">
                                        <span><i class="fas fa-star"></i> <?php echo $user['reputation_score']; ?> rep</span>
                                        <span><i class="fas fa-medal"></i> <?php echo $user['badge_count']; ?> badges</span>
                                    </div>
                                </div>
                                <a href="profile.php?user_id=<?php echo $user['id']; ?>" class="user-view-link">View <i class="fas fa-arrow-right" style="margin-left:4px;font-size:11px;"></i></a>
                            </div>
                        <?php $delay += 0.06; endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['posts']) && ($search_type === 'all' || $search_type === 'posts')): ?>
                    <div class="section-head">
                        <i class="fas fa-file-alt" style="color: var(--clr-secondary);"></i> Posts
                        <span class="count-badge"><?php echo $results['post_count']; ?></span>
                    </div>
                    <div class="result-grid">
                        <?php $delay = 0; foreach ($results['posts'] as $post): ?>
                            <div class="result-card post-result" style="animation-delay: <?php echo $delay; ?>s">
                                <h3 class="post-title"><a href="view_problem.php?problem_id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars(mb_substr($post['title'], 0, 100)); ?></a></h3>
                                <p class="post-excerpt"><?php echo nl2br(htmlspecialchars(mb_substr($post['description'], 0, 150))); ?>...</p>
                                <div class="post-footer">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['anonymous'] ? 'Anonymous' : $post['username']); ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                    <span><i class="fas fa-heart"></i> <?php echo $post['like_count']; ?> likes</span>
                                    <span><i class="fas fa-comment"></i> <?php echo $post['solution_count']; ?> responses</span>
                                </div>
                            </div>
                        <?php $delay += 0.06; endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?query=<?php echo urlencode($search_query); ?>&type=<?php echo $search_type; ?>&page=1" class="page-nav"><i class="fas fa-angle-double-left"></i></a>
                            <a href="?query=<?php echo urlencode($search_query); ?>&type=<?php echo $search_type; ?>&page=<?php echo $page - 1; ?>" class="page-nav"><i class="fas fa-angle-left"></i></a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="page-current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?query=<?php echo urlencode($search_query); ?>&type=<?php echo $search_type; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?query=<?php echo urlencode($search_query); ?>&type=<?php echo $search_type; ?>&page=<?php echo $page + 1; ?>" class="page-nav"><i class="fas fa-angle-right"></i></a>
                            <a href="?query=<?php echo urlencode($search_query); ?>&type=<?php echo $search_type; ?>&page=<?php echo $total_pages; ?>" class="page-nav"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>
