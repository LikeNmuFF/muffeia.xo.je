<?php
session_start();

include 'includes/db.php';
include 'includes/categories_tags.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$category_feature_available = categoryFeatureAvailable($conn);
$categories = $category_feature_available ? getCategories($conn) : [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle AJAX requests for likes
    if (isset($_POST['action']) && $_POST['action'] == 'like') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit();
        }
        
        $problem_id = intval($_POST['problem_id']);
        $user_id = $_SESSION['user_id'];
        
        // Check if user already liked this post
        $check_sql = "SELECT * FROM post_likes WHERE problem_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $problem_id, $user_id);
        $check_stmt->execute();
        $existing_like = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing_like) {
            // Unlike the post
            $delete_sql = "DELETE FROM post_likes WHERE problem_id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $problem_id, $user_id);
            $delete_stmt->execute();
            $liked = false;
        } else {
            // Like the post
            $insert_sql = "INSERT INTO post_likes (problem_id, user_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $problem_id, $user_id);
            $insert_stmt->execute();
            $liked = true;
        }
        
        // Get updated like count
        $count_sql = "SELECT COUNT(*) as like_count FROM post_likes WHERE problem_id = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("i", $problem_id);
        $count_stmt->execute();
        $like_count = $count_stmt->get_result()->fetch_assoc()['like_count'];
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => $like_count
        ]);
        exit();
    }
    
    // Handle regular post submission
    $title = $_POST['title'];
    $description = $_POST['description'];

    echo '<script>alert("Post Publish Successfully!!");</script>';
    echo '<script>"index.php";</script>';
}

// Get the latest post timestamp for initial load
$latest_post_sql = "SELECT MAX(created_at) as latest FROM problems";
$latest_result = $conn->query($latest_post_sql);
$latest_post_time = $latest_result->fetch_assoc()['latest'] ?? '';

// Pagination variables
$limit = 5; // Number of posts per load
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch problems with like counts and user like status
$category_select = $category_feature_available
    ? "p.category_id, c.name as category_name, c.slug as category_slug, c.description as category_description, c.icon as category_icon,"
    : "NULL as category_id, NULL as category_name, NULL as category_slug, NULL as category_description, NULL as category_icon,";
$category_join = $category_feature_available ? "LEFT JOIN categories c ON p.category_id = c.id" : "";

$sql = "SELECT p.id, p.title, p.description, p.user_id, $category_select u.username, u.profile_pic, p.anonymous, p.created_at,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id AND user_id = ?) as user_liked
        FROM problems p 
        JOIN users u ON p.user_id = u.id 
        $category_join
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// Get total count of posts for infinite scroll
$count_sql = "SELECT COUNT(*) as total FROM problems";
$count_result = $conn->query($count_sql);
$total_posts = $count_result->fetch_assoc()['total'];
$has_more = ($offset + $limit) < $total_posts;
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
<link rel="stylesheet" href="css/muffeia-ui.css">
<link rel="icon" href="/logo/m-blues.png" type="image/png">

<meta name="description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta name="keywords" content="Muffeia, social support, community help, share problems, find solutions, anonymous support">
<link rel="canonical" href="https://muffeia.xo.je/">

<?php
$safe_host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8');
$safe_uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$base_url = $protocol . '://' . $safe_host;
$full_url = $base_url . $safe_uri;
?>
<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo $full_url; ?>">
<meta property="og:title" content="MUFFEIA — Platform to solve and share problems">
<meta property="og:description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta property="og:image" content="<?php echo $base_url; ?>/logo/muffeia.png">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo $full_url; ?>">
<meta property="twitter:title" content="MUFFEIA — Platform to solve and share problems">
<meta property="twitter:description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta property="twitter:image" content="<?php echo $base_url; ?>/logo/muffeia.png">
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

<title>MUFFEIA — Home</title>

</head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                    <img src="logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
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
                <a href="index" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="pages/profile" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="pages/message" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge" id="messageBadge" style="display: none;">0</span>
                </a>
                <a href="pages/group-room.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Group Chats</span>
                    <span class="badge" id="groupChatBadge" style="display: none;">0</span>
                </a>
                <a href="pages/notifications" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <span class="badge" id="notificationBadge" style="display: none;">0</span>
                </a>

                <a href="pages/settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="auth/logout" class="nav-item logout">
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
                    <h1>Welcome to Muffeia!</h1>
                    <div class="user-actions">
                        <a href="pages/message.php" class="icon-btn message-btn" role="button">
                            <i class="fas fa-envelope"></i>
                        <a href="pages/notifications.php" class="icon-btn notification-btn" role="button">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot" style="display: none;"></span>
                        </a>
                        <a href="pages/search.php" class="icon-btn" title="Search">
                            <i class="fas fa-search"></i>
                        </a>
                    </div>
                </div>
            </header>

          
            <div class="content">
                <?php if (isset($_SESSION['post_error'])): ?>
                    <div class="error-message" style="margin-bottom:16px;">
                        <?php echo htmlspecialchars($_SESSION['post_error']); unset($_SESSION['post_error']); ?>
                    </div>
                <?php elseif (isset($_SESSION['post_success'])): ?>
                    <div class="success-message" style="margin-bottom:16px;">
                        <?php echo htmlspecialchars($_SESSION['post_success']); unset($_SESSION['post_success']); ?>
                    </div>
                <?php endif; ?>
                <div class="content-header">
                    <div>
                        <h2>Latest Problems</h2>
                        <p class="subtitle">Platform to solve and share problems</p>
                    </div>
                    <button class="btn-primary mobile-create-btn" id="mobileCreateBtn">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                <!-- Post Area -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="post-area card-elevated-lg" id="postArea" style="background: linear-gradient(135deg, rgba(212, 74, 108, 0.03) 0%, rgba(42, 157, 143, 0.03) 100%); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-6);">
                    <div class="post-area-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); padding-bottom: var(--space-4); border-bottom: 2px solid var(--clr-border-theme);">
                        <h3 style="font-family: var(--font-heading); font-size: var(--text-xl); font-weight: 700; color: var(--clr-text-primary); display: flex; gap: var(--space-2); align-items: center;">
                            <i class="fas fa-lightbulb" style="color: var(--clr-primary);"></i> Share Your Problem
                        </h3>
                        <button class="close-post-area" id="closePostArea" style="background: none; border: none; cursor: pointer; font-size: var(--text-lg); color: var(--clr-text-tertiary); transition: color 0.2s;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form action="post_problem.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-heading" style="margin-right: 6px; color: var(--clr-primary);"></i>Title
                            </label>
                            <input type="text" name="title" id="title" class="form-input" placeholder="What's your problem?" required data-badword-action="delete">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left" style="margin-right: 6px; color: var(--clr-primary);"></i>Description
                            </label>
                            <textarea name="description" id="description" class="form-textarea" placeholder="Describe your problem in detail..." required data-badword-action="delete"></textarea>
                        </div>

                        <?php if (!empty($categories)): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-folder" style="margin-right: 6px; color: var(--clr-primary);"></i>Category
                            </label>
                            <select name="category_id" class="form-input">
                                <option value="">Choose a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($categories)): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tags" style="margin-right: 6px; color: var(--clr-primary);"></i>Tags
                            </label>
                            <input type="text" name="tags" class="form-input" placeholder="Example: school, advice, stress">
                        </div>
                        <?php endif; ?>

                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-5);">
                            <input type="checkbox" name="anonymous" id="anonymousCheckbox" value="1" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--clr-primary);">
                            <label for="anonymousCheckbox" style="cursor: pointer; font-weight: 500; color: var(--clr-text-secondary);">
                                Post Anonymously
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: var(--space-2); padding: var(--space-3) var(--space-6);">
                            <i class="fas fa-paper-plane"></i> Share My Problem
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Display Problems -->
                <div class="post-container" id="post-container">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                             <div class="post-card card-elevated-md animate-in" data-problem-id="<?php echo $row['id']; ?>">
                                 <div class="post-header">
                                     <div class="post-avatar" style="overflow: hidden;">
                                          <?php if (!$row['anonymous'] && !empty($row['profile_pic'])): ?>
                                              <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>"
                                                   alt="<?php echo htmlspecialchars($row['username']); ?>"
                                                   style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;"
                                                   onerror="this.style.display='none'; this.parentNode.querySelector('.post-avatar-fallback').style.display='flex';">
                                              <div class="post-avatar-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--clr-primary),var(--clr-secondary));color:#fff;font-weight:600;font-size:14px;border-radius:50%;">
                                                  <?php echo strtoupper(substr(htmlspecialchars($row['username']), 0, 1)); ?>
                                              </div>
                                          <?php else: ?>
                                              <?php
                                              $initials = $row['anonymous'] ? 'A' : strtoupper(substr(htmlspecialchars($row['username']), 0, 1));
                                              ?>
                                              <?php echo $initials; ?>
                                          <?php endif; ?>
                                      </div>
                                     <div class="post-meta">
                                         <div class="post-author"><?php if ($row['anonymous']): ?>Anonymous<?php else: ?><a href="pages/profile.php?user_id=<?php echo $row['user_id']; ?>" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($row['username']); ?></a><?php endif; ?></div>
                                         <div class="post-time">
                                             <?php echo date('M j, Y \a\t g:i A', strtotime($row['created_at'])); ?>
                                         </div>
                                     </div>
                                      <div style="margin-left: auto; display: flex; gap: 8px;">
                                          <?php if (isset($_SESSION['user_id']) && $row['user_id'] == $_SESSION['user_id']): ?>
                                          <a href="pages/edit_post.php?post_id=<?php echo $row['id']; ?>" class="post-action" title="Edit post">
                                              <i class="fas fa-edit"></i>
                                          </a>
                                          <button class="post-action delete-post" onclick="deletePost(<?php echo $row['id']; ?>)" title="Delete post">
                                              <i class="fas fa-trash-alt"></i>
                                          </button>
                                          <?php endif; ?>
                                           <button class="post-action" onclick="reportPost(<?php echo $row['id']; ?>, '<?php echo $_SESSION['csrf_token']; ?>')" title="Report post">
                                               <i class="fas fa-flag"></i>
                                           </button>
                                           <button class="post-action bookmark-btn" onclick="toggleBookmark(<?php echo $row['id']; ?>)" title="Save post">
                                               <i class="far fa-bookmark"></i>
                                           </button>
                                           <button class="post-action" onclick="openPostMenu(<?php echo $row['id']; ?>)">
                                              <i class="fas fa-ellipsis-h"></i>
                                          </button>
                                      </div>
                                 </div>
                                 
                                  <div>
                                      <h3 class="post-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                                      <p class="post-description"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                  </div>

                                 <div class="post-footer">
                                     <a href="/pages/view_problem.php?problem_id=<?php echo $row['id']; ?>" class="post-action">
                                         <i class="fas fa-comments"></i> 
                                         <span>View Solutions</span>
                                     </a>
                                     <button class="post-action like-btn <?php echo $row['user_liked'] ? 'liked' : ''; ?>" 
                                             data-problem-id="<?php echo $row['id']; ?>" 
                                             title="Like">
                                         <i class="<?php echo $row['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                         <span class="like-count"><?php echo $row['like_count']; ?></span>
                                     </button>
                                      <button class="post-action share-btn" data-problem-id="<?php echo $row['id']; ?>" data-title="<?php echo htmlspecialchars($row['title']); ?>" data-description="<?php echo htmlspecialchars(substr($row['description'], 0, 500)); ?>" title="Share">
                                          <i class="fas fa-share-alt"></i>
                                          <span>Share</span>
                                      </button>
                                 </div>
                             </div>
                         <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No problems posted yet</h3>
                            <p>Be the first to share a problem!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Loading indicator -->
                <div class="loading" id="loading">
                    <div class="loading-spinner"></div>
                    <p>Loading more posts...</p>
                </div>
                
                <!-- No more posts indicator -->
                <div class="no-more-posts" id="no-more-posts">
                    <i class="fas fa-check-circle"></i>
                    <p>You're all caught up!</p>
                </div>
                
                <!-- New posts notification -->
                <div class="new-posts-notification" id="new-posts-notification">
                    <i class="fas fa-arrow-up"></i>
                    New posts available! Click to load.
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal-overlay" id="shareModalOverlay">
        <div class="modal card-elevated-lg" id="shareModal">
            <div class="modal-header">
                <h2>Share Post</h2>
                <button class="modal-close" id="shareModalClose" style="background: none; border: none; cursor: pointer; font-size: var(--text-lg); color: var(--clr-text-tertiary);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="share-options" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-4); margin-bottom: var(--space-6);">
                    <button class="share-option btn-ghost" style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-2);" data-method="copy">
                        <i class="fas fa-copy" style="font-size: 24px;"></i>
                        <span style="font-weight: 500;">Copy Link</span>
                    </button>
                    <button class="share-option btn-ghost" style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-2);" data-method="facebook">
                        <i class="fab fa-facebook" style="font-size: 24px; color: #1877F2;"></i>
                        <span style="font-weight: 500;">Facebook</span>
                    </button>
                    <button class="share-option btn-ghost" style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-2);" data-method="twitter">
                        <i class="fab fa-twitter" style="font-size: 24px; color: #1DA1F2;"></i>
                        <span style="font-weight: 500;">Twitter</span>
                    </button>
                    <button class="share-option btn-ghost" style="height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-2);" data-method="whatsapp">
                        <i class="fab fa-whatsapp" style="font-size: 24px; color: #25D366;"></i>
                        <span style="font-weight: 500;">WhatsApp</span>
                    </button>
                </div>
                <div style="display: flex; gap: var(--space-2); align-items: stretch;">
                    <input type="text" id="shareUrl" class="form-input" style="flex: 1;" readonly>
                    <button class="btn btn-secondary" id="copyUrlBtn" style="padding: var(--space-3) var(--space-4); white-space: nowrap;">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>



<script src="js/badword-filter.js"></script>
<script src="js/mode.js"></script>
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
        
        // Initialize counts
        updateMessageCount();
        updateNotificationCount();
        
        // Update counts every 30 seconds
        setInterval(updateMessageCount, 30000);
        setInterval(updateNotificationCount, 30000);
        setInterval(updateGroupChatCount, 30000);
        
        // Update counts when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateMessageCount();
                updateNotificationCount();
                updateGroupChatCount();
            }
        });
        
        // Mobile create post toggle
        const mobileCreateBtn = document.getElementById('mobileCreateBtn');
        const postArea = document.getElementById('postArea');
        const closePostArea = document.getElementById('closePostArea');
        const body = document.body;

        if (mobileCreateBtn && postArea) {
            mobileCreateBtn.addEventListener('click', function() {
                postArea.classList.add('active');
                body.classList.add('post-area-open');
                document.body.style.overflow = 'hidden';
            });
            
            closePostArea.addEventListener('click', function() {
                postArea.classList.remove('active');
                body.classList.remove('post-area-open');
                document.body.style.overflow = '';
            });
            
            postArea.addEventListener('click', function(e) {
                if (e.target === postArea) {
                    closePostArea.click();
                }
            });
        }

        // Like functionality
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function() {
                const problemId = this.dataset.problemId;
                const likeIcon = this.querySelector('i');
                const likeCount = this.querySelector('.like-count');
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=like&problem_id=${problemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update like count
                        likeCount.textContent = data.like_count;
                        
                        // Update icon and style
                        if (data.liked) {
                            likeIcon.classList.replace('far', 'fas');
                            this.classList.add('liked');
                        } else {
                            likeIcon.classList.replace('fas', 'far');
                            this.classList.remove('liked');
                        }
                        
                        // Add animation
                        this.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            this.style.transform = 'scale(1)';
                        }, 200);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });

        // Share functionality
        const shareModalOverlay = document.getElementById('shareModalOverlay');
        const shareModal = document.getElementById('shareModal');
        const shareModalClose = document.getElementById('shareModalClose');
        const shareUrlInput = document.getElementById('shareUrl');
        const copyUrlBtn = document.getElementById('copyUrlBtn');
        let currentProblemId = null;
        let currentProblemTitle = '';
        let currentProblemDesc = '';

        function getShareUrl(id) {
            return `${window.location.origin}/pages/view_problem.php?problem_id=${id}`;
        }

        document.querySelectorAll('.share-btn').forEach(button => {
            button.addEventListener('click', function() {
                currentProblemId = this.dataset.problemId;
                currentProblemTitle = this.dataset.title || '';
                currentProblemDesc = this.dataset.description || '';
                shareUrlInput.value = getShareUrl(currentProblemId);
                shareModalOverlay.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        // Close share modal
        function closeShareModal() {
            shareModalOverlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        shareModalClose.addEventListener('click', closeShareModal);
        shareModalOverlay.addEventListener('click', function(e) {
            if (e.target === shareModalOverlay) {
                closeShareModal();
            }
        });

        // Copy URL functionality
        copyUrlBtn.addEventListener('click', function() {
            shareUrlInput.select();
            document.execCommand('copy');
            
            // Visual feedback
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i>';
            this.style.background = 'var(--success)';
            
            setTimeout(() => {
                this.innerHTML = originalHtml;
                this.style.background = '';
            }, 2000);
        });

        // Social media sharing
        document.querySelectorAll('.share-option').forEach(option => {
            option.addEventListener('click', function() {
                const method = this.dataset.method;
                const url = shareUrlInput.value;
                const title = currentProblemTitle || 'Check out this problem on MUFFEIA';
                const desc = currentProblemDesc || '';
                const encodedUrl = encodeURIComponent(url);
                const encodedTitle = encodeURIComponent(title);
                const encodedDesc = encodeURIComponent(desc);
                
                // Use Web Share API on mobile (native share sheet includes Facebook, Messenger, etc.)
                if (navigator.share && /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    navigator.share({
                        title: title,
                        text: title + '\n\n' + desc,
                        url: url
                    }).catch(() => {});
                    return;
                }
                
                let shareWindow = '';
                
                switch(method) {
                    case 'facebook':
                        const caption = encodedDesc ? encodedTitle + '%0A%0A' + encodedDesc : encodedTitle;
                        shareWindow = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${caption}`;
                        break;
                    case 'twitter':
                        shareWindow = `https://twitter.com/intent/tweet?text=${encodedTitle}&url=${encodedUrl}`;
                        break;
                    case 'whatsapp':
                        shareWindow = `https://wa.me/?text=${encodedTitle}%0A%0A${encodedUrl}`;
                        break;
                    case 'copy':
                        return;
                    case 'copytext':
                }
                
                if (shareWindow) {
                    window.open(shareWindow, '_blank', 'width=600,height=400');
                }
            });
        });

        // Update message count function
        function updateMessageCount() {
            fetch('api/get_message_count.php')
                .then(response => response.json())
                .then(data => {
                    const messageBadge = document.getElementById('messageBadge');
                    const sidebarMessageBadge = document.querySelector('.nav-item[href="pages/message.php"] .badge');
                    
                    if (data.count > 0) {
                        if (messageBadge) messageBadge.textContent = data.count;
                        if (sidebarMessageBadge) sidebarMessageBadge.textContent = data.count;
                        
                        // Show badges
                        if (messageBadge) messageBadge.style.display = 'inline';
                        if (sidebarMessageBadge) sidebarMessageBadge.style.display = 'inline';
                    } else {
                        // Hide badges
                        if (messageBadge) messageBadge.style.display = 'none';
                        if (sidebarMessageBadge) sidebarMessageBadge.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching message count:', error);
                });
        }

        // Update notification count function
        function updateNotificationCount() {
            fetch('api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const notificationBadge = document.getElementById('notificationBadge');
                    const sidebarNotificationBadge = document.querySelector('.nav-item[href="pages/notifications.php"] .badge');
                    const topNavNotificationDot = document.querySelector('.notification-btn .notification-dot');
                    
                    if (data.count > 0) {
                        if (notificationBadge) notificationBadge.textContent = data.count;
                        if (sidebarNotificationBadge) sidebarNotificationBadge.textContent = data.count;
                        
                        // Show badges and dot
                        if (notificationBadge) notificationBadge.style.display = 'inline';
                        if (sidebarNotificationBadge) sidebarNotificationBadge.style.display = 'inline';
                        if (topNavNotificationDot) topNavNotificationDot.style.display = 'block';
                    } else {
                        // Hide badges and dot
                        if (notificationBadge) notificationBadge.style.display = 'none';
                        if (sidebarNotificationBadge) sidebarNotificationBadge.style.display = 'none';
                        if (topNavNotificationDot) topNavNotificationDot.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching notification count:', error);
                });
        }

        // Update group chat count function
        function updateGroupChatCount() {
            fetch('api/group-room.php?action=get_group_message_count')
                .then(response => response.json())
                .then(data => {
                    const groupChatBadge = document.getElementById('groupChatBadge');
                    const sidebarGroupChatBadge = document.querySelector('.nav-item[href="pages/group-room.php"] .badge');
                    
                    if (data.count > 0) {
                        if (groupChatBadge) groupChatBadge.textContent = data.count;
                        if (sidebarGroupChatBadge) sidebarGroupChatBadge.textContent = data.count;
                        
                        // Show badges
                        if (groupChatBadge) groupChatBadge.style.display = 'inline';
                        if (sidebarGroupChatBadge) sidebarGroupChatBadge.style.display = 'inline';
                    } else {
                        // Hide badges
                        if (groupChatBadge) groupChatBadge.style.display = 'none';
                        if (sidebarGroupChatBadge) sidebarGroupChatBadge.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching group chat count:', error);
                });
        }

        // Infinite scroll and post loading
        let page = <?php echo json_encode((int)$page); ?>;
        let loading = false;
        let hasMore = <?php echo json_encode((bool)$has_more); ?>;
        let lastPostTime = <?php echo json_encode($latest_post_time); ?>;
        let checkingForNewPosts = false;
        let newPostsAvailable = false;
        
        if (!hasMore && page > 1) {
            document.getElementById('no-more-posts').style.display = 'flex';
        }
        
        window.addEventListener('scroll', function() {
            if (loading || !hasMore) return;
            
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
                loadMorePosts();
            }
        });
        
        function loadMorePosts() {
            if (loading) return;
            
            loading = true;
            document.getElementById('loading').style.display = 'flex';
            
            page++;
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `api/load_posts.php?page=${page}`, true);
            
            xhr.onload = function() {
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    
                    if (response.posts) {
                        document.getElementById('post-container').insertAdjacentHTML('beforeend', response.posts);
                        
                        // Re-attach event listeners to new posts
                        attachEventListenersToNewPosts();
                        
                        if (response.latest_post_time) {
                            lastPostTime = response.latest_post_time;
                        }
                    }
                    
                    hasMore = response.hasMore;
                    
                    if (!hasMore) {
                        document.getElementById('no-more-posts').style.display = 'flex';
                    }
                }
                
                loading = false;
                document.getElementById('loading').style.display = 'none';
            };
            
            xhr.send();
        }
        
        function attachEventListenersToNewPosts() {
            // Re-attach like event listeners
        document.querySelectorAll('.like-btn').forEach(button => {
                if (!button.hasAttribute('data-listener-attached')) {
                    button.setAttribute('data-listener-attached', 'true');
                    button.addEventListener('click', function() {
                        const problemId = this.dataset.problemId;
                        const likeIcon = this.querySelector('i');
                        const likeCount = this.querySelector('.like-count');
                        
                        fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=like&problem_id=${problemId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                likeCount.textContent = data.like_count;
                                if (data.liked) {
                                    likeIcon.classList.replace('far', 'fas');
                                    this.classList.add('liked');
                                } else {
                                    likeIcon.classList.replace('fas', 'far');
                                    this.classList.remove('liked');
                                }
                                this.style.transform = 'scale(1.2)';
                                setTimeout(() => {
                                    this.style.transform = 'scale(1)';
                                }, 200);
                            }
                        });
                    });
                }
            });

            // Re-attach share event listeners
            document.querySelectorAll('.share-btn').forEach(button => {
                if (!button.hasAttribute('data-listener-attached')) {
                    button.setAttribute('data-listener-attached', 'true');
                    button.addEventListener('click', function() {
                        currentProblemId = this.dataset.problemId;
                        currentProblemTitle = this.dataset.title || '';
                        currentProblemDesc = this.dataset.description || '';
                        shareUrlInput.value = getShareUrl(currentProblemId);
                        shareModalOverlay.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    });
                }
            });
        }
        
        function checkForNewPosts() {
            if (checkingForNewPosts) return;
            
            checkingForNewPosts = true;
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `api/check_new_posts.php?last_post_time=${lastPostTime}`, true);
            
            xhr.onload = function() {
                checkingForNewPosts = false;
                
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    
                    if (response.new_posts) {
                        newPostsAvailable = true;
                        document.getElementById('new-posts-notification').style.display = 'flex';
                    }
                }
            };
            
            xhr.send();
        }

        function checkForNewGroupMessages() {
            // Since group messages don't have a timestamp-based notification system yet,
            // we'll just rely on the polling in group-room.js for real-time updates
            // This function exists for consistency with the post checking system
        }
        
        function loadNewPosts() {
            if (loading) return;
            
            loading = true;
            document.getElementById('new-posts-notification').style.display = 'none';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `api/load_new_posts.php?last_post_time=${lastPostTime}`, true);
            
            xhr.onload = function() {
                loading = false;
                
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    
                    if (response.posts) {
                        document.getElementById('post-container').insertAdjacentHTML('afterbegin', response.posts);
                        attachEventListenersToNewPosts();
                        
                        if (response.latest_post_time) {
                            lastPostTime = response.latest_post_time;
                        }
                        
                        newPostsAvailable = false;
                    }
                }
            };
            
            xhr.send();
        }
        
        setInterval(checkForNewPosts, 30000);
        setInterval(checkForNewGroupMessages, 30000);
        
        document.getElementById('new-posts-notification').addEventListener('click', function() {
            loadNewPosts();
        });
        
        window.addEventListener('focus', function() {
            checkForNewPosts();
            checkForNewGroupMessages();
        });
    });
</script>
<script src="js/post-actions.js"></script>
<script src="js/report-post.js"></script>
</body>
</html>
