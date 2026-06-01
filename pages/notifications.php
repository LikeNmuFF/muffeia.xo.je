<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch notifications
$sql = "SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Mark all notifications as read when page loads
$mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$mark_read_stmt = $conn->prepare($mark_read_sql);
$mark_read_stmt->bind_param("i", $user_id);
$mark_read_stmt->execute();
$mark_read_stmt->close();
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
    <link rel="icon" href="../logo/m-blues.png">
    <title>Muffeia — Notifications</title>
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .notifications-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .notifications-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            margin-left: auto;
        }

        .btn-mark-all {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-mark-all:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-clear-all {
            background: var(--error);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-clear-all:hover {
            background: var(--error-hover);
            transform: translateY(-2px);
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .notification-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all var(--transition-fast);
            position: relative;
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .notification-card.unread {
            border-left: 4px solid var(--primary);
            background: var(--primary-light);
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-message {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            line-height: 1.5;
            color: var(--text-primary);
            word-wrap: break-word;
        }

        .notification-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
            flex-shrink: 0;
        }

        .notification-link {
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-link:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .delete-btn {
            background: var(--error);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-btn:hover {
            background: var(--error-hover);
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        .notification-badge {
            background: var(--primary);
            color: white;
            border-radius: 12px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .notification-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        @media (max-width: 768px) {
            .notifications-container {
                padding: 1rem;
            }

            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                margin-left: 0;
                width: 100%;
                justify-content: space-between;
            }

            .notification-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-actions {
                margin-left: 0;
                width: 100%;
                justify-content: flex-end;
            }

            .btn-mark-all,
            .btn-clear-all {
                flex: 1;
                justify-content: center;
            }
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
                <span class="theme-label">Light/Dark Mode</span>
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
                </a>
                <a href="group-room.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Group Chats</span>
                </a>
                <a href="notifications.php" class="nav-item active">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
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
                    <h1>Notifications</h1>
                    <div class="user-actions">
                        <a href="message.php" class="icon-btn message-btn" role="button">
                            <i class="fas fa-envelope"></i>
                            <span class="badge" id="messageBadge" style="display: none;">0</span>
                        <a href="notifications.php" class="icon-btn notification-btn active">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot"></span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content">
                <div class="notifications-container" style="max-width: 800px; margin: 0 auto;">
                    <div class="notifications-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); padding-bottom: var(--space-5); border-bottom: 2px solid var(--clr-border-theme); flex-wrap: wrap; gap: var(--space-4);">
                        <h1 style="font-family: var(--font-heading); font-size: var(--text-3xl); font-weight: 700; color: var(--clr-text-primary); display: flex; align-items: center; gap: var(--space-3); margin: 0;">
                            <i class="fas fa-bell" style="background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                            Notifications
                            <?php if ($result->num_rows > 0): ?>
                                <span class="badge" style="font-size: var(--text-sm); padding: 4px 12px; -webkit-text-fill-color: white;"><?php echo $result->num_rows; ?></span>
                            <?php endif; ?>
                        </h1>
                        <div style="display: flex; gap: var(--space-3);">
                            <button class="btn btn-ghost" id="markAllRead" style="display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4);">
                                <i class="fas fa-check-double"></i>
                                Mark All Read
                            </button>
                            <button class="btn btn-ghost" id="clearAllNotifications" style="display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4); color: var(--clr-text-tertiary); border-color: var(--clr-border-theme);">
                                <i class="fas fa-trash"></i>
                                Clear All
                            </button>
                        </div>
                    </div>

                    <div class="notifications-list" id="notificationsList">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $icon_class = 'fas fa-bell';
                                $type_class = '';
                                
                                // Determine icon and type based on notification content
                                if (strpos(strtolower($row['message']), 'like') !== false) {
                                    $icon_class = 'fas fa-heart';
                                    $type_class = 'Like';
                                } elseif (strpos(strtolower($row['message']), 'comment') !== false || strpos(strtolower($row['message']), 'reply') !== false) {
                                    $icon_class = 'fas fa-comment';
                                    $type_class = 'Comment';
                                } elseif (strpos(strtolower($row['message']), 'follow') !== false) {
                                    $icon_class = 'fas fa-user-plus';
                                    $type_class = 'Follow';
                                } elseif (strpos(strtolower($row['message']), 'message') !== false) {
                                    $icon_class = 'fas fa-envelope';
                                    $type_class = 'Message';
                                }
                                ?>
                                  <div class="notification-item card-elevated-sm <?php echo $row['is_read'] ? '' : 'unread'; ?>" id="notification-<?php echo $row['id']; ?>" style="background: var(--clr-surface-theme); border: 1px solid var(--clr-border-theme); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-3); transition: all 0.2s ease; position: relative; overflow: hidden; display: flex; gap: var(--space-4); align-items: flex-start; <?php echo !$row['is_read'] ? 'background: linear-gradient(135deg, rgba(212, 74, 108, 0.04) 0%, var(--clr-surface-theme) 100%); border-color: var(--clr-primary-light);' : ''; ?>" onmouseover="this.style.boxShadow='0 4px 12px rgba(212, 74, 108, 0.1)'; this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)'">
                                      <div class="notification-icon" style="width: 40px; height: 40px; border-radius: var(--radius-lg); background: linear-gradient(135deg, rgba(212, 74, 108, 0.15) 0%, rgba(42, 157, 143, 0.15) 100%); display: flex; align-items: center; justify-content: center; color: var(--clr-primary); flex-shrink: 0; font-size: var(--text-lg); position: relative; z-index: 1;">
                                          <i class="<?php echo $icon_class; ?>"></i>
                                      </div>
                                      <div style="flex: 1; position: relative; z-index: 1; min-width: 0;">
                                          <p style="font-weight: <?php echo $row['is_read'] ? '500' : '600'; ?>; color: var(--clr-text-primary); margin: 0; margin-bottom: 6px; font-size: var(--text-sm); line-height: 1.5;">
                                              <?php echo htmlspecialchars($row['message']); ?>
                                          </p>
                                          <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                                              <div style="font-size: 11px; color: var(--clr-text-tertiary); display: flex; align-items: center; gap: 4px;">
                                                  <?php echo date('M j \a\t g:i A', strtotime($row['created_at'])); ?>
                                              </div>
                                              <?php if ($type_class): ?>
                                                  <span class="badge" style="font-size: 10px; padding: 3px 8px; background: linear-gradient(135deg, rgba(212, 74, 108, 0.15) 0%, rgba(212, 74, 108, 0.1) 100%); color: var(--clr-primary); text-transform: capitalize; letter-spacing: 0.3px;"><?php echo $type_class; ?></span>
                                              <?php endif; ?>
                                          </div>
                                      </div>
                                      <div style="display: flex; gap: var(--space-2); align-items: center; flex-shrink: 0; position: relative; z-index: 1;">
                                          <?php if (!empty($row['target_url'])): ?>
                                              <?php
                                              // Remove "pages/" from the target URL if it exists
                                              $clean_url = str_replace('pages/', '', $row['target_url']);
                                              ?>
                                              <a href="<?php echo htmlspecialchars($clean_url); ?>" class="post-action" title="View" style="padding: 6px 8px; font-size: var(--text-sm);">
                                                  <i class="fas fa-arrow-right"></i>
                                              </a>
                                          <?php endif; ?>
                                          <button class="post-action delete-btn" data-id="<?php echo $row['id']; ?>" title="Delete notification" style="padding: 6px 8px; font-size: var(--text-sm);">
                                              <i class="fas fa-trash-alt"></i>
                                          </button>
                                      </div>
                                  </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No notifications yet</h3>
                                <p>When you get notifications, they'll appear here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

  

    <script src="../js/mode.js"></script>
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

            // Delete notification
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-id');
                    const notificationCard = document.getElementById('notification-' + notificationId);
                    
                    if (confirm('Are you sure you want to delete this notification?')) {
                        fetch('../api/delete_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'id=' + notificationId
                        })
                        .then(response => response.text())
                        .then(result => {
                            if (result === 'success') {
                                notificationCard.style.opacity = '0';
                                notificationCard.style.transform = 'translateX(100px)';
                                setTimeout(() => {
                                    notificationCard.remove();
                                    updateNotificationCount();
                                }, 300);
                            } else {
                                alert('Error deleting notification.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting notification.');
                        });
                    }
                });
            });

            // Mark all as read
            document.getElementById('markAllRead').addEventListener('click', function() {
                fetch('../api/mark_all_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                })
                .then(response => response.text())
                .then(result => {
                    if (result === 'success') {
                        document.querySelectorAll('.notification-card.unread').forEach(card => {
                            card.classList.remove('unread');
                            card.classList.add('read');
                        });
                        updateNotificationCount();
                    } else {
                        alert('Error marking notifications as read.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error marking notifications as read.');
                });
            });

            // Clear all notifications
            document.getElementById('clearAllNotifications').addEventListener('click', function() {
                if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
                    fetch('../api/clear_all_notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    })
                    .then(response => response.text())
                    .then(result => {
                        if (result === 'success') {
                            document.getElementById('notificationsList').innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h3>No notifications yet</h3>
                                    <p>When you get notifications, they'll appear here</p>
                                </div>
                            `;
                            updateNotificationCount();
                        } else {
                            alert('Error clearing notifications.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error clearing notifications.');
                    });
                }
            });

            // Update notification count badge
            function updateNotificationCount() {
                const notificationCards = document.querySelectorAll('.notification-card');
                const badge = document.getElementById('notificationBadge');
                
                if (notificationCards.length === 0) {
                    badge.style.display = 'none';
                } else {
                    const unreadCount = document.querySelectorAll('.notification-card.unread').length;
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }

            // Check for new notifications periodically
            function checkNewNotifications() {
                fetch('../api/check_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notificationBadge');
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error checking notifications:', error));
            }

            // Check every 30 seconds
            setInterval(checkNewNotifications, 30000);

            // Initialize notification count
            updateNotificationCount();
        });
    </script>
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>