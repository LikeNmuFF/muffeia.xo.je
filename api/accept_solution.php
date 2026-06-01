<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

require_once '../includes/db.php';

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$user_id = $_SESSION['user_id'];
$solution_id = isset($_POST['solution_id']) ? intval($_POST['solution_id']) : 0;

if ($solution_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid solution ID']);
    exit();
}

// Check solution exists and get problem owner
$stmt = $conn->prepare("SELECT s.id, s.user_id as solution_author_id, s.problem_id, p.user_id as problem_author_id, s.is_accepted FROM solutions s JOIN problems p ON s.problem_id = p.id WHERE s.id = ?");
$stmt->bind_param("i", $solution_id);
$stmt->execute();
$result = $stmt->get_result();
$solution = $result->fetch_assoc();
$stmt->close();

if (!$solution) {
    echo json_encode(['success' => false, 'message' => 'Solution not found']);
    exit();
}

// Only the problem author can accept a solution
if ($solution['problem_author_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Only the post author can accept a solution']);
    exit();
}

// Toggle: if already accepted, unaccept
$new_status = $solution['is_accepted'] ? 0 : 1;

$update = $conn->prepare("UPDATE solutions SET is_accepted = ? WHERE id = ?");
$update->bind_param("ii", $new_status, $solution_id);
$update->execute();
$update->close();

// Award reputation if accepting (not un-accepting)
if ($new_status && $solution['solution_author_id'] != $user_id) {
    $rep = $conn->prepare("UPDATE users SET reputation_score = reputation_score + 25 WHERE id = ?");
    $rep->bind_param("i", $solution['solution_author_id']);
    $rep->execute();
    $rep->close();

    // Notify solution author
    $notif = $conn->prepare("INSERT INTO notifications (user_id, message, target_url, created_at, is_read) VALUES (?, 'Your solution was accepted!', 'pages/view_problem.php?problem_id=" . $solution['problem_id'] . "', NOW(), 0)");
    $notif->bind_param("i", $solution['solution_author_id']);
    $notif->execute();
    $notif->close();
}

echo json_encode([
    'success' => true,
    'accepted' => (bool)$new_status,
    'message' => $new_status ? 'Solution accepted!' : 'Solution unaccepted'
]);
