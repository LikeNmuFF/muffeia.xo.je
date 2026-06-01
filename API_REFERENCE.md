# MUFFEIA v3 - Developer API Reference

## Quick Function Reference

### Categories & Tags Functions
**File:** `includes/categories_tags.php`

```php
// Get all categories
$categories = getCategories($conn);

// Get specific category
$category = getCategoryById($conn, $category_id);

// Create/Get tags from string
$tag_ids = processTagsFromString($conn, "php,coding,help");

// Get tags for a problem
$tags = getProblemTags($conn, $problem_id);

// Get popular tags
$popular = getPopularTags($conn, 10);

// Link tags to problem
linkTagsToProblem($conn, $problem_id, $tag_ids);

// Get problems by category
$problems = getProblemsByCategory($conn, $cat_id, $limit, $offset, $user_id);

// Get problems by tag
$problems = getProblemsByTag($conn, $tag_id, $limit, $offset, $user_id);

// Get counts
$count = getProblemaCountByCategory($conn, $cat_id);
$count = getProblemCountByTag($conn, $tag_id);

// Render HTML
echo renderCategoryBadge($category, true);
echo renderProblemTags($tags, true);
```

### Reputation & Badges Functions
**File:** `includes/reputation.php`

```php
// Award reputation points
awardReputation($conn, $user_id, 'post_created', 10, 'Created new problem');

// Get user reputation
$score = getUserReputation($conn, $user_id);

// Get reputation history
$history = getReputationHistory($conn, $user_id, 20);

// Get user badges
$badges = getUserBadges($conn, $user_id);

// Check and award badges
$awarded = checkAndAwardBadges($conn, $user_id);

// Get user rank
$rank = getUserRank($conn, $user_id); // Returns: "Newbie", "Helper", etc.

// Render HTML
echo renderReputationBadge($reputation_score);
echo renderUserBadges($badges, 5);

// Get leaderboard
$leaderboard = getLeaderboard($conn, 100);
```

### Email Verification Functions
**File:** `includes/email_verification.php`

```php
// Generate token
$token = generateVerificationToken();

// Send verification email
$success = sendVerificationEmail($email, $username, $token);

// Create verification record and send email
$success = createEmailVerification($conn, $user_id, $email);

// Verify token
$verified_user_id = verifyEmailToken($conn, $token);

// Resend verification email
$success = resendVerificationEmail($conn, $user_id);

// Check if email verified
$is_verified = isEmailVerified($conn, $user_id);

// Get pending verification status
$status = getPendingVerificationStatus($conn, $user_id);
// Returns: ['created_at' => '...', 'expires_at' => '...']
```

---

## Integration Examples

### Add Reputation Award When Post Created

In `post_problem.php` or wherever posts are created:

```php
// After inserting post
$problem_id = $conn->insert_id;
$user_id = $_SESSION['user_id'];

// Award reputation
include 'includes/reputation.php';
awardReputation($conn, $user_id, 'post_created', 10, 'Created problem #' . $problem_id);
```

### Add Reputation Award When Post Liked

In `index.php` or wherever likes are handled:

```php
if ($liked) {  // Just liked
    // Award reputation to the user who created the post
    $get_creator = $conn->query("SELECT user_id FROM problems WHERE id = $problem_id");
    $creator = $get_creator->fetch_assoc();
    
    include 'includes/reputation.php';
    awardReputation($conn, $creator['user_id'], 'post_liked', 1, 'Post liked by user');
}
```

### Display Category on Post

In `index.php` template:

```php
<?php
include 'includes/categories_tags.php';

// Get category
$category = getCategoryById($conn, $problem['category_id']);

// Display
if ($category) {
    echo renderCategoryBadge($category, true);
}
?>
```

### Display Tags on Post

In `pages/view_problem.php`:

```php
<?php
include 'includes/categories_tags.php';

// Get tags
$tags = getProblemTags($conn, $problem_id);

// Display
if (!empty($tags)) {
    echo renderProblemTags($tags, true);
}
?>
```

### Display User Reputation and Badges

In `pages/profile.php`:

```php
<?php
include 'includes/reputation.php';

// Get data
$reputation = getUserReputation($conn, $user_id);
$badges = getUserBadges($conn, $user_id);

// Display
echo renderReputationBadge($reputation);
echo renderUserBadges($badges, 5);
?>
```

### Verify Email on Registration

In `auth/api.php` or registration handler:

```php
if ($registration_successful) {
    include 'includes/email_verification.php';
    
    // Create verification and send email
    createEmailVerification($conn, $new_user_id, $user_email);
    
    // Optionally redirect to verification page
    // header("Location: verify_email.php");
}
```

### Filter Posts by Category

In main feed or browse page:

```php
<?php
include 'includes/categories_tags.php';

$category_id = $_GET['category_id'] ?? null;
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if ($category_id) {
    // Get problems in category
    $problems = getProblemsByCategory($conn, $category_id, $limit, $offset, $_SESSION['user_id']);
    $total = getProblemaCountByCategory($conn, $category_id);
} else {
    // Get all problems
    // ... existing code
}
?>
```

---

## Database Constants

```php
// Points system (from includes/reputation.php)
REPUTATION_POINTS = [
    'post_created' => 10,
    'post_liked' => 1,
    'solution_created' => 15,
    'solution_liked' => 2,
    'solution_accepted' => 25,
    'reply_created' => 5,
];
```

---

## Return Value References

### getCategories()
```php
[
    ['id' => 1, 'name' => 'Personal', 'slug' => 'personal', 'description' => '...', 'icon' => '❤️'],
    ...
]
```

### getProblemTags()
```php
[
    ['id' => 1, 'name' => 'php', 'slug' => 'php', 'usage_count' => 5],
    ...
]
```

### getLeaderboard()
```php
[
    [
        'id' => 1,
        'username' => 'john',
        'profile_pic' => 'pic.jpg',
        'reputation_score' => 250,
        'badge_count' => 4
    ],
    ...
]
```

### getUserBadges()
```php
[
    [
        'id' => 1,
        'name' => 'First Problem',
        'description' => 'Posted your first problem',
        'icon' => '🎯',
        'criteria' => 'Posted a problem',
        'earned_at' => '2026-05-30 10:30:00'
    ],
    ...
]
```

### getUserRank()
```
Returns string: "Newbie", "Helper", "Contributor", "Expert", "Master", or "Legend"
```

---

## SQL Queries Reference

### Get problems with all data

```sql
SELECT 
    p.*, 
    u.username, 
    u.profile_pic,
    u.reputation_score,
    c.name as category_name,
    (SELECT COUNT(*) FROM post_likes WHERE problem_id = p.id) as like_count,
    (SELECT COUNT(*) FROM solutions WHERE problem_id = p.id) as solution_count
FROM problems p
JOIN users u ON p.user_id = u.id
LEFT JOIN categories c ON p.category_id = c.id
ORDER BY p.created_at DESC;
```

### Get problem with tags

```sql
SELECT 
    p.*,
    GROUP_CONCAT(t.name) as tags
FROM problems p
LEFT JOIN problem_tags pt ON p.id = pt.problem_id
LEFT JOIN tags t ON pt.tag_id = t.id
WHERE p.id = ?
GROUP BY p.id;
```

### Get user reputation summary

```sql
SELECT 
    u.id,
    u.username,
    u.reputation_score,
    COUNT(DISTINCT ub.id) as badge_count,
    (SELECT COUNT(*) FROM reputation_points WHERE user_id = u.id) as point_history_count
FROM users u
LEFT JOIN user_badges ub ON u.id = ub.user_id
WHERE u.id = ?
GROUP BY u.id;
```

---

## Configuration References

### Email Configuration
**File:** `includes/email_verification.php` (lines 8-14)

```php
$mail->Host = 'smtp.gmail.com';
$mail->Username = getenv('MAIL_USERNAME') ?: 'muff.muffeia@gmail.com';
$mail->Password = getenv('MAIL_PASSWORD') ?: 'your-app-password';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port = 465;
```

### Database Tables
All new tables created in `includes/migrations.php`

---

## Error Handling

All functions use proper error handling with prepared statements.

### Example error handling:

```php
try {
    if (!$success) {
        throw new Exception("Operation failed");
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    // Handle error gracefully
}
```

---

## Testing Examples

### Test Categories

```php
<?php
include 'includes/db.php';
include 'includes/categories_tags.php';

// Get and display all categories
$categories = getCategories($conn);
echo "<pre>";
print_r($categories);
echo "</pre>";
?>
```

### Test Reputation

```php
<?php
include 'includes/db.php';
include 'includes/reputation.php';

// Award points
awardReputation($conn, 1, 'post_created', 10, 'Test');

// Get score
$score = getUserReputation($conn, 1);
echo "User 1 reputation: " . $score;
?>
```

### Test Badges

```php
<?php
include 'includes/db.php';
include 'includes/reputation.php';

// Check badges
$badges = getUserBadges($conn, 1);
echo "User 1 has " . count($badges) . " badges";
?>
```

---

## Performance Tips

1. **Cache Categories** - They don't change often
   ```php
   $cache_key = 'categories_list';
   $categories = apcu_fetch($cache_key);
   if ($categories === false) {
       $categories = getCategories($conn);
       apcu_store($cache_key, $categories, 3600); // 1 hour
   }
   ```

2. **Paginate Tags** - Don't load all tags at once
   ```php
   $tags = getPopularTags($conn, 10); // Limit to top 10
   ```

3. **Use Indexes** - Ensure database indexes on:
   - `problems.category_id`
   - `problem_tags.tag_id`
   - `user_badges.user_id`
   - `reputation_points.user_id`

---

## Security Reminders

- ✅ All functions use prepared statements
- ✅ All user input is validated
- ✅ HTML output is escaped with htmlspecialchars()
- ✅ Admin functions check `is_admin` flag
- ✅ Email tokens are cryptographically secure

---

## Support & Debugging

Enable debug mode in any file:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

Check error logs:

```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/www/html/error_log
```

---

**Last Updated:** May 30, 2026  
**API Version:** 1.0  
**Status:** Production Ready
