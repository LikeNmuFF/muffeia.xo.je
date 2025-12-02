<?php
include '../includes/db.php';
include '../includes/security.php';
session_start();
$success = '';
$error = '';
$is_register_mode = false;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Social Login Configuration - UPDATE THESE WITH YOUR CREDENTIALS
$social_config = [
    'google' => [
        'clientId' => 'YOUR_GOOGLE_CLIENT_ID',
        'clientSecret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirectUri' => 'https://muffeia.xo.je/auth/login.php?provider=google',
    ],
    'facebook' => [
        'clientId' => 'YOUR_FACEBOOK_APP_ID',
        'clientSecret' => 'YOUR_FACEBOOK_APP_SECRET',
        'redirectUri' => 'https://muffeia.xo.je/auth/login.php?provider=facebook',
        'graphApiVersion' => 'v18.0'
    ]
];

// Handle Social Login Callbacks
if (isset($_GET['provider']) && in_array($_GET['provider'], ['google', 'facebook'])) {
    $provider = $_GET['provider'];
    
    try {
        if ($provider === 'google') {
            $oauthProvider = new League\OAuth2\Client\Provider\Google($social_config['google']);
        } else {
            $oauthProvider = new League\OAuth2\Client\Provider\Facebook($social_config['facebook']);
        }

        // If we don't have an authorization code, get one
        if (!isset($_GET['code'])) {
            $authUrl = $oauthProvider->getAuthorizationUrl([
                'scope' => ['email', 'profile']
            ]);
            $_SESSION['oauth2state'] = $oauthProvider->getState();
            header('Location: ' . $authUrl);
            exit;

        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            $error = 'Invalid OAuth state';
        } else {
            // Try to get an access token
            $token = $oauthProvider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            // Get user details
            $user = $oauthProvider->getResourceOwner($token);
            $userData = $user->toArray();

            // Process social login user
            processSocialLogin($userData, $provider, $conn);
        }

    } catch (Exception $e) {
        $error = 'Social login failed: ' . $e->getMessage();
    }
}

function processSocialLogin($userData, $provider, $conn) {
    $email = $userData['email'] ?? ($userData['email_address'] ?? '');
    $name = $userData['name'] ?? ($userData['displayName'] ?? '');
    $socialId = $userData['id'] ?? '';
    
    if (empty($email)) {
        $_SESSION['error'] = 'Could not retrieve email from ' . ucfirst($provider);
        header("Location: login.php");
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT u.id, u.username, u.email FROM users u 
                           LEFT JOIN social_logins sl ON u.id = sl.user_id 
                           WHERE u.email = ? OR (sl.provider = ? AND sl.social_id = ?)");
    $stmt->bind_param("sss", $email, $provider, $socialId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        $username = $user['username'];
    } else {
        // Create new user
        $username = generateUsernameFromEmail($email);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, email_verified) VALUES (?, ?, '', 1)");
        $stmt->bind_param("ss", $username, $email);
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
        } else {
            $_SESSION['error'] = 'Failed to create user account';
            header("Location: login.php");
            exit();
        }
    }

    // Store social login info
    $stmt = $conn->prepare("INSERT INTO social_logins (user_id, provider, social_id, social_data) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE social_data = ?");
    $socialData = json_encode($userData);
    $stmt->bind_param("sssss", $userId, $provider, $socialId, $socialData, $socialData);
    $stmt->execute();

    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['social_login'] = true;
    
    header("Location: ../index.php");
    exit();
}

function generateUsernameFromEmail($email) {
    $username = strtok($email, '@');
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    return $username . '_' . substr(md5($email), 0, 4);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        if ($_POST['action'] == 'login') {
            $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
            $stmt->bind_param("s", $_POST['email']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($_POST['password'], $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = 'Incorrect password.';
                }
            } else {
                $error = 'No account found with that email.';
            }
            $stmt->close();
        }

        if ($_POST['action'] == 'register') {
            $is_register_mode = true;
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];

            // Server-side password strength validation
            $password_errors = [];
            if (strlen($password) < 8) {
                $password_errors[] = 'at least 8 characters';
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $password_errors[] = 'an uppercase letter';
            }
            if (!preg_match('/[a-z]/', $password)) {
                $password_errors[] = 'a lowercase letter';
            }
            if (!preg_match('/[0-9]/', $password)) {
                $password_errors[] = 'a number';
            }
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $password_errors[] = 'a special character';
            }

            if (!empty($password_errors)) {
                $error = 'Password must contain ' . implode(', ', $password_errors) . '.';
            } else {
                // Validate username
                if (strlen($username) < 3) {
                    $error = 'Username must be at least 3 characters long.';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    $error = 'Username can only contain letters, numbers, and underscores.';
                } else {
                    // Check for existing user
                    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                    $stmt_check->bind_param("ss", $email, $username);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();

                    if ($result_check->num_rows > 0) {
                        $error = 'Email or Username already exists.';
                    } else {
                        // Insert new user
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                        $stmt_insert->bind_param("sss", $username, $email, $password_hash);
                        
                        if ($stmt_insert->execute()) {
                            header("Location: login.php?registered=success");
                            exit();
                        } else {
                            $error = "Error: " . $conn->error;
                        }
                        $stmt_insert->close();
                    }
                    $stmt_check->close();
                }
            }
        }
    }
}

if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = 'Registration successful! Please log in.';
    $error = ''; // Clear any errors
    $is_register_mode = false;
}

$initial_class = $is_register_mode ? 'register-mode' : '';

// Preserve form data after submission
$preserved_email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
$preserved_username = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muffeia - Login & Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <style>
        /* CSS Variables */
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --primary-light: #8b5cf6;
            --secondary: #f59e0b;
            --secondary-light: #fbbf24;
            --accent: #10b981;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8f9fc;
            --gray: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --container-width-small: 95%;
            --container-width-medium: 85%;
            --container-width-large: 1200px;
            --card-padding-small: 25px 20px;
            --card-padding-large: 40px 35px;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
            background-size: cover;
            background-position: center center;
            min-height: 100vh;
            color: var(--light);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: padding 0.3s ease;
        }

        /* Screen Size Indicator (for debugging) */
        .screen-size-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 10000;
            display: none; /* Hidden by default, enable for debugging */
        }

        /* Main Layout Container */
        .main-container {
            display: flex;
            width: 100%;
            max-width: var(--container-width-large);
            min-height: 600px;
            gap: 30px;
            transition: all 0.3s ease;
        }

        /* Welcome Panel - Visible on wide screens only */
        .welcome-panel {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            opacity: 1;
            transform: translateX(0);
        }

        .welcome-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-icon {
            font-size: 4rem;
            margin-bottom: 25px;
            color: var(--primary-light);
            animation: pulse 2s infinite;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            line-height: 1.2;
        }

        .welcome-features {
            list-style: none;
            margin: 30px 0;
        }

        .welcome-features li {
            padding: 12px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
            color: var(--light);
            opacity: 0.9;
        }

        .welcome-features li i {
            color: var(--accent);
            font-size: 1.2rem;
            width: 24px;
        }

        /* Auth Container - Adapts based on screen size */
        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
        }

        /* Logo - Shows differently based on screen size */
        .logo {
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .logo-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-light);
            animation: pulse 2s infinite;
        }

        /* Logo switching (two images crossfade) */
        .logo-switcher {
            display: inline-block;
            position: relative;
            width: 4rem; /* default width, overridden by parent sizes below */
            height: 4rem;
            overflow: visible;
        }

        /* allow different sizes depending on context */
        .logo-icon .logo-switcher { width: 3rem; height: 3rem; }
        .welcome-icon .logo-switcher { width: 4rem; height: 4rem; }

        .logo-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 10%; /* subtle rounding keeps logos neat */
            transition: opacity 400ms ease, transform 400ms ease;
            opacity: 0;
            transform-origin: center center;
        }

        /* animate two images in alternating crossfade */
        .logo-img-1 { animation: logoCrossfade 6s infinite linear; animation-delay: 0s; }
        .logo-img-2 { animation: logoCrossfade 6s infinite linear; animation-delay: 3s; }

        @keyframes logoCrossfade {
            0%   { opacity: 1; transform: translateY(0); }
            40%  { opacity: 1; transform: translateY(0); }
            50%  { opacity: 0; transform: translateY(-6px); }
            90%  { opacity: 0; transform: translateY(-6px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .logo-text {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -1px;
        }

        .logo-tagline {
            color: var(--gray);
            font-size: 1rem;
            margin-top: 8px;
        }

        /* Auth Card */
        .auth-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: var(--card-padding-large);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tabs */
        .auth-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 30px;
            position: relative;
        }

        .auth-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--gray);
            position: relative;
            z-index: 1;
        }

        .auth-tab.active {
            color: var(--light);
        }

        .tab-slider {
            position: absolute;
            top: 6px;
            left: 6px;
            width: calc(50% - 6px);
            height: calc(100% - 12px);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 8px;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
        }

        .tab-slider.register {
            transform: translateX(100%);
        }

        /* Forms */
        .auth-form {
            display: none;
            animation: fadeIn 0.4s ease-out;
        }

        .auth-form.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--light);
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            color: var(--light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .form-input::placeholder {
            color: #94a3b8;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 12px;
            padding: 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            display: none;
            animation: expand 0.3s ease-out;
        }

        @keyframes expand {
            from {
                opacity: 0;
                transform: scaleY(0);
            }
            to {
                opacity: 1;
                transform: scaleY(1);
            }
        }

        .password-strength.show {
            display: block;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.6);
        }

        /* small text link inside login form (forgot password) */
        .forgot-btn {
            color: var(--gray);
            text-decoration: underline;
            font-size: 0.9rem;
            padding: 6px 10px;
            border-radius: 6px;
            transition: color 160ms ease, background 160ms ease, transform 120ms ease;
        }

        .forgot-btn:hover {
            color: var(--light);
            background: rgba(255,255,255,0.03);
            transform: translateY(-1px);
        }

        .forgot-btn:active { transform: translateY(0); }

        /* Social Login - Hidden on small screens, shown on medium+ */
        .social-login {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: none; /* Hidden by default on mobile */
        }

        .social-login-title {
            text-align: center;
            color: var(--gray);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .social-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--light);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .social-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .social-btn.google {
            color: #DB4437;
        }

        .social-btn.facebook {
            color: #4267B2;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .quick-btn {
            padding: 12px 20px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--light);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }

        .quick-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Floating Elements - Reduced on mobile */
        .floating-element {
            position: absolute;
            border-radius: 50%;
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
            animation-duration: 8s;
            animation-iteration-count: infinite;
            animation-timing-function: ease-in-out;
        }

        /* RESPONSIVE DESIGN */
        
        /* Small Screens (Mobile) - Single column, compact layout */
        @media (max-width: 768px) {
            body {
                padding: 15px;
                align-items: flex-start;
                padding-top: 40px;
            }
            
            .main-container {
                flex-direction: column;
                min-height: auto;
                gap: 20px;
            }
            
            .welcome-panel {
                display: none; /* Hide welcome panel on mobile */
            }
            
            .auth-container {
                width: 100%;
                max-width: 100%;
            }
            
            .auth-card {
                padding: var(--card-padding-small);
            }
            
            .logo {
                margin-bottom: 20px;
            }
            
            .logo-icon {
                font-size: 2.5rem;
            }
            
            .logo-text {
                font-size: 1.8rem;
            }
            
            .form-input {
                padding: 16px 14px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .submit-btn {
                padding: 18px 24px;
            }
            
            .quick-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            /* Reduce floating elements on mobile */
            .floating-element:nth-child(3),
            .floating-element:nth-child(4) {
                display: none;
            }
            
            .floating-element:nth-child(1) {
                width: 150px;
                height: 150px;
                top: -75px;
                right: -75px;
            }
            
            .floating-element:nth-child(2) {
                width: 120px;
                height: 120px;
                bottom: -60px;
                left: -60px;
            }
            
            /* Social login hidden on mobile to save space */
            .social-login {
                display: none;
            }
        }

        /* Medium Screens (Tablets) - Shows social login */
        @media (min-width: 769px) and (max-width: 1024px) {
            .main-container {
                max-width: var(--container-width-medium);
                flex-direction: row;
            }
            
            .welcome-panel {
                flex: 0.8;
                padding: 30px;
            }
            
            .welcome-icon {
                font-size: 3rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .welcome-features li {
                font-size: 1rem;
                padding: 10px 0;
            }
            
            .auth-card {
                padding: 30px 25px;
            }
            
            /* Show social login on tablets */
            .social-login {
                display: block;
            }
        }

        /* Large Screens (Desktop) - Full expanded layout */
        @media (min-width: 1025px) {
            .main-container {
                max-width: var(--container-width-large);
                flex-direction: row;
            }
            
            .welcome-panel {
                flex: 1.2;
                padding: 40px;
            }
            
            .auth-card {
                padding: var(--card-padding-large);
            }
            
            /* Show social login on desktop */
            .social-login {
                display: block;
            }
            
            /* Add hover effect for desktop */
            .auth-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
        }

        /* Extra Large Screens */
        @media (min-width: 1440px) {
            .main-container {
                max-width: 1400px;
                gap: 50px;
            }
            
            .welcome-panel {
                padding: 50px;
            }
            
            .welcome-title {
                font-size: 3rem;
            }
            
            .auth-card {
                padding: 50px 40px;
            }
        }

        /* Landscape Orientation for Mobile */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .main-container {
                min-height: auto;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            
            .auth-card {
                padding: 20px 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .logo {
                margin-bottom: 15px;
            }
            
            .logo-icon {
                font-size: 2rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }

        /* High DPI Screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .auth-card {
                border: 0.5px solid rgba(255, 255, 255, 0.1);
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            
            .auth-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                background: white !important;
            }
            
            .submit-btn, .quick-btn {
                display: none !important;
            }
        }

        /* Loading State */
        .submit-btn.loading {
            position: relative;
            color: transparent;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
    </style>
</head>
<body>
    <!-- Screen size indicator (for debugging) -->
    <div class="screen-size-indicator" id="screenSizeIndicator"></div>

    <!-- Floating background elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <div class="main-container">
        <!-- Welcome Panel (Hidden on small screens) -->
        <div class="welcome-panel" id="welcomePanel">
            <div class="welcome-content">
                <div class="welcome-icon">
                    <div class="logo-switcher" aria-hidden="false" title="Muffeia">

                    </div>
                </div>
                <h1 class="welcome-title">Welcome to Muffeia</h1>
                <p class="logo-tagline" style="font-size: 1.2rem; margin-bottom: 30px;">
                    Your Safe Emotional Space
                </p>
                
                <ul class="welcome-features">
                    <li><i class="fas fa-shield-alt"></i> Secure & Private</li>
                    <li><i class="fas fa-users"></i> Supportive Community</li>
                    <li><i class="fas fa-brain"></i> Emotional Well-being Tools</li>
                    <li><i class="fas fa-comments"></i> Positivity</li>
                    <li><i class="fas fa-chart-line"></i> Personal Growth Tracking</li>
                </ul>
                
                <div style="margin-top: 40px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <p style="font-style: italic; color: var(--gray);">
                        "Muffeia has been a sanctuary for my mental health journey. The community support is incredible."
                    </p>
                    <p style="margin-top: 10px; font-weight: 600;">-Anonymous Message</p>
                </div>
            </div>
        </div>

        <!-- Auth Container -->
        <div class="auth-container">
            <!-- Logo (Shown on all screens) -->
            <div class="logo">
                <div class="logo-icon">
                    <div class="logo-switcher" aria-hidden="false" title="Muffeia">
                        <img src="../logo/m-blues.png" alt="Muffeia" class="logo-img logo-img-1"/>
                        <img src="../logo/m-light.png" alt="Muffeia Light" class="logo-img logo-img-2"/>
                    </div>
                </div>
                <div class="logo-text">Muffeia</div>
                <div class="logo-tagline">Your Safe Emotional Space</div>
            </div>

            <!-- Auth Card -->
            <div class="auth-card">
                <!-- Tabs -->
                <div class="auth-tabs">
                    <div class="tab-slider <?php echo $is_register_mode ? 'register' : ''; ?>"></div>
                    <div class="auth-tab <?php echo !$is_register_mode ? 'active' : ''; ?>" data-tab="login">
                        <i class="fas fa-sign-in-alt"></i> <span class="tab-text">Sign In</span>
                    </div>
                    <div class="auth-tab <?php echo $is_register_mode ? 'active' : ''; ?>" data-tab="register">
                        <i class="fas fa-user-plus"></i> <span class="tab-text">Sign Up</span>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                    <div class="message success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="message error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form id="loginForm" class="auth-form <?php echo !$is_register_mode ? 'active' : ''; ?>" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="loginEmail">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="loginEmail" name="email" class="form-input" 
                               placeholder="Enter your email" value="<?php echo $preserved_email; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="loginPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div style="position: relative;">
                            <input type="password" id="loginPassword" name="password" class="form-input" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" data-target="loginPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>

                    <!-- Forgot password small button near form action -->
                    <div style="display:flex;justify-content:center;margin-top:10px;">
                        <a href="forgot_password.php" class="forgot-btn" aria-label="Forgot password">Forgot your password?</a>
                    </div>
                </form>

                <!-- Register Form -->
                <form id="registerForm" class="auth-form <?php echo $is_register_mode ? 'active' : ''; ?>" method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="registerUsername">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" id="registerUsername" name="username" class="form-input" 
                               placeholder="Choose a username" value="<?php echo $preserved_username; ?>" 
                               pattern="[a-zA-Z0-9_]{3,30}" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="registerEmail">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="registerEmail" name="email" class="form-input" 
                               placeholder="Enter your email" value="<?php echo $preserved_email; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="registerPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div style="position: relative;">
                            <input type="password" id="registerPassword" name="password" class="form-input" 
                                   placeholder="Create a strong password" required>
                            <button type="button" class="password-toggle" data-target="registerPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-bar-fill weak"></div>
                            </div>
                            <div class="strength-text weak">Weak Password</div>
                            <ul class="strength-requirements">
                                <li class="unmet"><i class="far fa-circle"></i> At least 8 characters</li>
                                <li class="unmet"><i class="far fa-circle"></i> One uppercase letter</li>
                                <li class="unmet"><i class="far fa-circle"></i> One lowercase letter</li>
                                <li class="unmet"><i class="far fa-circle"></i> One number</li>
                                <li class="unmet"><i class="far fa-circle"></i> One special character</li>
                            </ul>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn" disabled>
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>


                <!-- Footer Links -->
                <div class="auth-footer">
                    <p>
                        <?php if ($is_register_mode): ?>
                            Already have an account? <a href="#" id="switchToLogin">Sign in here</a>
                        <?php else: ?>
                            Don't have an account? <a href="#" id="switchToRegister">Sign up here</a>
                        <?php endif; ?>
                    </p>
                    <p><a href="forgot_password.php"><i class="fas fa-key"></i> Forgot your password?</a></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../index.php" class="quick-btn">
                    <i class="fas fa-home"></i> <span class="btn-text">Back to Home</span>
                </a>
                <a href="../community/about.php" class="quick-btn">
                    <i class="fas fa-info-circle"></i> <span class="btn-text">Learn More</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Screen size detection and UI adaptation
        function updateScreenSizeInfo() {
            const indicator = document.getElementById('screenSizeIndicator');
            const width = window.innerWidth;
            let size = '';
            
            if (width < 480) size = 'XS: Mobile Portrait';
            else if (width < 768) size = 'SM: Mobile Landscape/Tablet';
            else if (width < 1024) size = 'MD: Tablet';
            else if (width < 1440) size = 'LG: Desktop';
            else size = 'XL: Large Desktop';
            
            indicator.textContent = `${size} (${width}px)`;
        }

        // Initialize
        updateScreenSizeInfo();
        window.addEventListener('resize', updateScreenSizeInfo);

        // Logo Animation
        let currentLogoIndex = 0;
        const logoSlides = document.querySelectorAll('.logo-slide');
        const totalLogos = logoSlides.length;
        
        function rotateLogos() {
            if (totalLogos > 0) {
                logoSlides[currentLogoIndex].classList.remove('active');
                currentLogoIndex = (currentLogoIndex + 1) % totalLogos;
                logoSlides[currentLogoIndex].classList.add('active');
            }
        }
        
        // Start logo rotation every 3 seconds if logos exist
        if (totalLogos > 0) {
            setInterval(rotateLogos, 3000);
        }

        // Tab functionality
        const tabs = document.querySelectorAll('.auth-tab');
        const forms = document.querySelectorAll('.auth-form');
        const tabSlider = document.querySelector('.tab-slider');
        const switchToRegister = document.getElementById('switchToRegister');
        const switchToLogin = document.getElementById('switchToLogin');

        function switchTab(tabName) {
            // Update tabs
            tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabName);
            });

            // Update forms
            forms.forEach(form => {
                form.classList.toggle('active', form.id === `${tabName}Form`);
            });

            // Update slider position
            tabSlider.classList.toggle('register', tabName === 'register');

            // Clear messages
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.remove());

            // Update UI based on screen size
            updateUIForScreenSize();
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                switchTab(tab.dataset.tab);
            });
        });

        if (switchToRegister) {
            switchToRegister.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab('register');
            });
        }

        if (switchToLogin) {
            switchToLogin.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab('login');
            });
        }

        // Password toggle functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('password-toggle') || 
                e.target.parentElement.classList.contains('password-toggle')) {
                
                const toggleBtn = e.target.classList.contains('password-toggle') ? e.target : e.target.parentElement;
                const targetId = toggleBtn.dataset.target;
                const input = document.getElementById(targetId);
                const icon = toggleBtn.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            // Count met requirements
            Object.values(requirements).forEach(met => {
                if (met) strength++;
            });

            let level = 'weak';
            if (strength >= 5) {
                level = 'strong';
            } else if (strength >= 3) {
                level = 'medium';
            }

            return { level, requirements, strength };
        }

        function updatePasswordStrength(password, strengthElement) {
            const result = checkPasswordStrength(password);
            
            if (password.length === 0) {
                strengthElement.classList.remove('show');
                return;
            }

            strengthElement.classList.add('show');
            
            const strengthBar = strengthElement.querySelector('.strength-bar-fill');
            const strengthText = strengthElement.querySelector('.strength-text');
            const requirements = strengthElement.querySelectorAll('.strength-requirements li');

            // Update bar
            strengthBar.className = 'strength-bar-fill ' + result.level;
            
            // Update text
            strengthText.className = 'strength-text ' + result.level;
            strengthText.textContent = result.level.charAt(0).toUpperCase() + result.level.slice(1) + ' Password';

            // Update requirements
            const reqArray = Object.entries(result.requirements);
            requirements.forEach((li, index) => {
                if (reqArray[index][1]) {
                    li.className = 'met';
                    li.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    li.className = 'unmet';
                    li.querySelector('i').className = 'far fa-circle';
                }
            });

            // Enable/disable submit button
            const submitBtn = document.querySelector('#registerForm .submit-btn');
            if (result.level === 'weak') {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
            }
        }

        // Add password strength checker to register form
        const registerPasswordInput = document.getElementById('registerPassword');
        const strengthElement = document.querySelector('.password-strength');
        
        if (registerPasswordInput && strengthElement) {
            registerPasswordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value, strengthElement);
            });
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.classList.add('loading');
            // Form will be submitted to PHP backend
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('registerUsername').value;
            const submitBtn = this.querySelector('.submit-btn');
            
            // Validate username pattern
            if (!isValidUsername(username)) {
                e.preventDefault();
                showMessage('Username can only contain letters, numbers, and underscores (3-30 characters).', 'error');
                submitBtn.classList.remove('loading');
                return;
            }
            
            submitBtn.classList.add('loading');
            // Form will be submitted to PHP backend
        });

        function isValidUsername(username) {
            const pattern = /^[a-zA-Z0-9_]{3,30}$/;
            return pattern.test(username);
        }

        function showMessage(text, type) {
            const message = document.createElement('div');
            message.className = `message ${type}-message`;
            message.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i> ${text}`;
            
            const forms = document.querySelector('.auth-card');
            forms.insertBefore(message, forms.firstChild);
            
            setTimeout(() => {
                message.remove();
            }, 5000);
        }

        // Update UI based on screen size
        function updateUIForScreenSize() {
            const width = window.innerWidth;
            const welcomePanel = document.getElementById('welcomePanel');
            
            // Show/hide welcome panel based on screen width
            if (width < 769) {
                if (welcomePanel) welcomePanel.style.display = 'none';
            } else {
                if (welcomePanel) welcomePanel.style.display = 'flex';
            }
            
            // Adjust text visibility on small screens
            const tabTexts = document.querySelectorAll('.tab-text');
            const btnTexts = document.querySelectorAll('.btn-text');
            const socialTexts = document.querySelectorAll('.social-text');
            
            if (width < 480) {
                // On very small screens, hide some text and show only icons
                tabTexts.forEach(text => text.style.display = 'none');
                btnTexts.forEach(text => text.style.display = 'none');
                socialTexts.forEach(text => text.style.display = 'none');
            } else {
                // Show text on larger screens
                tabTexts.forEach(text => text.style.display = 'inline');
                btnTexts.forEach(text => text.style.display = 'inline');
                socialTexts.forEach(text => text.style.display = 'inline');
            }
        }

        // Initial UI update
        updateUIForScreenSize();
        window.addEventListener('resize', updateUIForScreenSize);

        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                const firstInput = activeForm.querySelector('input[type="text"], input[type="email"]');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 300);
                }
            }
        });

        // Prevent zoom on input focus on mobile
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                if (window.innerWidth < 768) {
                    window.scrollTo(0, 0);
                    document.body.style.zoom = "1.0";
                }
            });
        });

        // Touch-friendly improvements
        document.addEventListener('touchstart', function() {}, { passive: true });

        // Add loading state to buttons on click
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.disabled) {
                    this.classList.add('loading');
                }
            });
        });
    </script>
</body>
</html>