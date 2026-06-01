<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$path = __DIR__ . '/../includes/badwords.txt';
$words = [];
if (is_readable($path)) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $words[] = $line;
    }
}
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($words, JSON_UNESCAPED_UNICODE);
