<?php
include '../includes/db.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Determine which user's profile to show
$profile_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
$is_own_profile = ($profile_user_id == $_SESSION['user_id']);

// Fetch user details from the database
$sql = "SELECT username, email, created_at, profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../index.php");
    exit();
}

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'posts';

// Fetch posts created by the user with like counts
$sql_posts = "SELECT p.id, p.title, p.description, p.created_at, 
              (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count
              FROM problems p 
              WHERE p.user_id = ? 
              ORDER BY p.created_at DESC";
$stmt_posts = $conn->prepare($sql_posts);
$stmt_posts->bind_param("i", $profile_user_id);
$stmt_posts->execute();
$result_posts = $stmt_posts->get_result();

// Fetch saved/bookmarked posts (own profile only)
$saved_posts = null;
$saved_count = 0;
if ($is_own_profile) {
    $sql_saved = "SELECT p.id, p.title, p.description, p.created_at, p.anonymous, u.username,
                  (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count
                  FROM post_shares ps
                  JOIN problems p ON ps.problem_id = p.id
                  JOIN users u ON p.user_id = u.id
                  WHERE ps.user_id = ?
                  ORDER BY ps.id DESC";
    $stmt_saved = $conn->prepare($sql_saved);
    $stmt_saved->bind_param("i", $profile_user_id);
    $stmt_saved->execute();
    $saved_posts = $stmt_saved->get_result();
    $saved_count = $saved_posts->num_rows;
}

// Redirect saved tab to own profile if viewing someone else
if ($active_tab === 'saved' && !$is_own_profile) {
    $active_tab = 'posts';
}

// Get user stats
$stats_sql = "SELECT 
              (SELECT COUNT(*) FROM problems WHERE user_id = ?) as post_count,
              (SELECT COUNT(*) FROM solutions WHERE user_id = ?) as solution_count,
              (SELECT COUNT(*) FROM post_likes WHERE problem_id IN (SELECT id FROM problems WHERE user_id = ?)) as total_likes";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iii", $profile_user_id, $profile_user_id, $profile_user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Handle profile picture upload
$error_message = "";
$success_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    if ($_FILES["profile_pic"]["error"] == UPLOAD_ERR_NO_FILE) {
        $error_message = "No file selected. Please choose a picture to upload.";
    } else {
        $target_dir = "../uploads/profile_pics/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES["profile_pic"]["tmp_name"]);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];

        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $error_message = "File is not an image.";
        } elseif (!in_array($mime_type, $allowed_mimes)) {
            $error_message = "Invalid image type. Only JPG, PNG, and GIF are allowed.";
        } else {
            if (in_array(strtolower($file_extension), ["jpg", "jpeg", "png", "gif"])) {
                if ($_FILES["profile_pic"]["size"] > 5000000) {
                    $error_message = "Sorry, your file is too large. Maximum size is 5MB.";
                } else {
                    if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                        $old_path = $user['profile_pic'] ?? '';
                        if (!empty($old_path)) {
                            $full_old = __DIR__ . '/../' . $old_path;
                            if (file_exists($full_old)) {
                                unlink($full_old);
                            }
                        }
                        
                        $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $relative_path = "uploads/profile_pics/" . $new_filename;
                        $stmt->bind_param("si", $relative_path, $user_id);
                        if ($stmt->execute()) {
                            $user['profile_pic'] = $relative_path;
                            $success_message = "Profile picture updated successfully!";
                        } else {
                            $error_message = "Error updating profile picture in database.";
                        }
                    } else {
                        $error_message = "Error uploading the file.";
                    }
                }
            } else {
                $error_message = "Sorry, only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        }
    }
}

// Handle Post Deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security token validation failed. Please try again.';
    } else {
        $post_id = intval($_POST['delete_post']);
        $sql_check_post = "SELECT user_id FROM problems WHERE id = ?";
        $stmt_check_post = $conn->prepare($sql_check_post);
        $stmt_check_post->bind_param("i", $post_id);
        $stmt_check_post->execute();
        $result_check_post = $stmt_check_post->get_result();

        if ($result_check_post->num_rows > 0) {
            $post = $result_check_post->fetch_assoc();
            if ($post['user_id'] == $user_id) {
                $sql_delete = "DELETE FROM problems WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $post_id);
                if ($stmt_delete->execute()) {
                    $success_message = "Post deleted successfully!";
                } else {
                    $error_message = "Error deleting post.";
                }
            } else {
                $error_message = "You do not have permission to delete this post.";
            }
        } else {
            $error_message = "Post not found.";
        }
    }
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
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <title>Muffeia - Profile</title>
</head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                <img src="/logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                <!-- Dark mode logo -->
                <img src="/logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
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
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                 <a href="message.php" class="nav-item">
                     <i class="fas fa-envelope"></i>
                     <span>Messages</span>
                     <span class="badge" id="messageBadge" style="display: none;">0</span>
                 </a>
                 <a href="group-room.php" class="nav-item">
                     <i class="fas fa-users"></i>
                     <span>Group Chats</span>
                     <span class="badge" id="groupChatBadge" style="display: none;">0</span>
                 </a>
                 <a href="notifications.php" class="nav-item">
                     <i class="fas fa-bell"></i>
                     <span>Notifications</span>
                     <span class="badge" id="notificationBadge" style="display: none;">0</span>
                 </a>

                 <a href="settings.php" class="nav-item">
                     <i class="fas fa-cog"></i>
                     <span>Settings</span>
                 </a>
                    <a href="../auth/logout.php" class="nav-item logout">
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
                    <h1><?php echo $is_own_profile ? 'Your Profile' : htmlspecialchars($user['username']) . '\'s Profile'; ?></h1>
                    <div class="user-actions">
                        <a href="message.php" class="icon-btn message-btn" role="button">
                            <i class="fas fa-envelope"></i>
                        <a href="notifications.php" class="icon-btn notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot"></span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content">
                <!-- Success/Error Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header card-elevated-lg" style="background: linear-gradient(135deg, rgba(212, 74, 108, 0.05) 0%, rgba(42, 157, 143, 0.05) 100%); border-radius: var(--radius-xl); padding: var(--space-8); margin-bottom: var(--space-6); position: relative; overflow: hidden; border: 1px solid var(--clr-border-theme);">
                    <div style="position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(212, 74, 108, 0.05) 0%, transparent 70%); pointer-events: none;"></div>
                    
                    <div style="display: flex; gap: var(--space-6); align-items: flex-start; position: relative; z-index: 1; flex-wrap: wrap;">
                        <!-- Avatar Section -->
                        <div class="profile-avatar-section" style="position: relative;">
                            <div class="profile-avatar" style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; font-weight: 700; box-shadow: 0 8px 20px rgba(212, 74, 108, 0.25); position: relative;">
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <?php echo strtoupper(substr(htmlspecialchars($user['username']), 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($is_own_profile): ?>
                                <div class="avatar-overlay" style="position: absolute; inset: 0; border-radius: 50%; background: rgba(0, 0, 0, 0); display: flex; align-items: center; justify-content: center; transition: background 0.2s; cursor: pointer;" onmouseover="this.style.background='rgba(0, 0, 0, 0.3)'" onmouseout="this.style.background='rgba(0, 0, 0, 0)'">
                                    <label for="profile-pic-upload" style="cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-camera" style="color: white; font-size: 18px;"></i>
                                    </label>
                                </div>
                            </div>
                            <form action="profile.php" method="post" enctype="multipart/form-data" class="avatar-upload-form" style="display: none;">
                                <input type="file" name="profile_pic" id="profile-pic-upload" accept="image/*" hidden onchange="this.parentElement.submit();">
                            </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- User Info Section -->
                        <div class="profile-info" style="flex: 1; min-width: 250px;">
                            <h2 style="font-family: var(--font-heading); font-size: var(--text-3xl); font-weight: 700; color: var(--clr-text-primary); margin-bottom: var(--space-3);"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p style="display: flex; align-items: center; gap: var(--space-2); color: var(--clr-text-secondary); margin-bottom: var(--space-2); font-size: var(--text-base);">
                                <i class="fas fa-envelope" style="color: var(--clr-primary);"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p style="display: flex; align-items: center; gap: var(--space-2); color: var(--clr-text-secondary); font-size: var(--text-sm);">
                                <i class="fas fa-calendar-alt" style="color: var(--clr-secondary);"></i>
                                Member since <?php echo date("F j, Y", strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-8);">
                    <div class="stat-card card-elevated-md" style="background: linear-gradient(135deg, rgba(212, 74, 108, 0.05) 0%, rgba(212, 74, 108, 0.02) 100%); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); padding: var(--space-5); display: flex; align-items: center; gap: var(--space-4); transition: all 0.2s ease;">
                        <div style="width: 50px; height: 50px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--clr-primary), #c73a60); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: var(--text-2xl); font-weight: 700; background: linear-gradient(135deg, var(--clr-primary), #c73a60); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo $stats['post_count'] ?? 0; ?></h3>
                            <p style="font-size: var(--text-sm); color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Problems Posted</p>
                        </div>
                    </div>
                    
                    <div class="stat-card card-elevated-md" style="background: linear-gradient(135deg, rgba(42, 157, 143, 0.05) 0%, rgba(42, 157, 143, 0.02) 100%); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); padding: var(--space-5); display: flex; align-items: center; gap: var(--space-4); transition: all 0.2s ease;">
                        <div style="width: 50px; height: 50px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--clr-secondary), #238577); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: var(--text-2xl); font-weight: 700; background: linear-gradient(135deg, var(--clr-secondary), #238577); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo $stats['solution_count'] ?? 0; ?></h3>
                            <p style="font-size: var(--text-sm); color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Solutions Provided</p>
                        </div>
                    </div>
                    
                    <div class="stat-card card-elevated-md" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); padding: var(--space-5); display: flex; align-items: center; gap: var(--space-4); transition: all 0.2s ease;">
                        <div style="width: 50px; height: 50px; border-radius: var(--radius-lg); background: linear-gradient(135deg, #ef4444, #dc2626); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: var(--text-2xl); font-weight: 700; background: linear-gradient(135deg, #ef4444, #dc2626); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo $stats['total_likes'] ?? 0; ?></h3>
                            <p style="font-size: var(--text-sm); color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Total Likes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card card-elevated-md" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.05) 0%, rgba(168, 85, 247, 0.02) 100%); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); padding: var(--space-5); display: flex; align-items: center; gap: var(--space-4); transition: all 0.2s ease;">
                        <div style="width: 50px; height: 50px; border-radius: var(--radius-lg); background: linear-gradient(135deg, #a855f7, #9333ea); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: var(--text-2xl); font-weight: 700; background: linear-gradient(135deg, #a855f7, #9333ea); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo ($stats['post_count'] ?? 0) + ($stats['solution_count'] ?? 0); ?></h3>
                            <p style="font-size: var(--text-sm); color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Total Engagement</p>
                        </div>
                    </div>
                </div>

                <!-- User Posts Section -->
                <div class="posts-section" style="margin-bottom: var(--space-6);">
                    <div class="section-header" style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4); padding-bottom: var(--space-4); border-bottom: 2px solid var(--clr-border-theme); flex-wrap: wrap;">
                        <div style="display: flex; gap: var(--space-3); align-items: center;">
                            <a href="profile.php?user_id=<?php echo $profile_user_id; ?>&tab=posts" style="text-decoration: none; <?php echo $active_tab === 'posts' ? 'color: var(--clr-primary); font-weight: 700; border-bottom: 2px solid var(--clr-primary); padding-bottom: 8px;' : 'color: var(--clr-text-secondary);'; ?>">
                                <i class="fas fa-file-alt"></i> Posts (<?php echo $result_posts->num_rows; ?>)
                            </a>
                            <?php if ($is_own_profile): ?>
                            <a href="profile.php?tab=saved" style="text-decoration: none; <?php echo $active_tab === 'saved' ? 'color: var(--clr-primary); font-weight: 700; border-bottom: 2px solid var(--clr-primary); padding-bottom: 8px;' : 'color: var(--clr-text-secondary);'; ?>">
                                <i class="fas fa-bookmark"></i> Saved (<?php echo $saved_count; ?>)
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($active_tab === 'saved'): ?>
                        <?php if ($saved_count > 0): ?>
                            <div class="posts-grid">
                                <?php $saved_posts->data_seek(0); while ($post = $saved_posts->fetch_assoc()): ?>
                                    <div class="post-card card-elevated-md animate-in" data-problem-id="<?php echo $post['id']; ?>">
                                        <div class="post-header">
                                            <div class="post-avatar" style="overflow:hidden;">
                                                <?php echo strtoupper(substr(htmlspecialchars($post['username']), 0, 1)); ?>
                                            </div>
                                            <div class="post-meta">
                                                <div class="post-author"><?php echo $post['anonymous'] ? 'Anonymous' : htmlspecialchars($post['username']); ?></div>
                                                <div class="post-time">
                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div style="margin-left: auto; display: flex; gap: 8px;">
                                                <button class="post-action bookmark-btn" onclick="toggleBookmark(<?php echo $post['id']; ?>)" title="Remove from saved">
                                                    <i class="fas fa-bookmark"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                            <p class="post-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
                                        </div>
                                        <div class="post-footer">
                                            <div class="stat" style="display:flex;align-items:center;gap:6px;color:var(--clr-text-secondary);font-size:14px;">
                                                <i class="fas fa-heart" style="color:#ef4444;"></i>
                                                <span><?php echo $post['like_count'] ?? 0; ?> Likes</span>
                                            </div>
                                            <a href="view_problem.php?problem_id=<?php echo $post['id']; ?>" class="post-action">
                                                <i class="fas fa-comments"></i> <span>View Solutions</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bookmark"></i>
                                <h3>No saved posts</h3>
                                <p>Click the bookmark icon on any post to save it for later!</p>
                                <a href="../index.php" class="btn-primary">
                                    <i class="fas fa-arrow-left"></i> Browse Posts
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($result_posts->num_rows > 0): ?>
                            <div class="posts-grid">
                                <?php while ($post = $result_posts->fetch_assoc()): ?>
                                    <div class="post-card card-elevated-md animate-in" data-problem-id="<?php echo $post['id']; ?>">
                                        <div class="post-header">
                                            <div class="post-avatar" style="overflow:hidden;">
                                                <?php if (!empty($user['profile_pic'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($user['profile_pic']); ?>"
                                                         alt="<?php echo htmlspecialchars($user['username']); ?>"
                                                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr(htmlspecialchars($user['username']), 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="post-meta">
                                                <div class="post-author"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="post-time">
                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div style="margin-left: auto; display: flex; gap: 8px; position:relative;">
                                                <button class="post-action post-options">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu" style="display:none;">
                                                    <a href="edit_post.php?post_id=<?php echo $post['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                                        <input type="hidden" name="delete_post" value="<?php echo $post['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <button type="submit" class="dropdown-item delete" style="width:100%;border:none;background:none;cursor:pointer;">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                            <p class="post-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
                                        </div>

                                        <div class="post-footer">
                                            <div class="stat" style="display:flex;align-items:center;gap:6px;color:var(--clr-text-secondary);font-size:14px;">
                                                <i class="fas fa-heart" style="color:#ef4444;"></i>
                                                <span><?php echo $post['like_count'] ?? 0; ?> Likes</span>
                                            </div>
                                            <a href="view_problem.php?problem_id=<?php echo $post['id']; ?>" class="post-action">
                                                <i class="fas fa-comments"></i> <span>View Solutions</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <h3>No problems posted yet</h3>
                                <p>Start sharing your problems to get help from the community!</p>
                                <a href="../index.php" class="btn-primary">
                                    <i class="fas fa-plus"></i> Create Your First Post
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($is_own_profile): ?>
                <!-- Account Management Section -->
                <div class="section account-management">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-user-cog"></i>
                            Account Management
                        </h3>
                    </div>
                    <div class="danger-zone">
                        <h4>Danger Zone</h4>
                        <p>Once you delete your account, all your data will be permanently removed after 3 hours.</p>
                        <div class="action-buttons">
                            <button id="deleteAccountBtn" class="btn-danger">
                                <i class="fas fa-user-times"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($is_own_profile): ?>
    <!-- Delete Account Confirmation Modal -->
    <div class="modal" id="deleteAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action will:</p>
                <ul>
                    <li>Schedule your account for deletion in 3 hours</li>
                    <li>Allow you to cancel the deletion within the 3-hour grace period</li>
                    <li>Permanently delete all your data after 3 hours</li>
                </ul>
                <div id="deletionStatus"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Cancel</button>
                <button class="btn-danger" id="confirmDeleteBtn">Yes, Delete My Account</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../js/mode.js"></script>
    <style>
        .danger-zone {
            background-color: var(--danger-bg);
            border: 1px solid var(--danger);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .danger-zone h4 {
            color: var(--danger);
            margin: 0 0 10px 0;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-danger:hover {
            background-color: var(--danger-hover);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background-color: var(--bg-color);
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-color);
        }
        
        .modal-body ul {
            margin: 20px 0;
            padding-left: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        #deletionStatus {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        #deletionStatus.success {
            background-color: var(--success-bg);
            color: var(--success);
        }
        
        #deletionStatus.error {
            background-color: var(--danger-bg);
            color: var(--danger);
        }

        .post-options {
            background: none;
            border: none;
            color: var(--clr-text-tertiary-theme);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .post-options:hover {
            background: var(--clr-bg-alt-theme);
            color: var(--clr-text-primary);
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 100;
            min-width: 160px;
            padding: 4px;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            color: var(--clr-text-primary);
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.15s;
        }
        .dropdown-item:hover {
            background: var(--clr-bg-alt-theme);
        }
        .dropdown-item.delete {
            color: #ef4444;
        }
    </style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Account deletion functionality
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    const deleteAccountModal = document.getElementById('deleteAccountModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deletionStatus = document.getElementById('deletionStatus');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    let deletionTimer = null;
    let scheduledDeletionTime = null;

    // Open modal
    if (deleteAccountBtn) {
        deleteAccountBtn.addEventListener('click', () => {
            deleteAccountModal.style.display = 'block';
        });
    }

    // Close modal
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            deleteAccountModal.style.display = 'none';
            deletionStatus.style.display = 'none';
        });
    });

    // Click outside modal to close
    window.addEventListener('click', (e) => {
        if (e.target === deleteAccountModal) {
            deleteAccountModal.style.display = 'none';
            deletionStatus.style.display = 'none';
        }
    });

    // Handle account deletion
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', () => {
            fetch('../api/schedule_account_deletion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=schedule'
            })
            .then(response => response.json())
            .then(data => {
                deletionStatus.style.display = 'block';
                if (data.success) {
                    deletionStatus.className = 'success';
                    scheduledDeletionTime = new Date(data.deletion_time);
                    updateDeletionStatus();
                    confirmDeleteBtn.style.display = 'none';
                    
                    // Add cancel button
                    const cancelBtn = document.createElement('button');
                    cancelBtn.className = 'btn-secondary';
                    cancelBtn.textContent = 'Cancel Deletion';
                    cancelBtn.addEventListener('click', cancelDeletion);
                    document.querySelector('.modal-footer').prepend(cancelBtn);
                } else {
                    deletionStatus.className = 'error';
                }
                deletionStatus.textContent = data.message;
            })
            .catch(error => {
                deletionStatus.style.display = 'block';
                deletionStatus.className = 'error';
                deletionStatus.textContent = 'Error scheduling account deletion';
            });
        });
    }

    function updateDeletionStatus() {
        if (!scheduledDeletionTime) return;
        
        const now = new Date();
        const timeLeft = scheduledDeletionTime - now;
        
        if (timeLeft <= 0) {
            deletionStatus.textContent = 'Account deletion in progress...';
            window.location.href = '../auth/logout.php';
            return;
        }
        
        const hours = Math.floor(timeLeft / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        deletionStatus.textContent = `Account will be deleted in ${hours}h ${minutes}m. You can cancel until then.`;
        
        deletionTimer = setTimeout(updateDeletionStatus, 60000); // Update every minute
    }

    function cancelDeletion() {
        fetch('../api/schedule_account_deletion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=cancel'
        })
        .then(response => response.json())
        .then(data => {
            deletionStatus.style.display = 'block';
            if (data.success) {
                deletionStatus.className = 'success';
                clearTimeout(deletionTimer);
                scheduledDeletionTime = null;
                setTimeout(() => {
                    deleteAccountModal.style.display = 'none';
                    location.reload();
                }, 2000);
            } else {
                deletionStatus.className = 'error';
            }
            deletionStatus.textContent = data.message;
        })
        .catch(error => {
            deletionStatus.style.display = 'block';
            deletionStatus.className = 'error';
            deletionStatus.textContent = 'Error cancelling account deletion';
        });
    }

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
    
    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Profile picture upload
    const profilePicUpload = document.getElementById('profile-pic-upload');
    const uploadSubmit = document.getElementById('upload-submit');
    const avatarImage = document.querySelector('.avatar-image');
    const avatarPlaceholder = document.querySelector('.avatar-placeholder');

    if (profilePicUpload) {
        profilePicUpload.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (avatarImage) {
                        avatarImage.src = e.target.result;
                    } else if (avatarPlaceholder) {
                        // Replace placeholder with actual image
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = 'Profile Picture';
                        newImg.className = 'avatar-image';
                        avatarPlaceholder.parentNode.replaceChild(newImg, avatarPlaceholder);
                    }
                }
                
                reader.readAsDataURL(this.files[0]);
                if (uploadSubmit) uploadSubmit.style.display = 'inline-block';
            }
        });
    }

    // Dropdown menu functionality
    document.querySelectorAll('.post-options').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.nextElementSibling;
            const isVisible = dropdown.style.display === 'block';
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            // Toggle current dropdown
            dropdown.style.display = isVisible ? 'none' : 'block';
        });
    });

    // Close dropdowns when clicking elsewhere
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    });

    // Confirm delete actions
    document.querySelectorAll('.delete').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>