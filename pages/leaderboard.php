<?php
session_start();
include '../includes/db.php';
include '../includes/reputation.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

// Get leaderboard
$leaderboard = getLeaderboard($conn, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - MUFFEIA</title>
    <link rel="stylesheet" href="../css/forall.css">
    <link rel="stylesheet" href="../css/muffeia-ui.css">
    <style>
        :root {
            --primary: #6366f1;
            --border: #e2e8f0;
            --text: #1e293b;
            --bg: #f8fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        
        .leaderboard-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .leaderboard-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .leaderboard-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .leaderboard-header p {
            color: #64748b;
            font-size: 16px;
        }
        
        .leaderboard-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead tr {
            background: var(--bg);
            border-bottom: 2px solid var(--border);
        }
        
        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #64748b;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        tbody tr {
            transition: all 0.2s;
        }
        
        tbody tr:hover {
            background: var(--bg);
        }
        
        .rank-cell {
            font-weight: 700;
            font-size: 18px;
            color: var(--primary);
            min-width: 50px;
        }
        
        .rank-cell.top-3 {
            font-size: 24px;
        }
        
        .rank-medal {
            display: inline-block;
            margin-right: 8px;
        }
        
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .user-info p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #64748b;
        }
        
        .reputation-score {
            font-weight: 700;
            font-size: 18px;
            color: var(--primary);
            min-width: 80px;
            text-align: right;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .leaderboard-container {
                padding: 10px;
            }
            
            th, td {
                padding: 12px;
                font-size: 13px;
            }
            
            .rank-cell {
                font-size: 14px;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .user-info h3 {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="leaderboard-container">
        <a href="../index.php" class="back-link">← Back to Feed</a>
        
        <div class="leaderboard-header">
            <h1>🏆 Leaderboard</h1>
            <p>Top community contributors by reputation</p>
        </div>
        
        <div class="leaderboard-table">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>User</th>
                        <th style="text-align: right;">Reputation</th>
                        <th style="text-align: right;">Badges</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $medals = ['🥇', '🥈', '🥉'];
                    foreach ($leaderboard as $index => $user): 
                    ?>
                    <tr>
                        <td>
                            <div class="rank-cell top-3">
                                <?php if ($index < 3): ?>
                                    <span class="rank-medal"><?php echo $medals[$index]; ?></span>
                                <?php else: ?>
                                    #<?php echo $index + 1; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?php if ($user['profile_pic'] && file_exists("../uploads/profile_pics/" . $user['profile_pic'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                    <p><?php echo getUserRank($conn, $user['id']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div class="reputation-score">
                                <?php echo number_format($user['reputation_score']); ?> pts
                            </div>
                        </td>
                        <td style="text-align: right;">
                            🎖️ <?php echo $user['badge_count']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
