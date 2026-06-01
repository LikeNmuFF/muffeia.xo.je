<?php
session_start();
include "../includes/db.php";
include "../includes/moderation.php";
include "../includes/categories_tags.php";

if (!isset($_GET['problem_id']) || !is_numeric($_GET['problem_id'])) {
    die("Invalid problem ID.");
}
$problem_id = intval($_GET['problem_id']);

// First fetch the problem details
$problem_sql = "SELECT p.*, u.username, u.profile_pic FROM problems p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = ?";
$problem_stmt = $conn->prepare($problem_sql);
$problem_stmt->bind_param("i", $problem_id);
$problem_stmt->execute();
$problem_result = $problem_stmt->get_result();
$problem = $problem_result->fetch_assoc();

if (!$problem) {
    die("Problem not found.");
}

$problem_category = !empty($problem['category_id']) ? getCategoryById($conn, intval($problem['category_id'])) : null;
$problem_tags = getProblemTags($conn, $problem_id);

// Now manage views:
// - Do NOT count views by the post owner
// - Count at most one view per logged-in account (tracked in problem_views table)
// - For guests, avoid double-counting within the same session

// Helper function to increment the problem counter
function incrementProblemViews($conn, $problem_id) {
    $views_sql = "UPDATE problems SET views_count = views_count + 1 WHERE id = ?";
    $views_stmt = $conn->prepare($views_sql);
    if ($views_stmt) {
        $views_stmt->bind_param("i", $problem_id);
        $views_stmt->execute();
    }
}

// If user is logged in, count only if they are not the owner and haven't viewed before
if (isset($_SESSION['user_id'])) {
    $viewer_id = intval($_SESSION['user_id']);
    // Do not count if the viewer is the owner of the post
    if ($viewer_id !== intval($problem['user_id'])) {
        $check_sql = "SELECT id FROM problem_views WHERE problem_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt) {
            $check_stmt->bind_param("ii", $problem_id, $viewer_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                // Insert tracking row and increment the counter
                $insert_sql = "INSERT INTO problem_views (problem_id, user_id, viewed_at) VALUES (?, ?, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                if ($insert_stmt) {
                    $insert_stmt->bind_param("ii", $problem_id, $viewer_id);
                    $insert_stmt->execute();
                }
                incrementProblemViews($conn, $problem_id);
            }
        } else {
            // Fallback: if we can't query problem_views, avoid double counting within session
            if (!isset($_SESSION['viewed_problems'])) {
                $_SESSION['viewed_problems'] = [];
            }
            if (!in_array($problem_id, $_SESSION['viewed_problems'])) {
                incrementProblemViews($conn, $problem_id);
                $_SESSION['viewed_problems'][] = $problem_id;
            }
        }
    }
} else {
    // Guest visitor: prevent multiple counts during the same session
    if (!isset($_SESSION['viewed_problems'])) {
        $_SESSION['viewed_problems'] = [];
    }
    if (!in_array($problem_id, $_SESSION['viewed_problems'])) {
        incrementProblemViews($conn, $problem_id);
        $_SESSION['viewed_problems'][] = $problem_id;
    }
}

$problem_sql = "SELECT p.*, u.username, u.profile_pic FROM problems p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = ?";
$problem_stmt = $conn->prepare($problem_sql);
$problem_stmt->bind_param("i", $problem_id);
$problem_stmt->execute();
$problem_result = $problem_stmt->get_result();
$problem = $problem_result->fetch_assoc();

if (!$problem) {
    die("Problem not found.");
}

// Handle AJAX requests for likes/dislikes/replies
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    
    if ($_POST['action'] == 'react') {
        $solution_id = intval($_POST['solution_id']);
        $reaction_type = $_POST['reaction_type'];
        
        // Check if user already reacted
        $check_sql = "SELECT * FROM solution_reactions WHERE solution_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $solution_id, $user_id);
        $check_stmt->execute();
        $existing_reaction = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing_reaction) {
            if ($existing_reaction['reaction_type'] == $reaction_type) {
                // Remove reaction if clicking same button
                $delete_sql = "DELETE FROM solution_reactions WHERE solution_id = ? AND user_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $solution_id, $user_id);
                $delete_stmt->execute();
            } else {
                // Update reaction if different type
                $update_sql = "UPDATE solution_reactions SET reaction_type = ? WHERE solution_id = ? AND user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sii", $reaction_type, $solution_id, $user_id);
                $update_stmt->execute();
            }
        } else {
            // Insert new reaction
            $insert_sql = "INSERT INTO solution_reactions (solution_id, user_id, reaction_type) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iis", $solution_id, $user_id, $reaction_type);
            $insert_stmt->execute();
        }
        
        // Get updated counts
        $likes_sql = "SELECT COUNT(*) as count FROM solution_reactions WHERE solution_id = ? AND reaction_type = 'like'";
        $likes_stmt = $conn->prepare($likes_sql);
        $likes_stmt->bind_param("i", $solution_id);
        $likes_stmt->execute();
        $likes_count = $likes_stmt->get_result()->fetch_assoc()['count'];
        
        $dislikes_sql = "SELECT COUNT(*) as count FROM solution_reactions WHERE solution_id = ? AND reaction_type = 'dislike'";
        $dislikes_stmt = $conn->prepare($dislikes_sql);
        $dislikes_stmt->bind_param("i", $solution_id);
        $dislikes_stmt->execute();
        $dislikes_count = $dislikes_stmt->get_result()->fetch_assoc()['count'];
        
        echo json_encode([
            'success' => true,
            'likes' => $likes_count,
            'dislikes' => $dislikes_count
        ]);
        exit();
        
    } elseif ($_POST['action'] == 'reply') {
        $solution_id = intval($_POST['solution_id']);
        $reply_text_raw = trim($_POST['reply_text']);
        // Server-side moderation for replies — mask bad words
        $reply_mod = moderate_text($reply_text_raw);
        if (!empty($reply_mod['flagged'])) {
            $reply_text_raw = mask_text($reply_text_raw);
        }
        $reply_text = htmlspecialchars($reply_text_raw);
        $is_anonymous = isset($_POST['anonymous_reply']) ? 1 : 0;
        
        $reply_sql = "INSERT INTO solution_replies (solution_id, user_id, reply_text, is_anonymous) VALUES (?, ?, ?, ?)";
        $reply_stmt = $conn->prepare($reply_sql);
        $reply_stmt->bind_param("iisi", $solution_id, $user_id, $reply_text, $is_anonymous);
        
        if ($reply_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Reply posted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to post reply']);
        }
        exit();
    }
}

// Handle solution submission (existing functionality)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    if (!isset($_SESSION['user_id'])) {
        die("User not logged in.");
    }

    if (!isset($_POST['content']) || empty(trim($_POST['content']))) {
        die("Solution content is required.");
    }

    $user_id = $_SESSION['user_id'];
    $content_raw = trim($_POST['content']);
    // Server-side moderation: mask bad words instead of blocking
    $mod_check = moderate_text($content_raw);
    if ($mod_check['flagged']) {
        $content_raw = mask_text($content_raw);
    }
    $content = htmlspecialchars($content_raw);
    $is_anonymous = isset($_POST['anonymous']) ? 1 : 0;

    $sql = "INSERT INTO solutions (problem_id, user_id, solution_text, is_anonymous, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $problem_id, $user_id, $content, $is_anonymous);
    
    if ($stmt->execute()) {
        if ($user_id != $problem['user_id']) {
            $target_url = "view_problem.php?problem_id=" . $problem_id;
            $commenter_name = $is_anonymous ? "Someone" : $_SESSION['username'];
            $notification_message = "$commenter_name commented on your problem: " . substr($problem['title'], 0, 50);
            
            $notification_sql = "INSERT INTO notifications (user_id, message, target_url, created_at, is_read) 
                                 VALUES (?, ?, ?, NOW(), 0)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("iss", $problem['user_id'], $notification_message, $target_url);
            $notification_stmt->execute();
        }
        header("Location: view_problem.php?problem_id=$problem_id");
        exit();
    } else {
        error_log("Database error in view_problem.php: " . $stmt->error);
        die("Something went wrong. Please try again later.");
    }
}

// Get solutions with reaction counts and replies
$solutions_sql = "SELECT s.*, u.username, u.profile_pic,
                  (SELECT COUNT(*) FROM solution_reactions WHERE solution_id = s.id AND reaction_type = 'like') as likes_count,
                  (SELECT COUNT(*) FROM solution_reactions WHERE solution_id = s.id AND reaction_type = 'dislike') as dislikes_count
                  FROM solutions s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.problem_id = ?
                  ORDER BY s.is_accepted DESC, (likes_count - dislikes_count) DESC, s.created_at DESC";
$solutions_stmt = $conn->prepare($solutions_sql);
$solutions_stmt->bind_param("i", $problem_id);
$solutions_stmt->execute();
$solutions_result = $solutions_stmt->get_result();

// Function to get replies for a solution
function getSolutionReplies($conn, $solution_id) {
    $replies_sql = "SELECT sr.*, u.username, u.profile_pic FROM solution_replies sr
                    JOIN users u ON sr.user_id = u.id
                    WHERE sr.solution_id = ?
                    ORDER BY sr.created_at ASC";
    $replies_stmt = $conn->prepare($replies_sql);
    $replies_stmt->bind_param("i", $solution_id);
    $replies_stmt->execute();
    return $replies_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark-mode')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <link rel="icon" type="png/jpg" href="/logo/m-blues.png">
    
    <meta name="keywords" content="Muffeia, <?php echo htmlspecialchars($problem['title']); ?>, support, solutions, community">
    <link rel="canonical" href="https://muffeia.xo.je/pages/view_problem.php?problem_id=<?php echo $problem_id; ?>">

<?php
    $safe_host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8');
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $base_url = $protocol . '://' . $safe_host;
?>
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $base_url . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($problem['title']); ?> | MUFFEIA">
    <meta property="og:description" content="<?php 
        $desc = strip_tags($problem['description']);
        $author = htmlspecialchars($problem['username']);
        $title = htmlspecialchars($problem['title']);
        $preview = mb_substr($desc, 0, 120);
        $ellipsis = (mb_strlen($desc) > 120) ? '...' : '';
        echo htmlspecialchars("[$author] $title - $preview$ellipsis"); 
    ?>">
    <?php 
        $has_profile_pic = !empty($problem['profile_pic']) && $problem['profile_pic'] != 'default.png';
        $og_image = $has_profile_pic 
            ? $base_url . "/" . htmlspecialchars($problem['profile_pic'])
            : $base_url . "/logo/muffeia.png";
    ?>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <meta property="og:image" content="<?php echo $og_image; ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?php echo $base_url . $_SERVER['REQUEST_URI']; ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($problem['title']); ?> | MUFFEIA">
    <meta property="twitter:description" content="<?php 
        echo htmlspecialchars("[$author] $title - $preview$ellipsis"); 
    ?>">
    <meta property="twitter:image" content="<?php echo $og_image; ?>">

    <title><?php echo htmlspecialchars($problem['title']); ?> | MUFFEIA</title>
    <style>
        /* ── Solutions Section ── */
        .solutions-section {
            margin-top: 2rem;
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .section-header h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--clr-text-theme);
            margin: 0;
        }
        .solutions-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* ── Solution Card ── */
        .solution-card {
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 12px;
            padding: 1.25rem;
            transition: box-shadow 0.2s;
        }
        .solution-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .solution-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }
        .solution-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--clr-bg-alt-theme);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--clr-primary);
            flex-shrink: 0;
        }
        .solution-meta {
            flex: 1;
        }
        .solution-author {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--clr-text-theme);
        }
        .solution-time {
            font-size: 0.8rem;
            color: var(--clr-text-tertiary-theme);
        }
        .solution-content {
            margin-bottom: 1rem;
            line-height: 1.6;
            color: var(--clr-text-secondary-theme);
        }
        .solution-content p {
            margin: 0;
        }

        /* ── Solution Actions ── */
        .solution-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-top: 0.75rem;
            border-top: 1px solid var(--clr-border-theme);
            flex-wrap: wrap;
        }
        .reaction-buttons {
            display: flex;
            gap: 4px;
        }
        .btn-reaction {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border: 1px solid var(--clr-border-theme);
            border-radius: 20px;
            background: transparent;
            color: var(--clr-text-tertiary-theme);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-reaction:hover {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
            background: color-mix(in srgb, var(--clr-primary) 8%, transparent);
        }
        .btn-reaction.active {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
            background: color-mix(in srgb, var(--clr-primary) 10%, transparent);
        }
        .btn-reaction.active .reaction-count {
            color: var(--clr-primary);
            font-weight: 600;
        }
        .btn-reply, .btn-icon {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border: none;
            border-radius: 20px;
            background: transparent;
            color: var(--clr-text-tertiary-theme);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-reply:hover, .btn-icon:hover {
            background: var(--clr-bg-alt-theme);
            color: var(--clr-text-theme);
        }

        /* ── Replies ── */
        .replies-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--clr-border-theme);
        }
        .replies-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .reply-card {
            padding: 12px 14px;
            background: color-mix(in srgb, var(--clr-surface-theme) 95%, var(--clr-text-theme));
            border-radius: 10px;
            margin-left: 8px;
            border-left: 3px solid color-mix(in srgb, var(--clr-primary) 25%, transparent);
        }
        .reply-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .reply-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--clr-bg-alt-theme);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--clr-primary);
            flex-shrink: 0;
        }
        .reply-author {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--clr-text-theme);
        }
        .reply-time {
            font-size: 0.75rem;
            color: var(--clr-text-tertiary-theme);
        }
        .reply-content {
            font-size: 0.9rem;
            color: var(--clr-text-secondary-theme);
            line-height: 1.5;
        }
        .reply-content p {
            margin: 0;
        }
        .reply-form-container {
            margin-top: 12px;
            margin-left: 8px;
        }
        .reply-form .form-group {
            margin-bottom: 8px;
        }
        .reply-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--clr-border-theme);
            border-radius: 8px;
            background: var(--clr-surface-theme);
            color: var(--clr-text-theme);
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .reply-form textarea:focus {
            outline: none;
            border-color: var(--clr-primary);
        }

        /* ── Post Your Solution Form ── */
        .solution-form-container {
            margin-top: 1.5rem;
            padding: 1.5rem;
            border-radius: 12px;
        }
        .solution-form-container .section-header {
            margin-bottom: 1rem;
        }
        .solution-form .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--clr-border-theme);
            border-radius: 8px;
            background: var(--clr-surface-theme);
            color: var(--clr-text-theme);
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .solution-form .form-group textarea:focus {
            outline: none;
            border-color: var(--clr-primary);
        }
        .solution-form .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }

        /* ── Login Prompt ── */
        .login-prompt {
            margin-top: 1.5rem;
            padding: 2rem;
            text-align: center;
            background: var(--clr-surface-theme);
            border: 1px dashed var(--clr-border-theme);
            border-radius: 12px;
        }
        .login-prompt i {
            font-size: 2rem;
            color: var(--clr-text-tertiary-theme);
            margin-bottom: 12px;
        }
        .login-prompt p {
            margin: 0;
            color: var(--clr-text-secondary-theme);
            font-size: 0.95rem;
        }
        .login-prompt a {
            color: var(--clr-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .login-prompt a:hover {
            text-decoration: underline;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--clr-surface-theme);
            border: 1px dashed var(--clr-border-theme);
            border-radius: 12px;
        }
        .empty-state i {
            font-size: 2.5rem;
            color: var(--clr-text-tertiary-theme);
            margin-bottom: 1rem;
        }
        .empty-state h3 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            color: var(--clr-text-theme);
        }
        .empty-state p {
            margin: 0 0 1rem 0;
            color: var(--clr-text-secondary-theme);
        }
    </style>
    </head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                    <img src="../logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
                    <span>Muffeia</span>
                </div>
                <button class="sidebar-close" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="theme-toggle">
                    <input type="checkbox" id="theme-toggle" />
                    <span class="slider">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </span>
                </label>
                <span class="theme-label">Dark Mode</span>
            </div>
            
            <div class="nav-items">
                <a href="../index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="message.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge">3</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../auth/logout.php" class="nav-item logout" style="margin-top: auto;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>

        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation Bar -->
            <header class="top-nav">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="top-nav-content">
                    <h1>Problem Details</h1>
                    <div class="user-actions">
                        <button class="icon-btn search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="notifications.php" class="icon-btn notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot"></span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content">
                <?php if (isset($_SESSION['moderation_block'])): ?>
                    <div class="error-message" style="margin-bottom:16px;">
                        <?php echo htmlspecialchars($_SESSION['moderation_block']); unset($_SESSION['moderation_block']); ?>
                    </div>
                <?php endif; ?>
                <!-- Problem Card -->
                <div class="post-card card-elevated-md animate-in">
                    <div class="post-header">
                        <div class="post-avatar" style="overflow:hidden;">
                            <?php if ($problem['anonymous']): ?>
                                <i class="fas fa-user-secret" style="font-size:1.4rem;color:var(--clr-text-tertiary-theme);"></i>
                            <?php elseif (!empty($problem['profile_pic'])): ?>
                                <img src="../<?php echo htmlspecialchars($problem['profile_pic']); ?>"
                                     alt="<?php echo htmlspecialchars($problem['username']); ?>"
                                     style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                            <?php else: ?>
                                <?php echo strtoupper(substr(htmlspecialchars($problem['username']), 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="post-meta">
                            <div class="post-author"><?php if ($problem['anonymous']): ?>Anonymous<?php else: ?><a href="profile.php?user_id=<?php echo $problem['user_id']; ?>" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($problem['username']); ?></a><?php endif; ?></div>
                            <div class="post-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('M j, Y \a\t g:i A', strtotime($problem['created_at'])); ?>
                            </div>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div style="margin-left: auto; display: flex; gap: 8px; align-items: flex-start;">
                            <?php if ($problem['user_id'] == $_SESSION['user_id']): ?>
                            <a href="edit_post.php?post_id=<?php echo $problem['id']; ?>" class="post-action" title="Edit post">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="post-action delete-post" onclick="deletePost(<?php echo $problem['id']; ?>)" title="Delete post">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <?php endif; ?>
                            <button class="post-action" onclick="reportPost(<?php echo $problem['id']; ?>, '<?php echo $_SESSION['csrf_token']; ?>')" title="Report post">
                                <i class="fas fa-flag"></i>
                            </button>
                            <button class="post-action bookmark-btn" onclick="toggleBookmark(<?php echo $problem['id']; ?>)" title="Save post">
                                <i class="far fa-bookmark"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 class="post-title" style="font-size:1.25rem;"><?php echo htmlspecialchars($problem['title']); ?></h3>
                        <?php if ($problem_category || !empty($problem_tags)): ?>
                            <div class="post-taxonomy" style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 10px;">
                                <?php echo renderCategoryBadge($problem_category); ?>
                                <?php echo renderProblemTags($problem_tags); ?>
                            </div>
                        <?php endif; ?>
                        <p class="post-description"><?php echo nl2br(htmlspecialchars($problem['description'])); ?></p>
                    </div>

                    <div class="post-footer">
                        <div class="stat" style="display:flex;align-items:center;gap:6px;color:var(--clr-text-secondary-theme);font-size:14px;">
                            <i class="fas fa-comments"></i>
                            <span><?php echo $solutions_result->num_rows; ?> Solutions</span>
                        </div>
                        <div class="stat" style="display:flex;align-items:center;gap:6px;color:var(--clr-text-secondary-theme);font-size:14px;">
                            <i class="far fa-eye"></i>
                            <span><?php echo $problem['views_count'] ?? 0; ?> Views</span>
                        </div>
                    </div>
                </div>

                <!-- Solutions Section -->
                <div class="solutions-section">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-lightbulb"></i>
                            Solutions (<?php echo $solutions_result->num_rows; ?>)
                        </h3>
                    </div>

                    <?php if ($solutions_result->num_rows > 0): ?>
                        <div class="solutions-list">
                            <?php while ($solution = $solutions_result->fetch_assoc()): ?>
                                <?php 
                                $replies_result = getSolutionReplies($conn, $solution['id']);
                                $user_reacted = null;
                                if (isset($_SESSION['user_id'])) {
                                    $reaction_sql = "SELECT reaction_type FROM solution_reactions WHERE solution_id = ? AND user_id = ?";
                                    $reaction_stmt = $conn->prepare($reaction_sql);
                                    $reaction_stmt->bind_param("ii", $solution['id'], $_SESSION['user_id']);
                                    $reaction_stmt->execute();
                                    $user_reaction = $reaction_stmt->get_result()->fetch_assoc();
                                    $user_reacted = $user_reaction['reaction_type'] ?? null;
                                }
                                ?>
                                <div class="solution-card" id="solution-<?php echo $solution['id']; ?>">
                                    <div class="solution-header">
                                        <div class="solution-avatar" style="overflow:hidden;">
                                            <?php if ($solution['is_anonymous']): ?>
                                                <i class="fas fa-user-secret" style="font-size:1.1rem;color:var(--clr-text-tertiary-theme);"></i>
                                            <?php elseif (!empty($solution['profile_pic'])): ?>
                                                <img src="../<?php echo htmlspecialchars($solution['profile_pic']); ?>"
                                                     alt="<?php echo htmlspecialchars($solution['username']); ?>"
                                                     style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr(htmlspecialchars($solution['username']), 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="solution-meta">
                                            <div class="solution-author">
                                                <?php if ($solution['is_anonymous']): ?>Anonymous<?php else: ?><a href="profile.php?user_id=<?php echo $solution['user_id']; ?>" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($solution['username']); ?></a><?php endif; ?>
                                                <?php if ($solution['is_accepted']): ?>
                                                    <span class="accepted-badge" style="display:inline-flex;align-items:center;gap:4px;background:#059669;color:white;font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px;">
                                                        <i class="fas fa-check-circle"></i> Accepted
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="solution-time">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('M j, Y \a\t g:i A', strtotime($solution['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="solution-content">
                                        <p><?php echo htmlspecialchars($solution['solution_text']); ?></p>
                                    </div>

                                    <div class="solution-actions">
                                        <div class="reaction-buttons">
                                            <button class="btn-reaction like-btn <?php echo $user_reacted == 'like' ? 'active' : ''; ?>"
                                                    data-solution-id="<?php echo $solution['id']; ?>"
                                                    data-reaction-type="like">
                                                <i class="far fa-thumbs-up"></i>
                                                <span class="reaction-count"><?php echo $solution['likes_count']; ?></span>
                                            </button>
                                            <button class="btn-reaction dislike-btn <?php echo $user_reacted == 'dislike' ? 'active' : ''; ?>"
                                                    data-solution-id="<?php echo $solution['id']; ?>"
                                                    data-reaction-type="dislike">
                                                <i class="far fa-thumbs-down"></i>
                                                <span class="reaction-count"><?php echo $solution['dislikes_count']; ?></span>
                                            </button>
                                        </div>
                                        <button class="btn-reply" data-solution-id="<?php echo $solution['id']; ?>">
                                            <i class="far fa-comment"></i>
                                            <span>Reply</span>
                                        </button>
                                        <?php if (isset($_SESSION['user_id']) && $problem['user_id'] == $_SESSION['user_id']): ?>
                                            <button class="btn-icon accept-btn <?php echo $solution['is_accepted'] ? 'accepted' : ''; ?>" onclick="acceptSolution(<?php echo $solution['id']; ?>)" title="<?php echo $solution['is_accepted'] ? 'Unaccept solution' : 'Accept as solution'; ?>">
                                                <i class="fas fa-check-circle"></i>
                                                <span><?php echo $solution['is_accepted'] ? 'Accepted' : 'Accept'; ?></span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon" title="Share">
                                            <i class="fas fa-share-alt"></i>
                                            <span>Share</span>
                                        </button>
                                    </div>

                                    <!-- Replies Section -->
                                    <div class="replies-section" id="replies-<?php echo $solution['id']; ?>" style="display:none;">
                                        <?php if ($replies_result->num_rows > 0): ?>
                                            <div class="replies-list">
                                                <?php while ($reply = $replies_result->fetch_assoc()): ?>
                                                    <div class="reply-card">
                                                        <div class="reply-header">
                                                            <div class="reply-avatar" style="overflow:hidden;">
                                                                <?php if ($reply['is_anonymous']): ?>
                                                                    <i class="fas fa-user-secret" style="font-size:0.9rem;color:var(--clr-text-tertiary-theme);"></i>
                                                                <?php elseif (!empty($reply['profile_pic'])): ?>
                                                                    <img src="../<?php echo htmlspecialchars($reply['profile_pic']); ?>"
                                                                         alt="<?php echo htmlspecialchars($reply['username']); ?>"
                                                                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                                                                <?php else: ?>
                                                                    <?php echo strtoupper(substr(htmlspecialchars($reply['username']), 0, 1)); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="reply-meta">
                                                                <div class="reply-author"><?php echo $reply['is_anonymous'] ? 'Anonymous' : htmlspecialchars($reply['username']); ?></div>
                                                                <span class="reply-time">
                                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($reply['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="reply-content">
                                                            <p><?php echo htmlspecialchars($reply['reply_text']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Reply Form -->
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <div class="reply-form-container">
                                                <form class="reply-form" data-solution-id="<?php echo $solution['id']; ?>">
                                                    <div class="form-group">
                                                        <textarea name="reply_text" rows="2" placeholder="Write a reply..." required data-badword-action="mask"></textarea>
                                                    </div>
                                                    <div class="form-options" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                                        <label class="checkbox-container small">
                                                            <input type="checkbox" name="anonymous_reply" value="1">
                                                            <span class="checkmark"></span>
                                                            Post Anonymously
                                                        </label>
                                                        <button type="submit" class="btn-submit small">
                                                            <i class="fas fa-reply"></i>
                                                            Reply
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-lightbulb"></i>
                            <h3>No solutions yet</h3>
                            <p>Be the first to share a solution to this problem!</p>
                        </div>
                    <?php endif; ?>

                    <!-- Solution Form -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="solution-form-container card-elevated-md">
                            <div class="section-header">
                                <h3>
                                    <i class="fas fa-edit"></i>
                                    Post Your Solution
                                </h3>
                            </div>
                            <form action="view_problem.php?problem_id=<?php echo $problem_id; ?>" method="POST" class="solution-form">
                                <div class="form-group">
                                    <textarea name="content" rows="5" placeholder="Write your detailed solution here..." required data-badword-action="mask"></textarea>
                                </div>

                                <div class="form-options">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="anonymous" value="1">
                                        <span class="checkmark"></span>
                                        Post Anonymously
                                    </label>

                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-paper-plane"></i>
                                        Submit Solution
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <i class="fas fa-sign-in-alt"></i>
                            <p>
                                <a href="../auth/login.php">Log in</a> to post a solution and help solve this problem.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>



    <script src="../js/mode.js"></script>
    <script src="../js/badword-filter.js"></script>
    <script src="../js/post-actions.js"></script>
    <script src="../js/report-post.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function openSidebar() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            menuToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);

            // Like/Dislike functionality
            document.querySelectorAll('.btn-reaction').forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.dataset.solutionId;
                    const reactionType = this.dataset.reactionType;
                    
                    fetch('view_problem.php?problem_id=<?php echo $problem_id; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=react&solution_id=${solutionId}&reaction_type=${reactionType}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const solutionCard = document.getElementById(`solution-${solutionId}`);
                            const likeBtn = solutionCard.querySelector('.like-btn');
                            const dislikeBtn = solutionCard.querySelector('.dislike-btn');
                            const likeCount = likeBtn.querySelector('.reaction-count');
                            const dislikeCount = dislikeBtn.querySelector('.reaction-count');
                            
                            // Update counts
                            likeCount.textContent = data.likes;
                            dislikeCount.textContent = data.dislikes;
                            
                            // Update active states
                            if (reactionType === 'like') {
                                likeBtn.classList.toggle('active');
                                dislikeBtn.classList.remove('active');
                            } else {
                                dislikeBtn.classList.toggle('active');
                                likeBtn.classList.remove('active');
                            }
                        }
                    });
                });
            });

            // Reply form submission
            document.querySelectorAll('.reply-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const solutionId = this.dataset.solutionId;
                    const formData = new FormData(this);
                    
                    fetch('view_problem.php?problem_id=<?php echo $problem_id; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=reply&solution_id=${solutionId}&${new URLSearchParams(formData)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.reset();
                            location.reload(); // Reload to show new reply
                        } else {
                            alert('Failed to post reply: ' + data.message);
                        }
                    });
                });
            });

            // Toggle reply form visibility
            document.querySelectorAll('.btn-reply').forEach(button => {
                button.addEventListener('click', function() {
                    const solutionId = this.dataset.solutionId;
                    const repliesSection = document.getElementById(`replies-${solutionId}`);
                    repliesSection.style.display = repliesSection.style.display === 'none' ? 'block' : 'none';
                });
            });
        });

        function acceptSolution(solutionId) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('../api/accept_solution.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `solution_id=${solutionId}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.csrf_token) {
                        document.querySelector('meta[name="csrf-token"]').content = data.csrf_token;
                    }
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Accept solution error:', err);
                alert('Error accepting solution. Please try again.');
            });
        }
    </script>
</body>
</html>
