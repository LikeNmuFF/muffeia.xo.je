<?php
header("Content-Type: application/xml; charset=utf-8");
include 'includes/db.php';

$base_url = "https://muffeia.xo.je";

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Static pages
$static_pages = [
    '/',
    '/community/about.php',
    '/community/guidelines.php',
    '/community/privacy.php',
    '/community/resources.php',
    '/community/contact.php',
    '/auth/login.php'
];

foreach ($static_pages as $page) {
    echo '<url>';
    echo '<loc>' . $base_url . $page . '</loc>';
    echo '<changefreq>weekly</changefreq>';
    echo '<priority>0.8</priority>';
    echo '</url>';
}

// Dynamic problem pages
$sql = "SELECT id, created_at FROM problems ORDER BY created_at DESC LIMIT 1000";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<url>';
        echo '<loc>' . $base_url . '/pages/view_problem.php?problem_id=' . $row['id'] . '</loc>';
        echo '<lastmod>' . date('Y-m-d', strtotime($row['created_at'])) . '</lastmod>';
        echo '<changefreq>monthly</changefreq>';
        echo '<priority>0.6</priority>';
        echo '</url>';
    }
}

echo '</urlset>';
?>
