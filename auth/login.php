<?php
include '../includes/db.php';
include '../includes/security.php';
require '../vendor/autoload.php';
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
        } elseif (empty($_GET['state']) || !hash_equals($_SESSION['oauth2state'] ?? '', $_GET['state'])) {
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
        $error = 'Social login failed. Please try again.';
        error_log("OAuth error: " . $e->getMessage());
    }
}

function processSocialLogin($userData, $provider, $conn) {
    $email = $userData['email'] ?? ($userData['email_address'] ?? '');
    $name = $userData['name'] ?? ($userData['displayName'] ?? '');
    $socialId = $userData['id'] ?? '';
    
    if (empty($email)) {
        $_SESSION['error'] = 'Could not retrieve email from ' . ucfirst($provider) . '. Please ensure your email is public in your social account settings.';
        header("Location: login.php");
        exit();
    }

    // Check if user exists by email or by social provider ID
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
        
        // If user exists by email but social_logins entry is missing, we'll link it below
    } else {
        // Create new user if not found
        $username = generateUsernameFromEmail($email);
        // Ensure username is unique
        $check_username_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        if ($check_username_stmt->get_result()->num_rows > 0) {
            $username .= '_' . rand(100, 999);
        }
        $check_username_stmt->close();

        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, email_verified) VALUES (?, ?, '', 1)");
        $stmt->bind_param("ss", $username, $email);
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
        } else {
            $_SESSION['error'] = 'Failed to create user account. Please try standard registration.';
            header("Location: login.php");
            exit();
        }
    }

    // Link or Update social login info
    $stmt = $conn->prepare("INSERT INTO social_logins (user_id, provider, social_id, social_data) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE social_data = ?");
    $socialData = json_encode($userData);
    $stmt->bind_param("issss", $userId, $provider, $socialId, $socialData, $socialData);
    $stmt->execute();

    // Set session
    session_regenerate_id(true);
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
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: ../index.php");
                    exit();
                }
            }
            $error = 'Invalid email or password.';
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
                            $error = "Registration failed. Please try again.";
                            error_log("Registration error: " . $conn->error);
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
    <title>Muffeia — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <img src="../logo/m-light.png" alt="Muffeia" style="height:42px;">
                <span>Muffeia</span>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab <?php echo !$is_register_mode ? 'active' : ''; ?>" data-tab="login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                <button class="auth-tab <?php echo $is_register_mode ? 'active' : ''; ?>" data-tab="register">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php elseif ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="auth-forms">
                <form id="loginForm" class="auth-form <?php echo !$is_register_mode ? 'active' : ''; ?>" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" class="form-input"
                               placeholder="you@example.com" value="<?php echo $preserved_email; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="loginPassword" name="password" class="form-input"
                                   placeholder="Enter your password" required>
                            <i class="fas fa-eye password-toggle" data-target="loginPassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <form id="registerForm" class="auth-form <?php echo $is_register_mode ? 'active' : ''; ?>" method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="registerUsername">Username</label>
                        <input type="text" id="registerUsername" name="username" class="form-input"
                               placeholder="Choose a username" pattern="[a-zA-Z0-9_ ]{3,30}"
                               title="Letters, numbers, underscores, spaces (3-30 characters)"
                               value="<?php echo $preserved_username; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="registerEmail">Email</label>
                        <input type="email" id="registerEmail" name="email" class="form-input"
                               placeholder="you@example.com" value="<?php echo $preserved_email; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="registerPassword">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="registerPassword" name="password" class="form-input"
                                   placeholder="Create a strong password" required>
                            <i class="fas fa-eye password-toggle" data-target="registerPassword"></i>
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

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;" disabled>
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
            </div>

            <div class="social-login">
                <p>or continue with</p>
                <div class="social-buttons">
                    <a href="login.php?provider=google" class="social-btn google" title="Continue with Google">
                        <i class="fab fa-google"></i> Google
                    </a>
                    <a href="login.php?provider=facebook" class="social-btn facebook" title="Continue with Facebook">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                </div>
            </div>

            <div class="form-footer">
                <p id="toggleFormText">Don't have an account? <a href="#" id="switchToRegister">Sign up</a></p>
                <p><a href="forgot_password.php" style="font-weight:400;">Forgot password?</a></p>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        const tabs = document.querySelectorAll('.auth-tab');
        const forms = document.querySelectorAll('.auth-form');
        const switchToRegister = document.getElementById('switchToRegister');
        const switchToLogin = document.getElementById('switchToLogin');

        function switchTab(tabName) {
            tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabName));
            forms.forEach(form => form.classList.toggle('active', form.id === `${tabName}Form`));
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                switchTab(tab.dataset.tab);
                const formText = document.getElementById('toggleFormText');
                if (tab.dataset.tab === 'register') {
                    formText.innerHTML = 'Already have an account? <a href="#" id="switchToLogin">Sign in</a>';
                } else {
                    formText.innerHTML = 'Don\'t have an account? <a href="#" id="switchToRegister">Sign up</a>';
                }
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

        // Password toggle
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('password-toggle')) {
                const targetId = e.target.dataset.target;
                const input = document.getElementById(targetId);
                if (input) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        e.target.classList.remove('fa-eye');
                        e.target.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        e.target.classList.remove('fa-eye-slash');
                        e.target.classList.add('fa-eye');
                    }
                }
            }
        });

        // Password strength checker
        let currentPasswordStrength = 'weak';

        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            Object.values(requirements).forEach(met => { if (met) strength++; });

            let level = 'weak';
            if (strength >= 5) level = 'very-strong';
            else if (strength >= 3) level = 'medium';
            else if (strength >= 2) level = 'medium';

            return { level, requirements, strength };
        }

        function updatePasswordStrength(password, strengthElement) {
            const result = checkPasswordStrength(password);
            currentPasswordStrength = result.level;

            if (password.length === 0) return;

            const strengthBar = strengthElement.querySelector('.strength-bar-fill');
            const strengthText = strengthElement.querySelector('.strength-text');
            const requirements = strengthElement.querySelectorAll('.strength-requirements li');

            strengthBar.className = 'strength-bar-fill ' + result.level;
            strengthText.className = 'strength-text ' + result.level;
            strengthText.textContent = result.level.charAt(0).toUpperCase() + result.level.slice(1) + ' Password';

            const reqArray = Object.entries(result.requirements);
            requirements.forEach((li, index) => {
                if (index < reqArray.length && reqArray[index][1]) {
                    li.className = 'met';
                    li.querySelector('i').className = 'fas fa-check-circle';
                } else if (index < reqArray.length) {
                    li.className = 'unmet';
                    li.querySelector('i').className = 'far fa-circle';
                }
            });

            const submitBtn = document.querySelector('#registerForm button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = result.level === 'weak';
            }
        }

        const registerPasswordInput = document.getElementById('registerPassword');
        const strengthElement = document.querySelector('.password-strength');

        if (registerPasswordInput && strengthElement) {
            registerPasswordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value, strengthElement);
            });
        }

        // Touch enhancements
        document.addEventListener('touchstart', function() {}, { passive: true });
    </script>
</body>
</html>