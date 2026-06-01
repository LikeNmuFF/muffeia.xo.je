<?php
/**
 * Email Verification System
 * Handles user email verification flow
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Generate email verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $username, $token) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USERNAME');
        $mail->Password = getenv('MAIL_PASSWORD');
        
        if (!$mail->Username || !$mail->Password) {
            error_log("Email credentials not configured. Set MAIL_USERNAME and MAIL_PASSWORD env vars.");
            return false;
        }
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Recipients
        $mail->setFrom('muff.muffeia@gmail.com', 'MUFFEIA');
        $mail->addAddress($email, $username);
        
        // Content
        $verification_url = "https://muffeia.xo.je/verify_email.php?token=" . urlencode($token);
        
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your MUFFEIA Email';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Welcome to MUFFEIA!</h2>
                    <p>Hi $username,</p>
                    <p>Thank you for signing up. Please verify your email address to activate your account.</p>
                    <p><a href='$verification_url' class='button'>Verify Email</a></p>
                    <p>Or copy this link: $verification_url</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>Best regards,<br>The MUFFEIA Team</p>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Please verify your email by visiting: $verification_url";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create email verification record
 */
function createEmailVerification($conn, $user_id, $email) {
    $token = generateVerificationToken();
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expires_at);
    $stmt->execute();
    $stmt->close();
    
    // Send verification email
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $username = $stmt->get_result()->fetch_assoc()['username'];
    $stmt->close();
    return sendVerificationEmail($email, $username, $token);
}

/**
 * Verify email with token
 */
function verifyEmailToken($conn, $token) {
    $stmt = $conn->prepare("
        SELECT u.id, ev.user_id FROM email_verifications ev
        JOIN users u ON ev.user_id = u.id
        WHERE ev.token = ? AND ev.verified_at IS NULL AND ev.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result) {
        return false;
    }
    
    $user_id = $result['user_id'];
    
    // Mark as verified
    $update_stmt = $conn->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE token = ?");
    $update_stmt->bind_param("s", $token);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Update user as email verified
    $user_update = $conn->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
    $user_update->bind_param("i", $user_id);
    $user_update->execute();
    $user_update->close();
    
    return $user_id;
}

/**
 * Resend verification email
 */
function resendVerificationEmail($conn, $user_id) {
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        return false;
    }
    
    // Delete old token
    $del_stmt = $conn->prepare("DELETE FROM email_verifications WHERE user_id = ? AND verified_at IS NULL");
    $del_stmt->bind_param("i", $user_id);
    $del_stmt->execute();
    $del_stmt->close();
    
    // Create and send new token
    return createEmailVerification($conn, $user_id, $user['email']);
}

/**
 * Check if user email is verified
 */
function isEmailVerified($conn, $user_id) {
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['email_verified'] ?? false;
}

/**
 * Get pending verification status
 */
function getPendingVerificationStatus($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT created_at, expires_at FROM email_verifications
        WHERE user_id = ? AND verified_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}
?>
