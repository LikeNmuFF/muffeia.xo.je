<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

include '../includes/db.php';
include '../includes/security.php';
session_start();

// Generate CSRF token if needed
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Security token validation failed. Please refresh and try again.']);
    exit;
}

$action = $_POST['action'];

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            echo json_encode(['success' => true, 'redirect' => '../index.php']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    // Validate username
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters long.']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores.']);
        exit;
    }

    // Password strength
    $pwErrors = [];
    if (strlen($password) < 8) $pwErrors[] = 'at least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) $pwErrors[] = 'an uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $pwErrors[] = 'a lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $pwErrors[] = 'a number';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $pwErrors[] = 'a special character';
    if (!empty($pwErrors)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain ' . implode(', ', $pwErrors) . '.']);
        exit;
    }

    // Check existing user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email or Username already exists.']);
        exit;
    }
    $stmt->close();

    // Insert new user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hash);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registration successful! Please sign in.', 'switchTab' => 'login']);
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
