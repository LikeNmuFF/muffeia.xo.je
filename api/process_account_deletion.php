<?php
include 'includes/db.php';

// This script should be run via cron job every hour
// Or you can call it manually to process pending deletions

$sql = "SELECT * FROM account_deletions WHERE status = 'pending' AND scheduled_time <= NOW()";
$result = $conn->query($sql);

while ($deletion = $result->fetch_assoc()) {
    $user_id = $deletion['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete user's data from all related tables
        $tables = [
            'post_likes', 'post_shares', 'problem_views', 'solution_reactions', 
            'solution_replies', 'solutions', 'problems', 'messages', 'conversations',
            'notifications', 'account_deletions', 'password_resets', 'contact_submissions'
        ];
        
        foreach ($tables as $table) {
            $conn->query("DELETE FROM $table WHERE user_id = $user_id");
        }
        
        // Finally delete the user
        $conn->query("DELETE FROM users WHERE id = $user_id");
        
        // Update deletion status
        $conn->query("UPDATE account_deletions SET status = 'completed' WHERE id = {$deletion['id']}");
        
        $conn->commit();
        
        echo "User $user_id deleted successfully.\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error deleting user $user_id: " . $e->getMessage() . "\n";
    }
}

echo "Account deletion processing completed.\n";
?>