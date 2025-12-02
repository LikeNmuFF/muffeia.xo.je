<?php
include 'includes/db.php';

if (isset($_GET['query'])) {
    $query = "%" . $_GET['query'] . "%";
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username LIKE ?");
    $stmt->bind_param("s", $query);
    $stmt->execute();
    $results = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - CrowdSolve</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Search Results</h2>
    <?php if ($results->num_rows > 0): ?>
        <ul>
            <?php while ($user = $results->fetch_assoc()): ?>
                <li>
                    <a href="profile.php?id=<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No users found.</p>
    <?php endif; ?>
</body>
</html>
