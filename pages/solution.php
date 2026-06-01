<?php
include '../includes/db.php';

$solution_id = $_GET['id'];

$query = "SELECT s.solution_text, p.description 
          FROM solutions s 
          JOIN problems p ON s.problem_id = p.id 
          WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $solution_id);
$stmt->execute();
$result = $stmt->get_result();
$solution = $result->fetch_assoc();
$stmt->close();

if ($solution): ?>
    <h1>Solution</h1>
    <p><strong>Problem:</strong> <?php echo nl2br(htmlspecialchars($solution['description'])); ?></p>
    <p><strong>Solution:</strong> <?php echo htmlspecialchars($solution['solution_text']); ?></p>
<?php else: ?>
    <p>Solution not found.</p>
<?php endif; ?>
