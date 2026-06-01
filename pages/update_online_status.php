<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$user_id = $_SESSION['user_id'];

// Update user's online status and last seen timestamp
$sql = "UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Clean up old online statuses (mark as offline if last update was more than 5 minutes ago)
$cleanup_sql = "UPDATE users SET is_online = FALSE WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$conn->query($cleanup_sql);

// Get online status for a specific user (for AJAX requests)
if (isset($_GET['user_id'])) {
    $other_user_id = intval($_GET['user_id']);
    $status_sql = "SELECT is_online, last_seen FROM users WHERE id = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("i", $other_user_id);
    $status_stmt->execute();
    $result = $status_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        echo json_encode([
            'is_online' => $user_data['is_online'],
            'last_seen' => $user_data['last_seen'],
            'status_text' => getStatusText($user_data['is_online'], $user_data['last_seen'])
        ]);
    }
    exit();
}

function getStatusText($is_online, $last_seen) {
    if ($is_online) {
        return 'Online';
    } else {
        $last_seen_time = strtotime($last_seen);
        $now = time();
        $diff = $now - $last_seen_time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' min ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}
?>