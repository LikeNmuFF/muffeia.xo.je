<?php
// Check if this is a valid page request
$valid_pages = ['login', 'register', 'forgot-password', 'index', 'message', 'api']; // Add your valid pages

$current_page = basename($_SERVER['PHP_SELF'], '.php');
if (!in_array($current_page, $valid_pages) && $current_page != 'index') {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit();
}
?>