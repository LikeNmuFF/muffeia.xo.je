<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$valid_categories = ['feature', 'bug', 'improvement', 'feedback'];
if (!in_array($category, $valid_categories)) {
    $category = 'feedback';
}

$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'name' => htmlspecialchars(mb_substr($name, 0, 100)),
    'email' => htmlspecialchars(mb_substr($email, 0, 255)),
    'category' => $category,
    'message' => htmlspecialchars(mb_substr($message, 0, 5000))
];

$file = __DIR__ . '/../data/suggestions.json';
$dir = dirname($file);

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$suggestions = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $suggestions = json_decode($content, true);
    if (!is_array($suggestions)) {
        $suggestions = [];
    }
}

$suggestions[] = $entry;

if (file_put_contents($file, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not save your suggestion. Please try again.']);
}
