<?php
// header.php
include 'db.php';
session_start();
$success = '';
$error = '';
$is_register_mode = false;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Contact form specific variables
$contact_success = '';
$contact_error = '';
$form_name = $form_email = $form_subject = $form_message = '';

// Handle login/registration
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

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'contact') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $contact_error = 'Security token validation failed. Please try again.';
    } else {
        // Basic form validation
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        // Store form values for repopulation
        $form_name = htmlspecialchars($name);
        $form_email = htmlspecialchars($email);
        $form_subject = htmlspecialchars($subject);
        $form_message = htmlspecialchars($message);
        
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $contact_error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contact_error = 'Please enter a valid email address.';
        } else {
            // Sanitize inputs for database
            $name_clean = $conn->real_escape_string($name);
            $email_clean = $conn->real_escape_string($email);
            $subject_clean = $conn->real_escape_string($subject);
            $message_clean = $conn->real_escape_string($message);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $created_at = date('Y-m-d H:i:s');
            
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Save to database
                $stmt = $conn->prepare("INSERT INTO contact_submissions (name, email, subject, message, ip_address, user_agent, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
                $stmt->bind_param("sssssss", $name_clean, $email_clean, $subject_clean, $message_clean, $ip_address, $user_agent, $created_at);
                
                if ($stmt->execute()) {
                    $submission_id = $stmt->insert_id;
                    
                    // Send email notification using PHPMailer
                    if (sendContactEmail($name, $email, $subject, $message, $submission_id)) {
                        $conn->commit();
                        $contact_success = 'Thank you for your message! We will get back to you within 2 business days.';
                        
                        // Clear form fields after successful submission
                        $form_name = $form_email = $form_subject = $form_message = '';
                    } else {
                        // Email failed but database was saved
                        $conn->commit(); // Still commit the database entry
                        $contact_success = 'Thank you for your message! We have received your submission (ID: #' . $submission_id . '). There was an issue with our email system, but we will still respond to your inquiry.';
                        $form_name = $form_email = $form_subject = $form_message = '';
                    }
                } else {
                    throw new Exception("Failed to save to database: " . $conn->error);
                }
                
                $stmt->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $contact_error = 'Sorry, there was an error processing your request. Please try again later.';
                error_log("Contact form error: " . $e->getMessage());
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

/**
 * Send email notification using PHPMailer
 */
function sendContactEmail($name, $email, $subject, $message, $submission_id) {
    // Import PHPMailer classes
    require_once '../vendor/autoload.php'; // Path to PHPMailer autoload
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings for Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'muff.muffeia@gmail.com'; // Your Gmail address
        $mail->Password = 'kgzgrstnatsjmwbl'; 
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Enable debugging (optional)
        // $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client and server messages
        
        // Recipients
        $mail->setFrom('muff.muffeia@gmail.com', 'Muffeia Support');
        $mail->addAddress('muff.muffeia@gmail.com', 'Muffeia Team'); // Send to yourself
        $mail->addReplyTo($email, $name); // Allow replying directly to the user
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Contact Form Submission: $subject (ID: #$submission_id)";
        
        // HTML Email Body
        $email_body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>New Contact Submission</title>
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
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📧 New Contact Form Submission</h1>
                    <p>Muffeia Support System</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <span class='submission-id'>Submission ID: #$submission_id</span>
                    </div>
                    
                    <div class='field'>
                        <span class='field-label'>From</span>
                        <span class='field-value'>$name &lt;$email&gt;</span>
                    </div>
                    
                    <div class='field'>
                        <span class='field-label'>Subject</span>
                        <span class='field-value'>$subject</span>
                    </div>
                    
                    <div class='field'>
                        <span class='field-label'>Submitted</span>
                        <span class='field-value'>" . date('F j, Y \a\t g:i A') . "</span>
                    </div>
                    
                    <div class='field'>
                        <span class='field-label'>Message</span>
                        <div class='message-box'>" . nl2br(htmlspecialchars($message)) . "</div>
                    </div>
                    
                    <div class='field'>
                        <span class='field-label'>IP Address</span>
                        <span class='field-value'>" . $_SERVER['REMOTE_ADDR'] . "</span>
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>This email was automatically generated from the Muffeia contact form.</strong></p>
                    <p>⚠️ Please do not reply to this email. Respond directly to: <strong>$email</strong></p>
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
NEW CONTACT FORM SUBMISSION - Muffeia
=====================================

Submission ID: #$submission_id
From: $name <$email>
Subject: $subject
Submitted: " . date('F j, Y \a\t g:i A') . "

Message:
--------
$message

IP Address: " . $_SERVER['REMOTE_ADDR'] . "

Please respond directly to: $email

This email was automatically generated from the Muffeia contact form.
        ";
        
        // Send email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error
        error_log("PHPMailer Error [Submission #$submission_id]: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Muffeia'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/auth.css">
    <link rel='stylesheet' href='../css/responsive.css'>
    <link rel="icon" href="../logo/m-blues.png" type="image/png">
</head>
<body>
    <!-- Floating background elements -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>

    <div class="container">
        <header>
            <div class="logo">
               
                Muffeia
            </div>
            <nav>
                <ul>
                 
                    <li><a href="about.php" <?php echo basename($_SERVER['PHP_SELF']) == 'about' ? 'class="active"' : ''; ?>>About</a></li>
                    <li><a href="guidelines.php" <?php echo basename($_SERVER['PHP_SELF']) == 'guidelines' ? 'class="active"' : ''; ?>>Community</a></li>
                    <li><a href="resources.php" <?php echo basename($_SERVER['PHP_SELF']) == 'resources' ? 'class="active"' : ''; ?>>Resources</a></li>
                    <li><a href="contact.php" <?php echo basename($_SERVER['PHP_SELF']) == 'contact' ? 'class="active"' : ''; ?>>Contact</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <button class="btn btn-secondary" id="loginBtn" onclick="window.location.href='../auth/login.php'">Join Community</button>
                <button class="btn btn-primary" id="contact" onclick="window.location.href='contact.php'">Message Us</button>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </header>