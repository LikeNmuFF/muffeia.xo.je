<?php
header('Content-Type: application/json; charset=utf-8');
// Return bad words as JSON array
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
// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode(array_values($words), JSON_UNESCAPED_UNICODE);
