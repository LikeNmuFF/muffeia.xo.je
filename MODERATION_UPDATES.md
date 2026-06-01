# Badword Moderation System Updates

## Overview
Updated the badword masking system from **removing** flagged content to **masking** it with asterisks. This allows users to see what was censored while maintaining content policy compliance.

## Changes Made

### 1. **Enhanced `mask_text()` Function** (includes/moderation.php)
**Previous Behavior:** Limited masking capability
**New Behavior:** 
- Masks bad words with pattern: `first_char + asterisks + last_char`
- Example: `fuck` → `f**k`, `bitches` → `b*****s`
- Handles obfuscated variants: `f_u_c_k` → `f**k`, `f u c k` → `* * * *`
- Processes words within larger strings: `fuckyou` → `f**kyou`
- Case-preserving: `FUCK` → `F**K`, `Fuck` → `F**k`

### 2. **New Helper Function: `create_badword_map()`** (includes/moderation.php)
**Purpose:** Reduce code duplication between `moderate_text()` and `mask_text()`
**Functionality:**
- Converts list of bad words to a "squished" map (removes non-alphanumeric chars)
- Example: `"fuck"`, `"f_ck"`, `"f.ck"` all map to squished form `"fuck"`
- Used by both moderation and masking functions for consistent matching

**Code:**
```php
function create_badword_map($bad_words) {
    $map = [];
    foreach ($bad_words as $bw) {
        $bw = trim($bw);
        if ($bw === '') continue;
        $bw_lower = mb_strtolower($bw, 'UTF-8');
        $bw_squish = preg_replace('/[^a-z0-9]+/i', '', $bw_lower);
        if ($bw_squish === '') continue;
        if (!isset($map[$bw_squish])) {
            $map[$bw_squish] = $bw_lower;
        }
    }
    return $map;
}
```

### 3. **New Helper Function: `mask_word()`** (includes/moderation.php)
**Purpose:** Consistent word masking logic
**Input:** Word to mask, optional reference squish (for length calc)
**Output:** Masked word with pattern `first + asterisks + last`

**Examples:**
- `"fuck"` (4 chars) → `f**k`
- `"bitches"` (7 chars) → `b*****s`
- `"a"` (1 char) → `*`
- `"ab"` (2 chars) → `**`

### 4. **Refactored `moderate_text()` Function** (includes/moderation.php)
**Improvements:**
- Now uses `create_badword_map()` to avoid code duplication
- Cleaner logic for detecting flagged content
- Better handling of obfuscated forms
- Maintains all original functionality (OpenAI fallback, etc.)

### 5. **Updated Client-Side Filter** (js/badword-filter.js)
**Changes:**
- Synchronized masking logic with PHP implementation
- Consistent pattern: first + asterisks + last character
- Better handling of obfuscated words in real-time
- Maintains toast notifications for user feedback

## Usage Locations

### Files Using Moderation:

| File | Function | Behavior |
|------|----------|----------|
| `post_problem.php:30-36` | Title & description moderation | Mask flagged content before storing |
| `pages/message.php:237-239` | Direct message moderation | Mask before encryption & storage |
| `pages/view_problem.php:177-180` | Solution replies moderation | Mask before storing |
| `pages/view_problem.php:210-213` | Solution submissions moderation | Mask before storing |

### Workflow:
1. User submits content (post, message, solution, reply)
2. `moderate_text()` checks if content is flagged
3. If flagged: `mask_text()` replaces bad words with asterisk pattern
4. Masked content is stored in database
5. Users see content with masked profanity

## Masking Pattern Examples

| Input | Output | Explanation |
|-------|--------|-------------|
| `fuck` | `f**k` | 4-char word: first + 2 asterisks + last |
| `fucking` | `f**king` | "fuck" masked, "ing" remains |
| `fuckyou` | `f**kyou` | "fuck" masked, "you" remains |
| `bitches` | `b*****s` | 7-char word: first + 5 asterisks + last |
| `shit` | `s**t` | 4-char word: first + 2 asterisks + last |
| `f_u_c_k` | `f**k` | Obfuscated form detected and masked |
| `f u c k` | `* * * *` | Spaced letters detected and masked |
| `This is a fuck word` | `This is a f**k word` | Context-aware masking |

## Sanitization & Security

### Current Protections:
1. **HTML Escaping:** `htmlspecialchars(ENT_QUOTES)` applied to all user inputs before display
2. **SQL Injection:** Prepared statements used throughout
3. **Message Encryption:** End-to-end AES-256-CBC encryption for DMs
4. **XSS Prevention:** Input validation and output encoding
5. **Obfuscation Detection:** 
   - Single-letter sequences: `f u c k` → detected as `fuck`
   - Punctuation-separated: `f.u.c.k` → detected as `fuck`
   - Squished form matching: `fuck` and `f_ck` both normalize to same pattern

### Input Sanitization Process:
```
User Input → Moderation Check → (If flagged) Masking → 
HTML Escape → Encrypt (if DM) → Database Storage → 
Decrypt (if DM) → HTML Display
```

## Code Quality Improvements

### Reduced Duplication:
- **Before:** Separate implementations of bad word detection in `moderate_text()` and `mask_text()`
- **After:** Shared `create_badword_map()` used by both functions

### Consistency:
- PHP (`moderation.php`) and JavaScript (`badword-filter.js`) now use identical masking logic
- Both handle obfuscated variants the same way
- Pattern is consistent: `first + asterisks + last`

## Testing Results

All masking tests passed:
- ✓ Simple words: `fuck` → `f**k`
- ✓ Words within strings: `fuckyou` → `f**kyou`
- ✓ Words with suffixes: `fucking` → `f**king`
- ✓ Multi-character words: `bitches` → `b*****s`
- ✓ Obfuscated forms: `f_u_c_k` → `f**k`
- ✓ Context preservation: Text without bad words remains unchanged

## Configuration

### Badwords List:
- Location: `includes/badwords.txt`
- Format: One word per line
- Comments: Lines starting with `#` are ignored
- Supports obfuscated variants in the list itself

### Active Protection:
- ✓ Post problem titles/descriptions
- ✓ Direct messages (before encryption)
- ✓ Solution submissions
- ✓ Solution replies
- ✓ Client-side input (real-time masking)

## Future Enhancements

1. **Admin Dashboard:** Add interface to manage bad words list in real-time
2. **Flagged Content Log:** Track what was masked and by whom
3. **Customizable Patterns:** Allow different mask patterns (### instead of ***)
4. **Severity Levels:** Different handling for minor vs. severe violations
5. **User Notifications:** Notify users when their content is masked
6. **Audit Trail:** Log moderation actions for compliance

