<?php
/**
 * Reputation and Badge Management System
 * Tracks user points and achievements
 */

// Point values for different actions
const REPUTATION_POINTS = [
    'post_created' => 10,
    'post_liked' => 1,
    'solution_created' => 15,
    'solution_liked' => 2,
    'solution_accepted' => 25,
    'reply_created' => 5,
];

/**
 * Award reputation points to a user
 */
function awardReputation($conn, $user_id, $action_type, $points = null, $description = '') {
    if ($points === null) {
        $points = REPUTATION_POINTS[$action_type] ?? 0;
    }
    
    if ($points === 0) {
        return false;
    }
    
    // Insert reputation point record
    $stmt = $conn->prepare("
        INSERT INTO reputation_points (user_id, points, action_type, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $user_id, $points, $action_type, $description);
    $success = $stmt->execute();
    $stmt->close();
    
    // Update user reputation score
    if ($success) {
        $update_stmt = $conn->prepare("UPDATE users SET reputation_score = reputation_score + ? WHERE id = ?");
        $update_stmt->bind_param("ii", $points, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Check for badge eligibility
        checkAndAwardBadges($conn, $user_id);
    }
    
    return $success;
}

/**
 * Get user reputation score
 */
function getUserReputation($conn, $user_id) {
    $stmt = $conn->prepare("SELECT reputation_score FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['reputation_score'] ?? 0;
}

/**
 * Get user reputation history
 */
function getReputationHistory($conn, $user_id, $limit = 20) {
    $stmt = $conn->prepare("
        SELECT * FROM reputation_points
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result ?: [];
}

/**
 * Get user badges
 */
function getUserBadges($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT b.*, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result ?: [];
}

/**
 * Check and award badges based on user activity
 */
function checkAndAwardBadges($conn, $user_id) {
    $badges_awarded = [];
    
    // Get badge criteria
    $badges_result = $conn->query("SELECT * FROM badges");
    $badges = $badges_result ? $badges_result->fetch_all(MYSQLI_ASSOC) : [];
    $user_rep = getUserReputation($conn, $user_id);
    
    foreach ($badges as $badge) {
        // Check if user already has this badge
        $check_stmt = $conn->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
        $check_stmt->bind_param("ii", $user_id, $badge['id']);
        $check_stmt->execute();
        $already_has = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
        
        if ($already_has) {
            continue;
        }
        
        $should_award = false;
        $criteria = $badge['criteria'];
        
        // Evaluate criteria
        if (strpos($criteria, 'Posted a problem') !== false) {
            $pstmt = $conn->prepare("SELECT COUNT(*) as count FROM problems WHERE user_id = ?");
            $pstmt->bind_param("i", $user_id);
            $pstmt->execute();
            $count = $pstmt->get_result()->fetch_assoc()['count'];
            $pstmt->close();
            if ($count >= 1) $should_award = true;
        } elseif (strpos($criteria, '5 solutions') !== false) {
            $pstmt = $conn->prepare("SELECT COUNT(*) as count FROM solutions WHERE user_id = ?");
            $pstmt->bind_param("i", $user_id);
            $pstmt->execute();
            $count = $pstmt->get_result()->fetch_assoc()['count'];
            $pstmt->close();
            if ($count >= 5) $should_award = true;
        } elseif (strpos($criteria, '10 likes on solutions') !== false) {
            $pstmt = $conn->prepare("
                SELECT COUNT(*) as count FROM solution_reactions
                WHERE solution_id IN (SELECT id FROM solutions WHERE user_id = ?)
                AND reaction_type = 'like'
            ");
            $pstmt->bind_param("i", $user_id);
            $pstmt->execute();
            $count = $pstmt->get_result()->fetch_assoc()['count'];
            $pstmt->close();
            if ($count >= 10) $should_award = true;
        } elseif (strpos($criteria, '50 likes on single problem') !== false) {
            $pstmt = $conn->prepare("
                SELECT COALESCE(MAX((SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id)), 0) as max_likes
                FROM problems p
                WHERE p.user_id = ?
            ");
            $pstmt->bind_param("i", $user_id);
            $pstmt->execute();
            $max_likes = $pstmt->get_result()->fetch_assoc()['max_likes'];
            $pstmt->close();
            if ($max_likes >= 50) $should_award = true;
        } elseif (strpos($criteria, 'Reputation >= 100') !== false) {
            if ($user_rep >= 100) $should_award = true;
        } elseif (strpos($criteria, '50 likes on solutions') !== false) {
            $pstmt = $conn->prepare("
                SELECT COUNT(*) as count FROM solution_reactions
                WHERE solution_id IN (SELECT id FROM solutions WHERE user_id = ?)
                AND reaction_type = 'like'
            ");
            $pstmt->bind_param("i", $user_id);
            $pstmt->execute();
            $count = $pstmt->get_result()->fetch_assoc()['count'];
            $pstmt->close();
            if ($count >= 50) $should_award = true;
        }
        
        if ($should_award) {
            // Award badge
            $award_stmt = $conn->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            $award_stmt->bind_param("ii", $user_id, $badge['id']);
            if ($award_stmt->execute()) {
                $badges_awarded[] = $badge;
            }
            $award_stmt->close();
        }
    }
    
    return $badges_awarded;
}

/**
 * Get user rank based on reputation
 */
function getUserRank($conn, $user_id) {
    $user_rep = getUserReputation($conn, $user_id);
    
    if ($user_rep < 10) return 'Newbie';
    if ($user_rep < 50) return 'Helper';
    if ($user_rep < 100) return 'Contributor';
    if ($user_rep < 250) return 'Expert';
    if ($user_rep < 500) return 'Master';
    return 'Legend';
}

/**
 * Render reputation badge with color
 */
function renderReputationBadge($reputation) {
    $rank = '';
    $color = '';
    
    if ($reputation < 10) {
        $rank = 'Newbie';
        $color = '#94a3b8';
    } elseif ($reputation < 50) {
        $rank = 'Helper';
        $color = '#3b82f6';
    } elseif ($reputation < 100) {
        $rank = 'Contributor';
        $color = '#8b5cf6';
    } elseif ($reputation < 250) {
        $rank = 'Expert';
        $color = '#f59e0b';
    } elseif ($reputation < 500) {
        $rank = 'Master';
        $color = '#ef4444';
    } else {
        $rank = 'Legend';
        $color = '#ec4899';
    }
    
    return '<span class="reputation-badge" style="background-color: ' . $color . '; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block;">' . $rank . '</span>';
}

/**
 * Render user badges
 */
function renderUserBadges($badges, $max = 5) {
    if (empty($badges)) {
        return '';
    }
    
    $html = '<div class="user-badges">';
    $count = 0;
    foreach ($badges as $badge) {
        if ($count >= $max) {
            $remaining = count($badges) - $max;
            $html .= '<span class="badge-item" title="' . $remaining . ' more">+' . $remaining . '</span>';
            break;
        }
        $html .= '<span class="badge-item" title="' . htmlspecialchars($badge['name']) . ': ' . htmlspecialchars($badge['description']) . '">' . htmlspecialchars($badge['icon']) . '</span>';
        $count++;
    }
    $html .= '</div>';
    return $html;
}

/**
 * Get leaderboard
 */
function getLeaderboard($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT id, username, profile_pic, reputation_score, (SELECT COUNT(*) FROM user_badges WHERE user_id = users.id) as badge_count
        FROM users
        WHERE reputation_score > 0
        ORDER BY reputation_score DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result ?: [];
}
?>
