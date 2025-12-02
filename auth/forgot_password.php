<?php
include '../includes/db.php';
session_start();

// Set timezone to UTC for consistency
date_default_timezone_set('UTC');
$conn->query("SET time_zone = '+00:00'");

$error = '';
$success = '';

// Include PHPMailer - Choose the correct path for your setup
// Option 1: If using Composer (recommended)
require_once '../vendor/autoload.php';

// Option 2: If manually installed in PHPMailer folder
// require_once '../PHPMailer/src/Exception.php';
// require_once '../PHPMailer/src/PHPMailer.php';
// require_once '../PHPMailer/src/SMTP.php';

// Option 3: If installed in vendor/phpmailer folder
// require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
// require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
// require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $sql = "SELECT id, username FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate unique token
            $token = bin2hex(random_bytes(50));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now
            
            // Ensure password_resets table exists
            $create_table_sql = "CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(100) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (email),
                INDEX (token)
            )";
            
            if (!$conn->query($create_table_sql)) {
                error_log("Error creating password_resets table: " . $conn->error);
                $error = 'Database configuration error. Please try again.';
            } else {
                // Delete any existing tokens for this email
                $delete_sql = "DELETE FROM password_resets WHERE email = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                
                // Insert new token
                $insert_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sss", $email, $token, $expires);
                
                if ($insert_stmt->execute()) {
                    // Send email with reset link using PHPMailer
                    $reset_link = "https://muffeia.xo.je/auth/reset_password.php?token=" . $token;
                    
                    if (sendResetEmail($email, $user['username'], $reset_link)) {
                        $success = "Password reset link has been sent to your email! Check your inbox (and spam folder).";
                    } else {
                        // Fallback: Show reset link directly if email fails
                        $success = "Email service temporarily unavailable. Here's your reset link (valid for 1 hour):<br>
                                   <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 10px 0; word-break: break-all;'>
                                   <a href='$reset_link' style='color: #7c3aed;'>$reset_link</a>
                                   </div>";
                    }
                } else {
                    $error = 'Error generating reset token. Please try again.';
                }
                
                $insert_stmt->close();
                $delete_stmt->close();
            }
            
            $stmt->close();
        } else {
            $error = 'No account found with that email address.';
        }
    }
}

/**
 * Send password reset email using PHPMailer with Gmail SMTP
 */
function sendResetEmail($to_email, $username, $reset_link) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings for Gmail SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'muff.muffeia@gmail.com';
        $mail->Password = 'kgzgrstnatsjmwbl'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Enable verbose debug output (optional - disable in production)
        // $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client and server messages
        
        // Recipients
        $mail->setFrom('muff.muffeia@gmail.com', 'MUFFEIA Support');
        $mail->addAddress($to_email, $username);
        $mail->addReplyTo('muff.muffeia@gmail.com', 'MUFFEIA');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'MUFFEIA - Password Reset Request';
        
        // HTML Email Body
        $email_body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600;
                }
                .content { 
                    padding: 30px; 
                }
                .field { 
                    margin-bottom: 20px; 
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eee;
                }
                .field:last-child {
                    border-bottom: none;
                }
                .field-label { 
                    font-weight: 600; 
                    color: #7c3aed; 
                    display: block;
                    margin-bottom: 5px;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .field-value {
                    color: #555;
                    font-size: 16px;
                }
                .message-box { 
                    background: #f8fafc; 
                    padding: 20px; 
                    border: 1px solid #e2e8f0; 
                    border-radius: 8px; 
                    margin: 15px 0; 
                    white-space: pre-wrap;
                    font-family: inherit;
                }
                .footer { 
                    background: #f1f5f9; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #64748b; 
                    border-top: 1px solid #e2e8f0;
                }
                .submission-id {
                    background: #7c3aed;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .reset-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
                    color: white;
                    padding: 14px 28px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    margin: 20px 0;
                    text-align: center;
                }
                .warning-box {
                    background: #fef3c7;
                    border-left: 4px solid #f59e0b;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .code-block {
                    background: #f1f5f9;
                    padding: 12px;
                    border-radius: 6px;
                    font-family: monospace;
                    word-break: break-all;
                    margin: 10px 0;
                    border: 1px solid #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>MUFFEIA</h1>
                    <p>Password Reset Request</p>
                </div>
                <div class='content'>
                    <h3>Hello $username,</h3>
                    <p>You requested to reset your password for your MUFFEIA account.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='$reset_link' class='reset-button'>Reset Your Password</a>
                    </div>
                    
                    <p>Or copy and paste this link in your browser:</p>
                    <div class='code-block'>$reset_link</div>
                    
                    <div class='warning-box'>
                        <strong>Important:</strong> This password reset link will expire in 1 hour.<br>
                        If you didn't request this reset, please ignore this email and your password will remain unchanged.
                    </div>
                    
                    <p>For security reasons, this link can only be used once and will expire after 1 hour.</p>
                </div>
                <div class='footer'>
                    <p><strong>This email was automatically generated from the Muffeia platform.</strong></p>
                    <p>Please do not reply to this email. If you need assistance, contact our support team.</p>
                    <p style='margin-top: 15px; font-size: 11px; color: #94a3b8;'>
                        Muffeia &copy; " . date('Y') . " | Creating safer online communities
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $email_body;
        
        // Plain text version for email clients that don't support HTML
        $mail->AltBody = "
MUFFEIA - Password Reset Request
================================

Hello $username,

You requested to reset your password for your MUFFEIA account.

To reset your password, please visit the following link:
$reset_link

This link will expire in 1 hour.

If you didn't request this password reset, please ignore this email.

For security reasons, this link can only be used once.

--
MUFFEIA Support
Creating safer online communities
        ";
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUFFEIA - Forgot Password</title>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --primary-light: #8b5cf6;
            --secondary: #f59e0b;
            --secondary-light: #fbbf24;
            --accent: #10b981;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
            min-height: 100vh;
            color: var(--light);
            overflow-x: hidden;
            position: relative;
        }

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

        .floating-element:nth-child(odd) {
            animation-name: float;
        }

        .floating-element:nth-child(even) {
            animation-name: float-reverse;
        }

        .floating-element:nth-child(1) {
            width: 250px;
            height: 250px;
            background: var(--primary);
            top: -125px;
            right: -125px;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 200px;
            height: 200px;
            background: var(--primary-light);
            bottom: -100px;
            left: -100px;
            animation-delay: 1s;
        }

        .floating-element:nth-child(3) {
            width: 150px;
            height: 150px;
            background: var(--secondary);
            top: 20%;
            left: 10%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(4) {
            width: 100px;
            height: 100px;
            background: var(--accent);
            bottom: 30%;
            right: 15%;
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        @keyframes float-reverse {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(20px) rotate(-5deg); }
        }

        .auth-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideInUp 0.8s ease-out;
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

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--light);
            text-align: center;
        }

        .subtitle {
            color: var(--gray);
            text-align: center;
            margin-bottom: 32px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--light);
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.05);
            color: var(--light);
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .form-group input::placeholder {
            color: var(--gray);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.6);
        }

        .back-link {
            text-align: center;
        }

        .back-link a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--primary);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .inline-link {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            word-break: break-all;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .inline-link a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }

        .inline-link a:hover {
            color: var(--primary);
        }

        @media (max-width: 576px) {
            .auth-container {
                padding: 30px 20px;
            }
            
            .brand-logo {
                font-size: 2rem;
            }
            
            .title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background floating elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    
    <div class="auth-page">
        <div class="auth-container">
            <div class="brand">
                <div class="brand-logo">MUFFEIA</div>
                <h1 class="title">Reset Password</h1>
                <p class="subtitle">Enter your email to receive a password reset link</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <?php 
                        // Check if success message contains a URL (fallback mode)
                        if (strpos($success, 'http') !== false) {
                            echo "Email service temporarily unavailable. Here's your reset link:";
                            echo '<div class="inline-link">';
                            // Extract URL from message
                            preg_match('/https?:\/\/[^\s<>"]+/', $success, $matches);
                            if (!empty($matches[0])) {
                                echo '<a href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a>';
                            }
                            echo '</div>';
                            echo '<small style="color: var(--gray);">(This link is valid for 1 hour)</small>';
                        } else {
                            echo htmlspecialchars($success);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>