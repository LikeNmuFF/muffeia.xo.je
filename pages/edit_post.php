<?php
include '../includes/db.php';
include '../includes/moderation.php';

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if post_id is provided
if (!isset($_GET['post_id'])) {
    header("Location: profile.php");
    exit();
}

$post_id = $_GET['post_id'];

// Verify the post belongs to the current user
$sql = "SELECT * FROM problems WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $post_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

if (!$post) {
    header("Location: profile.php");
    exit();
}

$error_message = "";
$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Log POST data
    error_log("POST Data Received: " . print_r($_POST, true));
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($title)) {
        $error_message = "Title is required.";
    } elseif (empty($description)) {
        $error_message = "Description is required.";
    } elseif (strlen($title) > 255) {
        $error_message = "Title must be less than 255 characters.";
    } else {
        if (function_exists('moderate_text')) {
            foreach (['title' => $title, 'description' => $description] as $field => $value) {
                $mod = moderate_text($value);
                if (!empty($mod['flagged'])) {
                    $error_message = ucfirst($field) . " contains disallowed language. Please revise and try again.";
                    break;
                }
            }
        }
    }

    if (empty($error_message)) {
        // Update the post
        $sql = "UPDATE problems SET title = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssii", $title, $description, $post_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "Post updated successfully!";
                    // Update the local post data
                    $post['title'] = $title;
                    $post['description'] = $description;
                    
                    // Redirect after successful update
                    header("Location: profile.php?success=Post updated successfully");
                    exit();
                } else {
                    $error_message = "No changes were made or post not found.";
                }
            } else {
                $error_message = "Error updating post: " . $conn->error;
                error_log("Database Error: " . $conn->error);
            }
            $stmt->close();
        } else {
            $error_message = "Database error: " . $conn->error;
            error_log("Prepare Statement Error: " . $conn->error);
        }
    }
    
    // If we have an error, log it for debugging
    if (!empty($error_message)) {
        error_log("Form Error: " . $error_message);
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
    <link rel="icon" href="../logo/m-blues.png">
    <title>MUFFEIA - Edit Post</title>
    
    <!-- Mobile-specific CSS -->
    <style>
        /* Debug styling */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            .form-group label, .form-group input, .form-group textarea {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .btn-submit, .btn-view {
                padding: 14px 20px;
                min-height: 50px; /* Larger touch target */
                display: flex;
                align-items: center;
                justify-content: center;
                flex: 1;
                font-size: 16px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-submit {
                background: var(--primary);
                color: white;
            }
            
            .btn-submit:active {
                background: var(--primary-dark);
                transform: scale(0.98);
            }
            
            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .form-actions .btn-submit, 
            .form-actions .btn-view {
                width: 100%;
                margin: 0;
            }
            
            .post-area {
                margin: 0.5rem;
                padding: 1rem;
            }
            
            .post-card {
                margin: 1rem 0.5rem;
            }
            
            .post-form {
                padding: 0;
            }
            
            .content {
                padding: 0.5rem;
            }
            
            /* Improve textarea for mobile */
            textarea {
                min-height: 150px;
                resize: vertical;
                font-size: 16px; /* Prevent zoom */
            }
            
            input[type="text"] {
                font-size: 16px; /* Prevent zoom */
            }
            
            /* Better mobile navigation */
            .top-nav {
                padding: 0.75rem 1rem;
            }
            
            .top-nav h1 {
                font-size: 1.25rem;
            }
        }
        
        /* Loading state for form submission */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .submit-loading {
            position: relative;
            color: transparent;
        }
        
        .submit-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-right-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Form validation styling */
        .form-group.error input,
        .form-group.error textarea {
            border-color: var(--danger);
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
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
                    <img src="../logo/m-blues.png" alt="MUFFEIA" class="logo-image">
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
            </div>

            <div class="sidebar-footer">
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
                    <h1>Edit Post</h1>
                    <div class="user-actions">
                        <a href="message.php" class="icon-btn message-btn" role="button">
                            <i class="fas fa-envelope"></i>
                        </a>
                        <a href="notifications.php" class="icon-btn notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot"></span>
                        </a>
                      <a href="chatbot.php" class="nav-item">
                    <i class="fas fa-robot"></i>
                    <span>AI Assistant</span>
                </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content">
                <!-- Debug info -->
                <div class="debug-info" style="display: <?php echo isset($_GET['debug']) ? 'block' : 'none'; ?>;">
                    <strong>Debug Information:</strong><br>
                    Post ID: <?php echo $post_id; ?><br>
                    User ID: <?php echo $user_id; ?><br>
                    Post Found: <?php echo $post ? 'Yes' : 'No'; ?><br>
                    Form Method: POST<br>
                    Current Title: <?php echo htmlspecialchars($post['title']); ?><br>
                    Form Submitted: <?php echo ($_SERVER['REQUEST_METHOD'] == 'POST') ? 'Yes' : 'No'; ?>
                </div>

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

                <!-- Edit Post Form -->
                <div class="post-area">
                    <div class="post-area-header">
                        <h3>
                            <i class="fas fa-edit"></i>
                            Edit Your Problem
                        </h3>
                        <a href="profile.php" class="close-post-area">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <form action="edit_post.php?post_id=<?php echo $post_id; ?>" method="post" class="post-form" id="editForm">
                        <div class="form-group">
                            <label for="title">
                                <i class="fas fa-heading"></i>
                                Problem Title
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                value="<?php echo htmlspecialchars($post['title']); ?>" 
                                placeholder="Enter a clear title for your problem..."
                                maxlength="255"
                                required
                            >
                            <div class="error-message" id="title-error"></div>
                            <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                <i class="fas fa-info-circle"></i>
                                Be specific about the issue you're facing
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                <i class="fas fa-align-left"></i>
                                Problem Description
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                placeholder="Describe your problem in detail. Include any error messages, what you've tried, and what you expect to happen..."
                                rows="8"
                                required
                            ><?php echo htmlspecialchars($post['description']); ?></textarea>
                            <div class="error-message" id="description-error"></div>
                            <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                <i class="fas fa-lightbulb"></i>
                                The more details you provide, the better others can help you
                            </small>
                        </div>

                        <div class="form-actions" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-save"></i>
                                Update Post
                            </button>
                            <a href="profile.php" class="btn-view" style="text-decoration: none;">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                            <div style="flex: 1;"></div>
                            <small style="color: var(--text-secondary);">
                                <i class="far fa-clock"></i>
                                Created: <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                            </small>
                        </div>
                    </form>
                </div>

                <!-- Post Preview -->
                <div class="post-card" style="margin-top: 2rem;">
                    <div class="post-header">
                        <div class="post-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="post-meta">
                            <h4>Preview</h4>
                            <span class="post-time">
                                <i class="far fa-clock"></i>
                                Updated just now
                            </span>
                        </div>
                    </div>
                    
                    <div class="post-content">
                        <h3 id="preview-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p id="preview-description"><?php echo htmlspecialchars($post['description']); ?></p>
                    </div>

                    <div class="post-stats">
                        <div class="stat">
                            <i class="fas fa-heart"></i>
                            <span>0 Likes</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-comments"></i>
                            <span>0 Solutions</span>
                        </div>
                    </div>

                    <div class="post-actions">
                        <a href="view_problem.php?problem_id=<?php echo $post_id; ?>" class="btn-view">
                            <i class="fas fa-eye"></i>
                            View Post
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date("Y"); ?> MUFFEIA @Muffy. All rights reserved.</p>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <span>•</span>
                <a href="terms.php">Terms of Service</a>
            </div>
        </div>
    </footer>

    <script src="../js/mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editForm');
            const submitBtn = document.getElementById('submitBtn');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const titleError = document.getElementById('title-error');
            const descriptionError = document.getElementById('description-error');
            
            // Real-time preview updates
            const previewTitle = document.getElementById('preview-title');
            const previewDescription = document.getElementById('preview-description');

            titleInput.addEventListener('input', function() {
                previewTitle.textContent = this.value || '<?php echo htmlspecialchars($post['title']); ?>';
                clearError(this, titleError);
            });

            descriptionInput.addEventListener('input', function() {
                previewDescription.textContent = this.value || '<?php echo htmlspecialchars($post['description']); ?>';
                clearError(this, descriptionError);
            });

            // Character counter for title
            const titleCounter = document.createElement('small');
            titleCounter.style.color = 'var(--text-secondary)';
            titleCounter.style.marginTop = '0.5rem';
            titleCounter.style.display = 'block';
            titleCounter.innerHTML = '<i class="fas fa-text-height"></i> <span id="title-count">' + titleInput.value.length + '</span>/255 characters';
            titleInput.parentNode.appendChild(titleCounter);

            titleInput.addEventListener('input', function() {
                const count = this.value.length;
                document.getElementById('title-count').textContent = count;
                
                if (count > 255) {
                    titleCounter.style.color = 'var(--danger)';
                } else if (count > 200) {
                    titleCounter.style.color = 'var(--warning)';
                } else {
                    titleCounter.style.color = 'var(--text-secondary)';
                }
            });

            // Enhanced form validation
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                let isValid = true;

                // Clear previous errors
                clearError(titleInput, titleError);
                clearError(descriptionInput, descriptionError);

                // Validate title
                if (!title) {
                    showError(titleInput, titleError, 'Title is required');
                    isValid = false;
                } else if (title.length > 255) {
                    showError(titleInput, titleError, 'Title must be less than 255 characters');
                    isValid = false;
                }

                // Validate description
                if (!description) {
                    showError(descriptionInput, descriptionError, 'Description is required');
                    isValid = false;
                }

                if (isValid) {
                    // Show loading state
                    submitBtn.classList.add('submit-loading');
                    form.classList.add('loading');
                    
                    // Submit form after a short delay to show loading state
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                }
            });

            function showError(input, errorElement, message) {
                input.parentElement.classList.add('error');
                errorElement.textContent = message;
                errorElement.style.display = 'block';
                input.focus();
            }

            function clearError(input, errorElement) {
                input.parentElement.classList.remove('error');
                errorElement.style.display = 'none';
            }

            // Mobile-specific enhancements
            function isMobile() {
                return window.innerWidth <= 768;
            }
            
            // Auto-focus title field on mobile for better UX
            if (isMobile() && titleInput.value === '') {
                setTimeout(() => {
                    titleInput.focus();
                }, 300);
            }
            
            // Add touch feedback for mobile buttons
            if (isMobile()) {
                const buttons = document.querySelectorAll('.btn-submit, .btn-view');
                buttons.forEach(button => {
                    button.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.98)';
                    });
                    
                    button.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                    });
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
            
            menuToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);
        });
    </script>
</body>
</html>