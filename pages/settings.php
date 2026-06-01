<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT username, email, created_at, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please try again.';
    } elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->bind_param("si", $hash, $user_id);
            $update->execute();
            $update->close();
            $message = 'Password changed successfully!';
        }
    } elseif ($_POST['action'] === 'change_email') {
        $new_email = trim($_POST['new_email'] ?? '');
        $password = $_POST['password_for_email'] ?? '';

        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($password, $row['password_hash'])) {
            $error = 'Password is incorrect.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $new_email, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'Email address is already in use.';
            } else {
                $update = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $update->bind_param("si", $new_email, $user_id);
                $update->execute();
                $update->close();
                $message = 'Email updated successfully!';
                $user['email'] = $new_email;
            }
            $check->close();
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
    <title>Settings — MUFFEIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <link rel="icon" href="/logo/m-blues.png" type="image/png">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <style>
        .settings-content {
            max-width: 680px;
            margin: 0 auto;
            padding: 28px 24px 60px;
            animation: settingsFadeIn 0.5s ease both;
        }

        @keyframes settingsFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .settings-page-title {
            font-family: var(--font-heading, Outfit);
            font-size: 22px;
            font-weight: 700;
            color: var(--clr-text-theme);
            margin: 0 0 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-page-title i {
            color: var(--clr-primary);
        }

        .settings-page-sub {
            font-size: 14px;
            color: var(--clr-text-secondary-theme);
            margin: 0 0 28px;
            font-weight: 400;
        }

        .settings-card {
            background: var(--clr-surface-theme);
            border: 1px solid var(--clr-border-theme);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
            transition: box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            animation: settingsFadeIn 0.5s ease both;
        }

        .settings-card:hover {
            box-shadow: 0 8px 28px rgba(212,74,108,0.06), 0 2px 8px rgba(0,0,0,0.04);
        }

        .settings-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1.5px solid var(--clr-border-light-theme);
            margin-bottom: 24px;
        }

        .settings-card-header .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .settings-card-header .card-icon.rose {
            background: var(--clr-primary-lighter);
            color: var(--clr-primary);
        }

        .settings-card-header .card-icon.teal {
            background: var(--clr-secondary-lighter);
            color: var(--clr-secondary);
        }

        .settings-card-header .card-icon.gold {
            background: var(--clr-accent-light);
            color: var(--clr-accent-dark);
        }

        .settings-card-header h3 {
            font-family: var(--font-heading, Outfit);
            font-size: 17px;
            font-weight: 600;
            margin: 0;
            color: var(--clr-text-theme);
        }

        .settings-card-header p {
            margin: 2px 0 0;
            font-size: 13px;
            color: var(--clr-text-secondary-theme);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-item .info-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--clr-text-tertiary-theme);
            font-family: var(--font-heading, Outfit);
        }

        .info-item .info-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--clr-text-theme);
            word-break: break-word;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font-heading, Outfit);
            color: var(--clr-text-secondary-theme);
            margin-bottom: 6px;
            letter-spacing: 0.2px;
        }

        .form-group .input-wrap {
            position: relative;
        }

        .form-group .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-text-tertiary-theme);
            font-size: 14px;
            pointer-events: none;
        }

        .settings-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            font-size: 14px;
            font-family: var(--font-body, 'Source Sans 3');
            background: var(--clr-bg-theme);
            border: 1.5px solid var(--clr-border-theme);
            border-radius: 10px;
            color: var(--clr-text-theme);
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
        }

        .settings-input:focus {
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px rgba(212,74,108,0.1);
        }

        .settings-input::placeholder {
            color: var(--clr-text-tertiary-theme);
        }

        .settings-btn {
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font-heading, Outfit);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .settings-btn-primary {
            background: var(--clr-primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(212,74,108,0.2);
        }

        .settings-btn-primary:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(212,74,108,0.3);
        }

        .settings-btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: settingsFadeIn 0.3s ease both;
        }

        .alert-success {
            background: var(--clr-secondary-lighter);
            color: var(--clr-secondary-dark);
            border: 1px solid var(--clr-secondary-light);
        }

        .alert-error {
            background: var(--clr-primary-lighter);
            color: var(--clr-primary-darker);
            border: 1px solid var(--clr-primary-light);
        }

        .member-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--clr-primary-lighter);
            color: var(--clr-primary);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-heading, Outfit);
        }

        @media (max-width: 640px) {
            .settings-content { padding: 20px 16px 40px; }
            .settings-card { padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../logo/m-light.png" alt="Muffeia" class="logo-light logo-image">
                    <img src="../logo/m-blues.png" alt="Muffeia" class="logo-dark logo-image">
                    <span>Muffeia</span>
                </div>
                <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="theme-switch-wrapper">
                <label class="theme-switch">
                    <input type="checkbox" id="themeToggle">
                    <span class="slider"></span>
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

                <a href="settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../auth/logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
        <div class="main-content">
            <header class="top-nav">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="top-nav-content">
                    <h1>Account Settings</h1>
                    <div class="user-actions">
                        <a href="message.php" class="icon-btn"><i class="fas fa-envelope"></i></a>
                        <a href="notifications.php" class="icon-btn"><i class="fas fa-bell"></i></a>
                        <a href="search.php" class="icon-btn" title="Search"><i class="fas fa-search"></i></a>
                    </div>
                </div>
            </header>
            <div class="content">
                <div class="settings-content">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <h2 class="settings-page-title"><i class="fas fa-sliders-h"></i> Settings</h2>
                    <p class="settings-page-sub">Manage your account details, security, and preferences</p>

                    <div class="settings-card" style="animation-delay: 0.05s;">
                        <div class="settings-card-header">
                            <div class="card-icon rose"><i class="fas fa-user-circle"></i></div>
                            <div>
                                <h3>Account Info</h3>
                                <p>Your account details and membership</p>
                            </div>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Username</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date("F j, Y", strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="member-badge"><i class="fas fa-shield-alt"></i> Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card" style="animation-delay: 0.1s;">
                        <div class="settings-card-header">
                            <div class="card-icon gold"><i class="fas fa-lock"></i></div>
                            <div>
                                <h3>Change Password</h3>
                                <p>Update your password — minimum 8 characters</p>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <label>Current Password</label>
                                <div class="input-wrap">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="current_password" class="settings-input" placeholder="Enter current password" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="input-wrap">
                                    <i class="fas fa-key"></i>
                                    <input type="password" name="new_password" class="settings-input" placeholder="At least 8 characters" required minlength="8">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="input-wrap">
                                    <i class="fas fa-check-circle"></i>
                                    <input type="password" name="confirm_password" class="settings-input" placeholder="Re-enter new password" required minlength="8">
                                </div>
                            </div>
                            <button type="submit" class="settings-btn settings-btn-primary" style="margin-top: 4px;">
                                <i class="fas fa-save"></i> Update Password
                            </button>
                        </form>
                    </div>

                    <div class="settings-card" style="animation-delay: 0.15s;">
                        <div class="settings-card-header">
                            <div class="card-icon teal"><i class="fas fa-envelope"></i></div>
                            <div>
                                <h3>Change Email</h3>
                                <p>Update your email address (requires password confirmation)</p>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_email">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-group">
                                <label>New Email Address</label>
                                <div class="input-wrap">
                                    <i class="fas fa-at"></i>
                                    <input type="email" name="new_email" class="settings-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm with Password</label>
                                <div class="input-wrap">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="password_for_email" class="settings-input" placeholder="Enter your password to confirm" required>
                                </div>
                            </div>
                            <button type="submit" class="settings-btn settings-btn-primary" style="margin-top: 4px;">
                                <i class="fas fa-save"></i> Update Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            document.getElementById('menuToggle').addEventListener('click', () => sidebar.classList.toggle('active'));
            document.getElementById('sidebarClose').addEventListener('click', () => sidebar.classList.remove('active'));
        });
    </script>
</body>
</html>
