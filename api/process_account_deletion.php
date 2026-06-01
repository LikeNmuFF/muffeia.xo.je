<?php
include 'includes/db.php';

$sql = "SELECT * FROM account_deletions WHERE status = 'pending' AND scheduled_time <= NOW()";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($deletion = $result->fetch_assoc()) {
    $user_id = $deletion['user_id'];
    
    $conn->begin_transaction();
    
    try {
        $tables = [
            'post_likes', 'post_shares', 'problem_views', 'solution_reactions', 
            'solution_replies', 'solutions', 'problems', 'messages', 'conversations',
            'notifications', 'account_deletions', 'password_resets', 'contact_submissions'
        ];
        
        foreach ($tables as $table) {
            $del_stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
            $del_stmt->bind_param("i", $user_id);
            $del_stmt->execute();
            $del_stmt->close();
        }
        
        $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del_user->bind_param("i", $user_id);
        $del_user->execute();
        $del_user->close();
        
        $upd_stmt = $conn->prepare("UPDATE account_deletions SET status = 'completed' WHERE id = ?");
        $upd_stmt->bind_param("i", $deletion['id']);
        $upd_stmt->execute();
        $upd_stmt->close();
        
        $conn->commit();
        
        echo "User $user_id deleted successfully.\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error deleting user $user_id: " . $e->getMessage() . "\n";
    }
}

echo "Account deletion processing completed.\n";
?>