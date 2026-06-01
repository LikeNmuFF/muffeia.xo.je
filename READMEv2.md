# MUFFEIA v2 — Major Changes from v1

## 1. Real-Time Badword Masking (Biggest Change)

**Old:** Bad words in posts were silently deleted/replaced on submit — user had no feedback when their content was moderated.

**New:** 
- **Client-side masking** — as the user types, bad words are masked in real-time using the pattern `f***k` (first char + asterisks + last char)
- **Visual animation** — a red flash pulse (`.badword-masking` keyframe) plays on the input field when masking occurs
- **Toast notification** — shows "Bad words masked" so the user knows moderation happened
- **Detects obfuscation** — catches `f u c k`, `f_u_c_k`, `f.u.c.k`, and embedded variants like `fucking`, `fuckyou`
- **PHP backup** — `mask_text()` still runs server-side for non-JS clients
- **3-char minimum** — avoids false positives on short strings like "ll" in "hello"
- **Shared helper** — eliminated ~30 lines of duplicated code between `moderate_text()` and `mask_text()` via `create_badword_map()`

**Files changed:** `js/badword-filter.js`, `includes/moderation.php`

---

## 2. Profile Pictures on Dashboard & Posts

**Old:** All post avatars showed only a user icon or initial letter.

**New:**
- Dashboard feeds (`index.php`) show the user's actual profile picture in a circular crop when the post is not anonymous
- Profile pics are included in the SQL queries (`u.profile_pic`)
- Graceful fallback: if the image fails to load (onerror), it switches to initials
- Same treatment on infinite-scroll posts (`api/load_posts.php`)
- Solutions and replies on `view_problem.php` also show profile pics (added `u.profile_pic` to their queries)

**Files changed:** `index.php`, `api/load_posts.php`, `pages/view_problem.php`

---

## 3. Like & Share Buttons Fixed

**Old:** Buttons used class names `.btn-like` and `.btn-share` that didn't match any CSS — they were non-functional.

**New:**
- Changed to `.like-btn` and `.share-btn` matching the existing CSS
- Heart icon toggles between `far fa-heart` (unliked) and `fas fa-heart` (liked)
- Active `.liked` class for visual feedback
- Share modal shows copy-link, Facebook, Twitter, WhatsApp options
- Share buttons carry `data-title` and `data-description` for social share text
- Fixed broken share URL (was missing `.php` extension in `index.php`)

**Files changed:** `index.php`, `api/load_posts.php`

---

## 4. Profile Page Post Cards Redesigned

**Old:** Used `.profile-post-card` class whose CSS only existed in `modern-theme.css` — a file the profile page never loaded. Cards appeared unstyled/broken.

**New:** Restructured to use `.post-card.card-elevated-md.animate-in` — the exact same card classes used on the dashboard. Includes profile pic avatar, post meta, title, description, like count, and view solutions link. Added dropdown menu CSS inline since the shared stylesheet wasn't loaded.

**Files changed:** `pages/profile.php`

---

## 5. View Problem Page UI Overhaul

**Old:** Minimal unstyled layout — problem card used generic icons, solution cards had no CSS, replies were plain, "Post Your Solution" form had no container styling.

**New:**
- Problem card matches dashboard style (`.post-card.card-elevated-md.animate-in`) with actual profile pic
- Solution cards have proper borders, hover shadows, and profile pic avatars
- Reactions are pill-shaped buttons with hover/active tinting via `color-mix()`
- Replies are nested with a left accent border (3px primary color)
- Replies hidden by default, toggled via the Reply button
- "Post Your Solution" form wrapped in `.card-elevated-md` with consistent section headers
- Login prompt and empty state have dashed-border styling
- All CSS added as inline `<style>` block (no shared CSS file changes needed)

**Files changed:** `pages/view_problem.php`

---

## 6. Facebook / Social Sharing Improvements

**Old:** 
- OG image pointed to a broken `default.png` path
- Description was truncated at 300 chars with `substr` (no multi-byte support)
- No image dimensions set → Facebook showed full-width "large image" layout, pushing text down
- Share URL was missing `.php` extension

**New:**
- OG image always uses the site logo (`/logo/muffeia.png`) with 200x200 dimensions for a compact "thumbnail on right" layout — giving text more space
- Description truncated at 200 chars with `mb_substr` (safe for UTF-8)
- Facebook share now passes `quote` parameter with post title + description for text to appear directly in the share composer
- WhatsApp and Twitter shares also include the post title text alongside the URL
- Removed `error_reporting(E_ALL); ini_set('display_errors', 1);` for production safety

**Files changed:** `pages/view_problem.php`, `index.php`, `api/load_posts.php`

---

## 7. Code Quality

- Eliminated duplicate moderation logic (~30 lines) by creating shared `create_badword_map()` helper
- All API endpoints pass PHP syntax checks
- Changed `data-badword-action` from `"delete"` to `"mask"` on solution/reply textareas for consistent masking behavior
