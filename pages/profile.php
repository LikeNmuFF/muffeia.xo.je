<?php
include '../includes/db.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Determine which user's profile to show. If `user_id` is provided in the query string
// and is numeric, show that profile; otherwise show the logged-in user's profile.
$current_user_id = $_SESSION['user_id'];
$profile_user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : $current_user_id;

// Fetch user details from the database for the profile being viewed
$sql = "SELECT id, username, email, created_at, profile_pic FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    // Profile not found — redirect back to home or show an error
    header("Location: ../index.php");
    exit();
}

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
// Handle profile picture upload only when the logged-in user is viewing their own profile
if ($profile_user_id == $current_user_id && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    if ($_FILES["profile_pic"]["error"] == UPLOAD_ERR_NO_FILE) {
        $error_message = "No file selected. Please choose a picture to upload.";
    } else {
        $target_dir = "../uploads/profile_pics/";
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        // Use the logged-in user's id variable (defined above) when composing the filename
        $new_filename = "user_" . $current_user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $error_message = "File is not an image.";
        } else {
            if (in_array(strtolower($file_extension), ["jpg", "jpeg", "png", "gif"])) {
                if ($_FILES["profile_pic"]["size"] > 5000000) {
                    $error_message = "Sorry, your file is too large. Maximum size is 5MB.";
                } else {
                    if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                        // Delete old profile picture if it exists
                        if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
                            unlink($user['profile_pic']);
                        }
                        
                        $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $relative_path = "uploads/profile_pics/" . $new_filename;
                        // bind the current logged-in user's id (we already ensure they own the profile)
                        $stmt->bind_param("si", $relative_path, $current_user_id);
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
if (isset($_GET['delete_post'])) {
    $post_id = $_GET['delete_post'];
    $sql_check_post = "SELECT user_id FROM problems WHERE id = ?";
    $stmt_check_post = $conn->prepare($sql_check_post);
    $stmt_check_post->bind_param("i", $post_id);
    $stmt_check_post->execute();
    $result_check_post = $stmt_check_post->get_result();

    if ($result_check_post->num_rows > 0) {
        $post = $result_check_post->fetch_assoc();
        // Only allow deletion when the logged-in user is the owner of the post
        if ($post['user_id'] == $current_user_id) {
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
    <link rel="stylesheet" href="../css/modern-theme.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <title>MUFFEIA - Profile</title>
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
                <!-- Dark mode logo -->
                <img src="../logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
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
                    <span class="badge">3</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="chatbot.php" class="nav-item">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
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
                    <h1>Your Profile</h1>
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
                <div class="profile-header-card">
                    <div class="profile-background"></div>
                    <div class="profile-content">
                        <div class="profile-avatar-section">
                            <div class="profile-avatar">
                                <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="../<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" class="avatar-image" width="120" height="120" loading="eager">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if ($profile_user_id == $current_user_id): ?>
                                <div class="avatar-overlay">
                                    <label for="profile-pic-upload" class="avatar-upload-btn">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                            </div>
                            <form action="profile.php?user_id=<?php echo $profile_user_id; ?>" method="post" enctype="multipart/form-data" class="avatar-upload-form">
                                <input type="file" name="profile_pic" id="profile-pic-upload" accept="image/*" hidden>
                                <button type="submit" class="btn-primary" id="upload-submit" style="display: none;">Update Picture</button>
                            </form>
                                <?php else: ?>
                                </div>
                                <?php endif; ?>
                        </div>
                        
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="profile-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="profile-join-date">
                                <i class="fas fa-calendar-alt"></i>
                                Member since <?php echo date("F j, Y", strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon posts">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['post_count'] ?? 0; ?></h3>
                            <p>Problems Posted</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon solutions">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['solution_count'] ?? 0; ?></h3>
                            <p>Solutions Provided</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon likes">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_likes'] ?? 0; ?></h3>
                            <p>Total Likes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon engagement">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo ($stats['post_count'] ?? 0) + ($stats['solution_count'] ?? 0); ?></h3>
                            <p>Total Engagement</p>
                        </div>
                    </div>
                </div>

                <!-- User Posts Section -->
                <div class="posts-section">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-file-alt"></i>
                            Your Problems (<?php echo $result_posts->num_rows; ?>)
                        </h3>
                    </div>

                    <?php if ($result_posts->num_rows > 0): ?>
                        <div class="posts-grid">
                            <?php while ($post = $result_posts->fetch_assoc()): ?>
                                <div class="post-card profile-post-card">
                                    <div class="post-header">
                                        <div class="post-meta">
                                            <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                            <span class="post-time">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="post-actions-dropdown">
                                            <button class="post-options">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                    <a href="../index.php?problem_id=<?php echo $post['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-eye"></i> View Post
                                                    </a>
                                                    <?php if ($profile_user_id == $current_user_id): ?>
                                                    <a href="edit_post.php?post_id=<?php echo $post['id']; ?>" class="dropdown-item">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="profile.php?delete_post=<?php echo $post['id']; ?>" class="dropdown-item delete" onclick="return confirm('Are you sure you want to delete this post?');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                    <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="post-content">
                                        <p><?php echo htmlspecialchars($post['description']); ?></p>
                                    </div>

                                    <div class="post-stats">
                                        <div class="stat">
                                            <i class="fas fa-heart"></i>
                                            <span><?php echo $post['like_count'] ?? 0; ?> Likes</span>
                                        </div>
                                        <div class="stat">
                                            <i class="fas fa-comments"></i>
                                            <span>View Solutions</span>
                                        </div>
                                    </div>

                                    <div class="post-actions">
                                        <a href="view_problem.php?problem_id=<?php echo $post['id']; ?>" class="btn-view">
                                            <i class="fas fa-comments"></i> View Solutions
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
                </div>

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
            </div>
        </div>
    </div>

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
<style>
/* Profile picture circular and clear */
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #ccc; /* optional border for clarity */
    display: inline-block;
}

.avatar-image {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    border-radius: 50%;
}

/* Optional: style for posts images if added in future */
.post-image {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 8px;
}
</style>
</body>
</html>
