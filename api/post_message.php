<?php
include '../includes/db.php';

session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];
    $is_anonymous = isset($_POST['anonymous']) ? 1 : 0; // Checkbox for anonymous posting

    // Insert the message into the database
    $sql = "INSERT INTO messages (user_id, content, is_anonymous) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $content, $is_anonymous);
    $stmt->execute();

    header("Location: messages.php"); // Redirect to message list page
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post a Message - CrowdSolve</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'templates/header.php'; ?>
    <div class="container">
        <h2>Post a New Message</h2>
        <form action="post_message.php" method="post">
            <textarea name="content" rows="5" placeholder="Write your message here..." required></textarea>
            <div>
                <input type="checkbox" id="anonymous" name="anonymous">
                <label for="anonymous">Post anonymously</label>
            </div>
            <button type="submit">Post Message</button>
        </form>
    </div>
    <script src="js/scripts.js"></script>
</body>
</html>
