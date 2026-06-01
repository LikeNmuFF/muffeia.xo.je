<?php
/**
 * Category and Tag Management Functions
 * Used throughout the application for filtering and organization
 */

/**
 * Get all categories
 */
function categoryFeatureAvailable($conn) {
    $tables = ['categories', 'tags', 'problem_tags'];
    foreach ($tables as $table) {
        $table_check = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$table_check || $table_check->num_rows === 0) {
            return false;
        }
    }

    $column_check = $conn->query("SHOW COLUMNS FROM problems LIKE 'category_id'");
    return $column_check && $column_check->num_rows > 0;
}

function getCategories($conn) {
    $result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Get category by ID
 */
function getCategoryById($conn, $category_id) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    if (!$stmt) return null;
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Create or get tags from comma-separated string
 */
function processTagsFromString($conn, $tag_string) {
    if (empty($tag_string)) {
        return [];
    }
    
    $tag_names = array_filter(array_map('trim', explode(',', $tag_string)));
    $tag_ids = [];
    
    foreach ($tag_names as $tag_name) {
        if (strlen($tag_name) < 2 || strlen($tag_name) > 30) continue;
        
        // Check if tag exists
        $stmt = $conn->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->bind_param("s", $tag_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $tag = $result->fetch_assoc();
            $tag_ids[] = $tag['id'];
        } else {
            // Create new tag
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $tag_name));
            $insert_stmt = $conn->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $tag_name, $slug);
            if ($insert_stmt->execute()) {
                $tag_ids[] = $conn->insert_id;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
    
    return array_unique($tag_ids);
}

/**
 * Extract #tags from description text
 */
function extractTagsFromText($text) {
    preg_match_all('/(?:^|\s)#(\w+)/u', $text, $matches);
    if (empty($matches[1])) return [];
    $tags = array_filter($matches[1], function($tag) {
        return strlen($tag) >= 2 && !preg_match('/^\d+$/', $tag);
    });
    return array_values(array_unique($tags));
}

/**
 * Extract ##Category from description text
 */
function extractCategoryFromText($text) {
    if (preg_match('/##([\w\s]+?)(?=\s*#|$)/u', $text, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Get tags for a specific problem
 */
function getProblemTags($conn, $problem_id) {
    $stmt = $conn->prepare("
        SELECT t.* FROM tags t
        JOIN problem_tags pt ON t.id = pt.tag_id
        WHERE pt.problem_id = ?
        ORDER BY t.name ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param("i", $problem_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Get popular tags
 */
function getPopularTags($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT t.*, COUNT(pt.id) as usage_count
        FROM tags t
        LEFT JOIN problem_tags pt ON t.id = pt.tag_id
        GROUP BY t.id
        ORDER BY usage_count DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Link tags to a problem
 */
function linkTagsToProblem($conn, $problem_id, $tag_ids) {
    // Delete existing tags for this problem
    $delete_stmt = $conn->prepare("DELETE FROM problem_tags WHERE problem_id = ?");
    $delete_stmt->bind_param("i", $problem_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Insert new tags
    if (!empty($tag_ids)) {
        $insert_stmt = $conn->prepare("INSERT INTO problem_tags (problem_id, tag_id) VALUES (?, ?)");
        foreach ($tag_ids as $tag_id) {
            $insert_stmt->bind_param("ii", $problem_id, $tag_id);
            $insert_stmt->execute();
        }
        $insert_stmt->close();
    }
}

/**
 * Get problems by category
 */
function getProblemsByCategory($conn, $category_id, $limit = 10, $offset = 0, $user_id = null) {
    $user_id = $user_id ? intval($user_id) : 0;
    $query = "
        SELECT p.*, u.username, u.profile_pic, 
               (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
               " . ($user_id ? "(SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id AND user_id = $user_id) as user_liked" : "0 as user_liked") . "
        FROM problems p
        JOIN users u ON p.user_id = u.id
        WHERE p.category_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $category_id, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Get problems by tag
 */
function getProblemsByTag($conn, $tag_id, $limit = 10, $offset = 0, $user_id = null) {
    $user_id = $user_id ? intval($user_id) : 0;
    $query = "
        SELECT p.*, u.username, u.profile_pic,
               (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
               " . ($user_id ? "(SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id AND user_id = $user_id) as user_liked" : "0 as user_liked") . "
        FROM problems p
        JOIN users u ON p.user_id = u.id
        JOIN problem_tags pt ON p.id = pt.problem_id
        WHERE pt.tag_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $tag_id, $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Get total count of problems by category
 */
function getProblemaCountByCategory($conn, $category_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM problems WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

function getProblemCountByCategory($conn, $category_id) {
    return getProblemaCountByCategory($conn, $category_id);
}

/**
 * Get total count of problems by tag
 */
function getProblemCountByTag($conn, $tag_id) {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT pt.problem_id) as count FROM problem_tags pt WHERE tag_id = ?");
    $stmt->bind_param("i", $tag_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

/**
 * Render category badge
 */
function renderCategoryBadge($category, $link = true) {
    if (!$category) return '';
    
    $html = '<span class="category-badge" style="display:inline-flex;align-items:center;background-color: ' . generateCategoryColor($category['id']) . ';color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;">';
    
    if ($link) {
        $html .= '<a href="/pages/browse_category.php?category_id=' . $category['id'] . '" style="text-decoration: none; color: white;">';
    }
    
    $html .= htmlspecialchars($category['name']);
    
    if ($link) {
        $html .= '</a>';
    }
    
    $html .= '</span>';
    return $html;
}

/**
 * Generate consistent color for category
 */
function generateCategoryColor($category_id) {
    $colors = ['#6366f1', '#3b82f6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
    return $colors[$category_id % count($colors)];
}

/**
 * Render tags for a problem
 */
function renderProblemTags($tags, $linked = true) {
    if (empty($tags)) return '';
    
    $html = '<div class="problem-tags" style="display:flex;flex-wrap:wrap;gap:8px;">';
    foreach ($tags as $tag) {
        if ($linked) {
            $html .= '<a href="/pages/browse_tags.php?tag_id=' . $tag['id'] . '" class="tag-chip" style="display:inline-flex;align-items:center;background:#f1f5f9;color:#6366f1;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;">#' . htmlspecialchars($tag['name']) . '</a>';
        } else {
            $html .= '<span class="tag-chip" style="display:inline-flex;align-items:center;background:#f1f5f9;color:#6366f1;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;">#' . htmlspecialchars($tag['name']) . '</span>';
        }
    }
    $html .= '</div>';
    return $html;
}
?>
