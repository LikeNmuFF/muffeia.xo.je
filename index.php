
<?php
session_start();

include 'includes/db.php';
include 'includes/moderation.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

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
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (function_exists('moderate_text')) {
        foreach (['title' => $title, 'description' => $description] as $field => $value) {
            if ($value === '') continue;
            $mod = moderate_text($value);
            if (!empty($mod['flagged'])) {
                $_SESSION['post_error'] = sprintf(
                    'Your %s contains language we do not allow. Please revise and try again.',
                    $field
                );
                header("Location: index.php");
                exit();
            }
        }
    }

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
$sql = "SELECT p.id, p.title, p.description, p.user_id, u.username, u.profile_pic, p.anonymous, p.created_at,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
        (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id AND user_id = ?) as user_liked
        FROM problems p 
        JOIN users u ON p.user_id = u.id 
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/modern-theme.css">
<link rel="icon" href="logo/m-blues.png" type="image/png">

<title>MUFFEIA - Home</title>

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
                <!-- Dark mode logo -->
                <img src="logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
                    <span>MUFFEIA</span>
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
                <span class="theme-label">Light/Dark Mode</span>
            </div>
            
            <div class="nav-items">
                <a href="index.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="pages/profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="pages/message.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="badge" id="messageBadge" style="display: none;">0</span>
                </a>
                <a href="pages/notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <span class="badge" id="notificationBadge" style="display: none;">0</span>
                </a>
                <a href="pages/chatbot.php" class="nav-item">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
                </a>

                <a href="auth/logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span> </a>
                
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
                    <button class="btn-primary mobile-create-btn" id="mobileCreateBtn" style="display: none;">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                <!-- Post Area -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="post-area" id="postArea">
                    <div class="post-area-header">
                        <h3><i class="fas fa-edit"></i> Create a New Post</h3>
                        <button class="close-post-area" id="closePostArea">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form action="post_problem.php" method="POST">
                        <div class="form-group">
                            <label for="title"><i class="fas fa-heading"></i> Title</label>
                            <input type="text" name="title" id="title" placeholder="Enter your problem title" required data-badword-action="delete">
                        </div>

                        <div class="form-group">
                            <label for="description"><i class="fas fa-align-left"></i> Description</label>
                            <textarea name="description" id="description" rows="5" placeholder="Describe your problem in detail" required data-badword-action="delete"></textarea>
                        </div>

                        <label class="checkbox-container">
                            <input type="checkbox" name="anonymous" value="1">
                            <span class="checkmark"></span>
                            Post Anonymously
                        </label>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Post Problem
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Display Problems -->
                <div class="post-container" id="post-container">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="post-card" data-problem-id="<?php echo $row['id']; ?>">
                                <div class="post-header">
                                    <div class="post-avatar">
                                        <?php if ($row['anonymous']): ?>
                                            <i class="fas fa-user-secret"></i>
                                        <?php else: ?>
                                            
                                                <?php if (!empty($row['profile_pic'])): ?>
                                                    <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Avatar" class="avatar-image" width="48" height="48" loading="lazy">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle"></i>
                                                <?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-meta">
                                        <?php if ($row['anonymous']): ?>
                                            <h4>Anonymous</h4>
                                        <?php else: ?>
                                            <h4><a href="pages/profile.php?user_id=<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['username']); ?></a></h4>
                                        <?php endif; ?>
                                        <span class="post-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($row['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if (isset($_SESSION['user_id']) && $row['user_id'] == $_SESSION['user_id']): ?>
                                    <div class="post-actions">
                                        <button class="btn-icon delete-post" onclick="deletePost(<?php echo $row['id']; ?>)" title="Delete post">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    <button class="post-options">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                </div>
                                
                                <div class="post-content">
                                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($row['description']); ?></p>
                                </div>

                                <div class="post-actions">
                                    <a href="pages/view_problem.php?problem_id=<?php echo $row['id']; ?>" class="btn-view">
                                        <i class="fas fa-comments"></i> View Solutions
                                    </a>
                                    <button class="btn-like <?php echo $row['user_liked'] ? 'liked' : ''; ?>" 
                                            data-problem-id="<?php echo $row['id']; ?>" 
                                            title="Like">
                                        <i class="<?php echo $row['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <span class="like-count"><?php echo $row['like_count']; ?></span>
                                    </button>
                                    <button class="btn-share" data-problem-id="<?php echo $row['id']; ?>" title="Share">
                                        <i class="fas fa-share-alt"></i>
                                        <span class="share-text">Share</span>
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
        <div class="modal" id="shareModal">
            <div class="modal-header">
                <h3>Share Post</h3>
                <button class="modal-close" id="shareModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-content">
                <div class="share-options">
                    <button class="share-option" data-method="copy">
                        <i class="fas fa-copy"></i>
                        <span>Copy Link</span>
                    </button>
                    <button class="share-option" data-method="facebook">
                        <i class="fab fa-facebook"></i>
                        <span>Facebook</span>
                    </button>
                    <button class="share-option" data-method="twitter">
                        <i class="fab fa-twitter"></i>
                        <span>Twitter</span>
                    </button>
                    <button class="share-option" data-method="whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp</span>
                    </button>
                </div>
                <div class="share-link">
                    <input type="text" id="shareUrl" readonly>
                    <button class="btn-copy" id="copyUrlBtn">
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
        
        // Update counts when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateMessageCount();
                updateNotificationCount();
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
        document.querySelectorAll('.btn-like').forEach(button => {
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

        document.querySelectorAll('.btn-share').forEach(button => {
            button.addEventListener('click', function() {
                currentProblemId = this.dataset.problemId;
                const shareUrl = `${window.location.origin}/pages/view_problem?problem_id=${currentProblemId}`;
                shareUrlInput.value = shareUrl;
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
                const url = encodeURIComponent(shareUrlInput.value);
                const title = encodeURIComponent('Check out this problem on MUFFEIA');
                
                let shareWindow = '';
                
                switch(method) {
                    case 'facebook':
                        shareWindow = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                        break;
                    case 'twitter':
                        shareWindow = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                        break;
                    case 'whatsapp':
                        shareWindow = `https://wa.me/?text=${title}%20${url}`;
                        break;
                    case 'copy':
                        // Already handled by copy button
                        return;
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

        // Infinite scroll and post loading
        let page = <?php echo $page; ?>;
        let loading = false;
        let hasMore = <?php echo $has_more ? 'true' : 'false'; ?>;
        let lastPostTime = '<?php echo $latest_post_time; ?>';
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
            document.querySelectorAll('.btn-like').forEach(button => {
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
            document.querySelectorAll('.btn-share').forEach(button => {
                if (!button.hasAttribute('data-listener-attached')) {
                    button.setAttribute('data-listener-attached', 'true');
                    button.addEventListener('click', function() {
                        currentProblemId = this.dataset.problemId;
                        const shareUrl = `${window.location.origin}${window.location.pathname}pages/view_problem.php?problem_id=${currentProblemId}`;
                        shareUrlInput.value = shareUrl;
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
        
        document.getElementById('new-posts-notification').addEventListener('click', function() {
            loadNewPosts();
        });
        
        window.addEventListener('focus', checkForNewPosts);
    });
</script>
<script src="js/post-actions.js"></script>
</body>
</html>