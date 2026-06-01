<?php
session_start();
include '../includes/db.php';
include '../includes/email_verification.php';

$message = '';
$error = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!empty($token)) {
    $verified_user_id = verifyEmailToken($conn, $token);
    if ($verified_user_id) {
        $message = "✓ Email verified successfully! Your account is now active.";
    } else {
        $error = "✗ Invalid or expired verification link. Please try again.";
    }
}

// Handle resend
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'resend') {
    if (!isset($_SESSION['user_id'])) {
        $error = "You must be logged in to resend verification.";
    } else {
        if (resendVerificationEmail($conn, $_SESSION['user_id'])) {
            $message = "✓ Verification email sent! Check your inbox.";
        } else {
            $error = "✗ Error sending verification email. Try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - MUFFEIA</title>
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <style>
        :root {
            --primary: #6366f1;
            --success: #22c55e;
            --danger: #ef4444;
            --border: #e2e8f0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .verification-icon {
            text-align: center;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h1 {
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
            font-weight: 700;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .info-text {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin: 20px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button, a.btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-secondary {
            background: var(--border);
            color: var(--primary);
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .loading {
            text-align: center;
            font-size: 14px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if (!empty($message)): ?>
            <div class="verification-icon">✓</div>
            <h1>Success!</h1>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <div class="info-text">Your account is now fully activated. You can start sharing and helping the community!</div>
            <div class="action-buttons">
                <a href="../index.php" class="btn btn-primary">Go to Dashboard</a>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="verification-icon">✕</div>
            <h1>Verification Failed</h1>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <div class="info-text">The verification link may have expired. Please request a new one below.</div>
            <div class="action-buttons">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn btn-primary">Resend Email</button>
                </form>
                <a href="../index.php" class="btn btn-secondary">Back</a>
            </div>
        <?php elseif (!empty($token)): ?>
            <div class="verification-icon">⏳</div>
            <h1>Verifying...</h1>
            <div class="loading">Processing your verification...</div>
        <?php else: ?>
            <div class="verification-icon">✉️</div>
            <h1>Email Verification</h1>
            <div class="info-text">
                <?php if (isset($_SESSION['user_id'])): ?>
                    Click the button below to resend the verification email to your inbox.
                <?php else: ?>
                    Check your email for a verification link. Click it to activate your account.
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="action" value="resend">
                        <button type="submit" class="btn btn-primary">Resend Verification Email</button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="action-buttons">
                <a href="../index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
