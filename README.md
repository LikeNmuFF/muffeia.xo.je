# 🌐 Muffeia - Community Problem-Sharing Platform

<svg width="100%" height="150" viewBox="0 0 800 150" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 20px auto; display: block;">
  <!-- Animated background -->
  <defs>
    <style>
      @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
      }
      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }
      @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      .float { animation: float 3s ease-in-out infinite; }
      .pulse { animation: pulse 2s ease-in-out infinite; }
      .rotate { animation: rotate 20s linear infinite; transform-origin: center; }
    </style>
    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#ec4899;stop-opacity:1" />
    </linearGradient>
  </defs>
  
  <!-- Background circles -->
  <circle cx="100" cy="75" r="60" fill="url(#grad1)" opacity="0.1" class="rotate" />
  <circle cx="700" cy="75" r="50" fill="url(#grad1)" opacity="0.15" class="rotate" style="animation-direction: reverse;" />
  
  <!-- Main elements -->
  <g class="float">
    <!-- Left chat bubble -->
    <rect x="50" y="40" width="120" height="50" rx="10" fill="#6366f1" opacity="0.9"/>
    <polygon points="45,80 40,95 55,80" fill="#6366f1"/>
    <text x="65" y="70" font-family="Arial, sans-serif" font-size="14" fill="white" font-weight="bold">Problem?</text>
  </g>
  
  <g class="float" style="animation-delay: 0.3s;">
    <!-- Center sparkle -->
    <circle cx="400" cy="75" r="8" fill="#ec4899" class="pulse"/>
    <g opacity="0.6">
      <line x1="400" y1="55" x2="400" y2="45" stroke="#ec4899" stroke-width="2"/>
      <line x1="400" y1="95" x2="400" y2="105" stroke="#ec4899" stroke-width="2"/>
      <line x1="380" y1="75" x2="370" y2="75" stroke="#ec4899" stroke-width="2"/>
      <line x1="420" y1="75" x2="430" y2="75" stroke="#ec4899" stroke-width="2"/>
    </g>
  </g>
  
  <g class="float" style="animation-delay: 0.6s;">
    <!-- Right chat bubble -->
    <rect x="630" y="40" width="120" height="50" rx="10" fill="#10b981" opacity="0.9"/>
    <polygon points="755,80 760,95 745,80" fill="#10b981"/>
    <text x="645" y="70" font-family="Arial, sans-serif" font-size="14" fill="white" font-weight="bold">Solution!</text>
  </g>
  
  <!-- Connecting lines -->
  <line x1="170" y1="65" x2="390" y2="75" stroke="#6366f1" stroke-width="2" opacity="0.5" stroke-dasharray="5,5"/>
  <line x1="410" y1="75" x2="630" y2="65" stroke="#10b981" stroke-width="2" opacity="0.5" stroke-dasharray="5,5"/>
</svg>

> **A modern, secure community platform where people share their problems and find solutions together.**

---

## 📋 Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Installation Guide](#installation-guide)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Security](#security)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)

---

## 🎯 Overview

**Muffeia** is a sophisticated, full-stack web application designed to foster a safe and inclusive community where individuals can:

- **Post problems** they're facing (personal, technical, educational, etc.)
- **Receive solutions** from community members with relevant expertise
- **Engage with peers** through discussions, comments, and direct messaging
- **Build reputation** through helpful contributions and community recognition
- **Discover content** via categories, tags, and advanced search functionality
- **Participate anonymously** for sensitive topics or openly to build personal brand

### Vision

To create a **safer alternative to traditional Q&A and support platforms** by emphasizing:
- 🔒 **Privacy**: End-to-end encrypted direct messaging
- 👥 **Community**: Gamified reputation system and public recognition
- 🛡️ **Safety**: Intelligent content moderation and community guidelines
- 🌍 **Inclusivity**: Multi-language support with UTF-8MB4 encoding

---

## ✨ Key Features

<svg width="100%" height="280" viewBox="0 0 800 280" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 20px auto; display: block;">
  <defs>
    <style>
      @keyframes slideIn {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
      }
      @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
      }
      .feature-item { animation: slideIn 0.6s ease-out forwards; }
      .feature-icon { animation: bounce 2s ease-in-out infinite; }
    </style>
    <linearGradient id="featureGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#6366f1;stop-opacity:0.2" />
      <stop offset="100%" style="stop-color:#ec4899;stop-opacity:0.2" />
    </linearGradient>
  </defs>
  
  <!-- Feature boxes -->
  <g class="feature-item" style="animation-delay: 0s;">
    <rect x="20" y="20" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#6366f1" stroke-width="2"/>
    <circle cx="105" cy="50" r="20" fill="#6366f1" class="feature-icon" style="animation-delay: 0s;"/>
    <text x="105" y="57" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">✉️</text>
    <text x="105" y="100" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Post Problems</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.1s;">
    <rect x="215" y="20" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#10b981" stroke-width="2"/>
    <circle cx="300" cy="50" r="20" fill="#10b981" class="feature-icon" style="animation-delay: 0.1s;"/>
    <text x="300" y="57" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">💡</text>
    <text x="300" y="100" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Share Solutions</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.2s;">
    <rect x="410" y="20" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#f59e0b" stroke-width="2"/>
    <circle cx="495" cy="50" r="20" fill="#f59e0b" class="feature-icon" style="animation-delay: 0.2s;"/>
    <text x="495" y="57" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">🔐</text>
    <text x="495" y="100" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Encrypted Chat</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.3s;">
    <rect x="605" y="20" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#ec4899" stroke-width="2"/>
    <circle cx="690" cy="50" r="20" fill="#ec4899" class="feature-icon" style="animation-delay: 0.3s;"/>
    <text x="690" y="57" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">⭐</text>
    <text x="690" y="100" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Reputation System</text>
  </g>
  
  <!-- Bottom row -->
  <g class="feature-item" style="animation-delay: 0.4s;">
    <rect x="20" y="160" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#3b82f6" stroke-width="2"/>
    <circle cx="105" cy="190" r="20" fill="#3b82f6" class="feature-icon" style="animation-delay: 0.4s;"/>
    <text x="105" y="197" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">🔍</text>
    <text x="105" y="240" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Advanced Search</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.5s;">
    <rect x="215" y="160" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#8b5cf6" stroke-width="2"/>
    <circle cx="300" cy="190" r="20" fill="#8b5cf6" class="feature-icon" style="animation-delay: 0.5s;"/>
    <text x="300" y="197" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">🏆</text>
    <text x="300" y="240" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Leaderboard</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.6s;">
    <rect x="410" y="160" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#06b6d4" stroke-width="2"/>
    <circle cx="495" cy="190" r="20" fill="#06b6d4" class="feature-icon" style="animation-delay: 0.6s;"/>
    <text x="495" y="197" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">📱</text>
    <text x="495" y="240" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Responsive Design</text>
  </g>
  
  <g class="feature-item" style="animation-delay: 0.7s;">
    <rect x="605" y="160" width="170" height="120" rx="8" fill="url(#featureGrad)" stroke="#14b8a6" stroke-width="2"/>
    <circle cx="690" cy="190" r="20" fill="#14b8a6" class="feature-icon" style="animation-delay: 0.7s;"/>
    <text x="690" y="197" font-family="Arial, sans-serif" font-size="28" fill="white" text-anchor="middle">🌙</text>
    <text x="690" y="240" font-family="Arial, sans-serif" font-size="12" fill="#333" text-anchor="middle" font-weight="bold">Dark/Light Mode</text>
  </g>
</svg>

### Core Features

#### 👤 User Management
- **Multi-authentication methods**: Email/Password, Google OAuth, Facebook OAuth
- **Email verification** with secure token system
- **User profiles** with statistics and contribution history
- **Account management** including secure deletion workflows
- **Online status** and presence tracking

#### 📝 Content Management
- **Post problems** with categories, tags, and descriptions
- **Submit solutions** with community voting
- **Accept best solution** feature to mark definitive answers
- **Edit & delete** your own content
- **Bookmarking** for saving posts to read later
- **View analytics** (likes, views, reply count)

#### 💬 Community Engagement
- **Direct messaging** with end-to-end encryption (AES-256-CBC)
- **Group chat rooms** for community discussions
- **Real-time notifications** for replies, messages, and mentions
- **Like/unlike** problems and solutions
- **Reputation system** with badges and recognition
- **Leaderboard** showcasing top contributors
- **Follow categories and tags** for personalized feed

#### 🛡️ Content Moderation
- **Intelligent bad word filtering** (1,360+ moderated terms)
- **Obfuscation detection** to prevent filter bypass
- **Report inappropriate content** with admin review
- **Text masking** for profanity
- **Spam detection** via rate limiting
- **Admin moderation dashboard**

#### 🔍 Discovery & Search
- **Full-text search** across users and posts
- **Category browsing** with hierarchical organization
- **Tag exploration** and trending tags
- **Advanced search filters** by date, popularity, solved status
- **Pagination** for efficient data loading
- **SEO-optimized** with sitemap and robots.txt

#### 🔒 Security Features
- **Password hashing** with bcrypt
- **CSRF protection** on all forms
- **SQL injection prevention** via prepared statements
- **XSS protection** through HTML escaping
- **Rate limiting** (120 requests/minute per IP)
- **AES-256-CBC encryption** for private messages
- **Email verification tokens**
- **Secure session management**

---

## 🛠️ Technology Stack

```
┌─────────────────────────────────────────────────────┐
│  FRONTEND LAYER                                      │
│  • HTML5                                             │
│  • CSS3 (Responsive, Dark/Light Mode)               │
│  • JavaScript (ES6+, Vanilla - No framework)        │
│  • Font Awesome 6.4.0 (Icons)                       │
│  • Google Fonts (Outfit, Source Sans 3)             │
└─────────────────────────────────────────────────────┘
                       ↓ AJAX
┌─────────────────────────────────────────────────────┐
│  BACKEND LAYER                                       │
│  • PHP 7.0+ (Object-Oriented & Procedural)          │
│  • PHPMailer v7.0 (Email - SMTP)                    │
│  • OAuth2 (Google & Facebook)                       │
│  • OpenSSL (AES-256-CBC Encryption)                 │
│  • Prepared Statements (Security)                   │
└─────────────────────────────────────────────────────┘
                       ↓ SQL
┌─────────────────────────────────────────────────────┐
│  DATA LAYER                                          │
│  • MySQL 5.7+ / MariaDB 10.2+                       │
│  • UTF-8MB4 Encoding (Multilingual)                 │
│  • 20+ Relational Tables                            │
└─────────────────────────────────────────────────────┘
```

### Dependencies

| Package | Purpose | Version |
|---------|---------|---------|
| `phpmailer/phpmailer` | Email delivery | ^7.0 |
| `league/oauth2-facebook` | Facebook authentication | ^2.2 |
| `league/oauth2-google` | Google authentication | ^5.0 |
| `guzzlehttp/guzzle` | HTTP client | (transitive) |

---

## 📁 Project Structure

```
muffeia/
├── 📄 index.php                    # Main dashboard (authenticated users)
├── 📄 landing.php                  # Public landing page
├── 📄 post_problem.php             # Problem submission
├── 📄 submit_solution.php          # Solution submission
├── 📄 send_message.php             # Message endpoint
├── 📄 verify_email.php             # Email verification handler
├── 📄 migrate.php                  # Database migration trigger
├── 📄 setup_admin.php              # Admin setup utility
│
├── 📁 api/                         # AJAX Endpoints (20+ files)
│   ├── load_posts.php              # Paginated posts
│   ├── load_messages.php           # Message fetching
│   ├── post_message.php            # Send encrypted message
│   ├── bookmark_post.php           # Save posts
│   ├── report_post.php             # Flag content
│   ├── accept_solution.php         # Mark best solution
│   ├── check_notifications.php     # Real-time notifications
│   └── ...
│
├── 📁 auth/                        # Authentication (6 files)
│   ├── login.php                   # Login interface
│   ├── api.php                     # OAuth & login endpoint
│   ├── logout.php                  # Logout
│   ├── forgot_password.php         # Password reset
│   └── reset_password.php          # Reset handler
│
├── 📁 pages/                       # Page Views (17 files)
│   ├── admin_dashboard.php         # Admin panel
│   ├── view_problem.php            # Problem detail
│   ├── profile.php                 # User profile
│   ├── message.php                 # Direct messaging
│   ├── notifications.php           # Notifications
│   ├── search.php                  # Search results
│   ├── leaderboard.php             # Rankings
│   └── ...
│
├── 📁 includes/                    # Core Libraries (18 files)
│   ├── config.php                  # Database credentials ⚠️ (ignored)
│   ├── db.php                      # Connection & init
│   ├── moderation.php              # Content filtering
│   ├── encryption.php              # AES-256-CBC
│   ├── reputation.php              # Points & badges
│   ├── categories_tags.php         # Organization
│   ├── email_verification.php      # Email system
│   ├── rate_limiter.php            # Anti-spam
│   ├── security.php                # Security utils
│   ├── migrations.php              # Schema definitions
│   └── badwords.txt                # 1,360+ terms
│
├── 📁 js/                          # JavaScript (9 files)
│   ├── scripts.js                  # Main logic
│   ├── logins.js                   # Auth handlers
│   ├── post-actions.js             # Post interactions
│   ├── notifications.js            # Real-time updates
│   └── mode.js                     # Theme toggle
│
├── 📁 css/                         # Stylesheets (13 files)
│   ├── muffeia-ui.css              # Main styles
│   ├── modern-theme.css            # Design
│   ├── responsive.css              # Mobile
│   ├── darkmodes.css               # Dark mode
│   └── all-min.css                 # Minified
│
├── 📁 community/                   # Static Pages
│   ├── about.php
│   ├── guidelines.php
│   ├── privacy.php
│   └── contact.php
│
├── 📁 uploads/                     # User Files
├── 📁 vendor/                      # Composer Dependencies
│
├── 🐳 .htaccess                   # Apache config
├── 🤖 robots.txt                  # SEO
├── 📦 composer.json               # Dependencies
├── 📦 composer.lock               # Locked versions
├── 🚫 .gitignore                  # Sensitive files
└── 📖 README.md                   # This file
```

---

## 🚀 Installation Guide

### Prerequisites

Before you begin, ensure you have:

- **PHP 7.0+** with these extensions:
  - `mysqli` (MySQL connection)
  - `openssl` (encryption)
  - `json` (data handling)
  - `spl` (libraries)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache** with `mod_rewrite` enabled
- **Composer** (for dependency management)
- **Git** (for version control)

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/muffeia.git
cd muffeia
```

### Step 2: Install Dependencies

```bash
composer install
```

This will install:
- `phpmailer/phpmailer` - Email functionality
- `league/oauth2-facebook` - Facebook authentication
- `league/oauth2-google` - Google authentication

### Step 3: Environment Configuration

**Create a `.env` file** (never commit this):

```bash
cp .env.example .env
```

**Edit `.env` with your credentials:**

```env
# Database Configuration
DB_HOST=your_db_host
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=your_db_name

# Application Settings
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila
APP_ENV=development

# Email Configuration (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your_email@gmail.com
MAIL_PASS=your_app_password

# OAuth Credentials
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
FACEBOOK_CLIENT_ID=your_facebook_app_id
FACEBOOK_CLIENT_SECRET=your_facebook_app_secret
```

> ⚠️ **Important**: Never commit `.env` file. It's included in `.gitignore`.

### Step 4: Set Up Database

**Option A: Using Migration Script (Recommended)**

```bash
# Start local server
php -S localhost:8000

# Visit in browser
http://localhost:8000/migrate.php
```

**Option B: Manual SQL Import**

```bash
mysql -h your_host -u your_user -p your_database < database/schema.sql
```

### Step 5: Configure Admin User

```sql
-- Set existing user as admin
UPDATE users SET is_admin = TRUE 
WHERE email = 'your_email@example.com';
```

Or use the setup utility:

```bash
php setup_admin.php
```

### Step 6: Start Development Server

```bash
php -S localhost:8000
```

Visit `http://localhost:8000` in your browser.

### Step 7: Verify Installation

- [ ] Home page loads (landing.php for guests)
- [ ] Registration works
- [ ] Email verification sends
- [ ] Login succeeds
- [ ] Can post problems
- [ ] Can submit solutions
- [ ] Dark mode toggle works
- [ ] Admin dashboard accessible

---

## ⚙️ Configuration

### Database Configuration

Edit `includes/config.php` (or use `.env`):

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'muffeia');
define('DB_CHARSET', 'utf8mb4');
define('TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Manila');
```

### Email Configuration

Configure SMTP in `includes/email_verification.php`:

```php
$mail->Host = getenv('MAIL_HOST');
$mail->Port = getenv('MAIL_PORT');
$mail->Username = getenv('MAIL_USER');
$mail->Password = getenv('MAIL_PASS');
```

### OAuth Configuration

Set up Google OAuth in `includes/config.php`:

```php
$googleClient = new \League\OAuth2\Client\Provider\Google([
    'clientId' => getenv('GOOGLE_CLIENT_ID'),
    'clientSecret' => getenv('GOOGLE_CLIENT_SECRET'),
    'redirectUri' => getenv('APP_URL') . '/auth/api.php?provider=google',
]);
```

### Content Moderation

Edit bad words list in `includes/badwords.txt` (one per line).

### Rate Limiting

Adjust in `includes/rate_limiter.php`:

```php
define('RATE_LIMIT_REQUESTS', 120);     // Requests
define('RATE_LIMIT_WINDOW', 60);        // Time window (seconds)
```

---

## 📖 Usage

### For Users

#### Creating an Account

1. Visit the landing page
2. Click "Sign Up"
3. Choose authentication method:
   - Email & Password (verify email)
   - Google OAuth
   - Facebook OAuth
4. Complete your profile

#### Posting a Problem

1. Click "Post a Problem"
2. Fill in:
   - **Title**: Clear, descriptive heading
   - **Description**: Detailed explanation
   - **Category**: Select relevant category
   - **Tags**: Add 1-5 tags
3. Click "Submit"

#### Finding Solutions

1. Browse categories or use search
2. Click on a problem to view details
3. Read solutions from community members
4. Accept the best solution (if problem owner)
5. Bookmark for later reference

#### Direct Messaging

1. Visit a user's profile
2. Click "Message"
3. Send encrypted message
4. All messages are end-to-end encrypted

### For Administrators

#### Access Admin Dashboard

1. Log in with admin account
2. Navigate to `/pages/admin_dashboard.php`

#### Moderate Content

- Review reported posts
- Remove inappropriate content
- Suspend/ban users if needed
- Manage categories and tags

#### View Statistics

- User growth trends
- Most active categories
- Top contributors
- System health metrics

---

## 🔌 API Endpoints

All API endpoints use **POST** requests with **CSRF tokens** and return **JSON**.

### Authentication

| Endpoint | Purpose |
|----------|---------|
| `POST /auth/api.php` | Login/OAuth handler |
| `POST /auth/logout.php` | Logout user |
| `POST /auth/forgot_password.php` | Request password reset |
| `POST /auth/reset_password.php` | Reset password with token |

### Posts

| Endpoint | Purpose |
|----------|---------|
| `POST /api/load_posts.php` | Fetch paginated posts |
| `POST /api/check_new_posts.php` | Check for new content |
| `POST /api/bookmark_post.php` | Toggle bookmark |
| `POST /api/report_post.php` | Report inappropriate content |
| `POST /api/delete_post.php` | Delete problem |
| `POST /api/accept_solution.php` | Mark best solution |

### Messaging

| Endpoint | Purpose |
|----------|---------|
| `POST /api/load_messages.php` | Fetch conversation |
| `POST /api/post_message.php` | Send encrypted message |
| `POST /api/delete_message.php` | Delete message |
| `POST /api/get_message_count.php` | Unread count |

### Notifications

| Endpoint | Purpose |
|----------|---------|
| `POST /api/check_notifications.php` | Fetch notifications |
| `POST /api/get_notification_count.php` | Unread count |
| `POST /api/delete_notification.php` | Mark as read |
| `POST /api/clear_all_notifications.php` | Clear all |

### Users

| Endpoint | Purpose |
|----------|---------|
| `POST /api/update_online_status.php` | Set presence |
| `POST /api/delete_user.php` | Delete account |
| `POST /api/schedule_account_deletion.php` | Request deletion |

### Examples

**Fetch Posts:**
```javascript
fetch('/api/load_posts.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    csrf_token: document.querySelector('[name="csrf_token"]').value,
    page: 1,
    category: 'technology'
  })
})
.then(r => r.json())
.then(data => console.log(data));
```

**Send Encrypted Message:**
```javascript
fetch('/api/post_message.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    csrf_token: csrfToken,
    recipient_id: 123,
    message: "Hello, this is encrypted!"
  })
})
.then(r => r.json());
```

---

## 🔐 Security

<svg width="100%" height="200" viewBox="0 0 800 200" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 20px auto; display: block;">
  <defs>
    <style>
      @keyframes shield {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
      }
      .shield { animation: shield 2s ease-in-out infinite; }
    </style>
  </defs>
  
  <!-- Title -->
  <text x="400" y="30" font-family="Arial, sans-serif" font-size="24" fill="#000" text-anchor="middle" font-weight="bold">Security Layers</text>
  
  <!-- Layer 1: Authentication -->
  <g class="shield">
    <rect x="50" y="50" width="150" height="80" rx="5" fill="#10b981" opacity="0.3" stroke="#10b981" stroke-width="2"/>
    <text x="125" y="70" font-family="Arial, sans-serif" font-size="12" fill="#000" text-anchor="middle" font-weight="bold">Authentication</text>
    <text x="125" y="90" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">Bcrypt Hashing</text>
    <text x="125" y="105" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">OAuth2 Support</text>
  </g>
  
  <!-- Layer 2: Encryption -->
  <g class="shield" style="animation-delay: 0.2s;">
    <rect x="325" y="50" width="150" height="80" rx="5" fill="#f59e0b" opacity="0.3" stroke="#f59e0b" stroke-width="2"/>
    <text x="400" y="70" font-family="Arial, sans-serif" font-size="12" fill="#000" text-anchor="middle" font-weight="bold">Encryption</text>
    <text x="400" y="90" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">AES-256-CBC</text>
    <text x="400" y="105" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">End-to-End</text>
  </g>
  
  <!-- Layer 3: Validation -->
  <g class="shield" style="animation-delay: 0.4s;">
    <rect x="600" y="50" width="150" height="80" rx="5" fill="#ec4899" opacity="0.3" stroke="#ec4899" stroke-width="2"/>
    <text x="675" y="70" font-family="Arial, sans-serif" font-size="12" fill="#000" text-anchor="middle" font-weight="bold">Input Validation</text>
    <text x="675" y="90" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">SQL Injection Prevention</text>
    <text x="675" y="105" font-family="Arial, sans-serif" font-size="10" fill="#333" text-anchor="middle">XSS Protection</text>
  </g>
  
  <!-- Layer 4: Rate Limiting -->
  <g class="shield" style="animation-delay: 0.6s;">
    <rect x="180" y="145" width="150" height="45" rx="5" fill="#06b6d4" opacity="0.3" stroke="#06b6d4" stroke-width="2"/>
    <text x="255" y="160" font-family="Arial, sans-serif" font-size="11" fill="#000" text-anchor="middle" font-weight="bold">Rate Limiting</text>
    <text x="255" y="178" font-family="Arial, sans-serif" font-size="9" fill="#333" text-anchor="middle">120 req/min per IP</text>
  </g>
  
  <!-- Layer 5: CSRF Protection -->
  <g class="shield" style="animation-delay: 0.8s;">
    <rect x="470" y="145" width="150" height="45" rx="5" fill="#8b5cf6" opacity="0.3" stroke="#8b5cf6" stroke-width="2"/>
    <text x="545" y="160" font-family="Arial, sans-serif" font-size="11" fill="#000" text-anchor="middle" font-weight="bold">CSRF Protection</text>
    <text x="545" y="178" font-family="Arial, sans-serif" font-size="9" fill="#333" text-anchor="middle">Token Validation</text>
  </g>
</svg>

### Security Best Practices

#### 1. **Environment Variables**

Never hardcode sensitive data. Use `.env` file:

```bash
# .env (NEVER commit)
DB_HOST=localhost
DB_USER=root
DB_PASS=secure_password
MAIL_PASS=smtp_password
GOOGLE_CLIENT_SECRET=xxx
```

Load in config:
```php
$db_password = getenv('DB_PASS') ?: '';
```

#### 2. **Password Security**

- Passwords hashed with **bcrypt** (PASSWORD_DEFAULT)
- Never log or display passwords
- Force password reset after admin compromise
- Implement password expiration policy

```php
$hashed = password_hash($password, PASSWORD_DEFAULT);
if (password_verify($input, $hashed)) { /* Login */ }
```

#### 3. **SQL Injection Prevention**

Use **prepared statements** exclusively:

```php
// ✅ GOOD: Prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// ❌ BAD: String concatenation
$query = "SELECT * FROM users WHERE email = '" . $email . "'";
```

#### 4. **XSS Protection**

Escape all user input in HTML:

```php
// ✅ GOOD: HTML escaping
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// ❌ BAD: Unescaped output
echo $user_input;
```

#### 5. **CSRF Protection**

All forms require CSRF tokens:

```php
// Generate token
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;

// Validate on submit
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token invalid');
}
```

#### 6. **Message Encryption**

End-to-end encryption with AES-256-CBC:

```php
// Encrypt
$encrypted = openssl_encrypt($message, 'AES-256-CBC', $key, 0, $iv);

// Decrypt
$decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
```

#### 7. **Rate Limiting**

Prevent brute force attacks:

```php
// Check limit
if (isRateLimited($ip)) {
    http_response_code(429);
    die('Too many requests');
}

// Track request
recordRequest($ip);
```

#### 8. **Content Moderation**

Automatic filtering of inappropriate content:

```php
// Bad word detection
if (containsModeratedContent($text)) {
    $text = maskProfanity($text);  // Mask as **
}
```

### Security Checklist

- [ ] `.env` file created and added to `.gitignore`
- [ ] All database credentials in `.env` (not in code)
- [ ] `includes/config.php` added to `.gitignore`
- [ ] HTTPS enabled in production
- [ ] HTTPS redirect in `.htaccess`
- [ ] OAuth credentials stored in `.env`
- [ ] SMTP password stored in `.env`
- [ ] File permissions: `chmod 600` for config files
- [ ] Regular backups implemented
- [ ] SQL backups encrypted and stored securely
- [ ] Error logging to file (not displayed to users)
- [ ] Update PHP and MySQL regularly

---

## 🌍 Deployment

### Hosting Options

#### Option 1: Shared Hosting (e.g., InfinityFree, Bluehost)

1. **Upload files via FTP/SFTP**
   ```bash
   sftp user@host.com
   put -r muffeia/* /public_html/
   ```

2. **Create database**
   - Via hosting control panel (cPanel, Plesk)
   - Run migrations

3. **Configure `.env`**
   - Set production database credentials
   - Set production SMTP credentials

4. **Enable HTTPS**
   - Use Let's Encrypt (free)
   - Update `.htaccess` to force HTTPS

### Option 2: VPS (e.g., DigitalOcean, Linode)

1. **Install LEMP Stack**
   ```bash
   sudo apt update
   sudo apt install php php-cli php-fpm php-mysql nginx mysql-server
   ```

2. **Clone repository**
   ```bash
   git clone https://github.com/yourusername/muffeia.git
   cd muffeia
   composer install
   ```

3. **Configure Nginx**
   ```nginx
   server {
       listen 80;
       server_name muffeia.com;
       root /var/www/muffeia;
       
       location ~ \.php$ {
           fastcgi_pass unix:/run/php/php-fpm.sock;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
   }
   ```

4. **Set SSL with Certbot**
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d muffeia.com
   ```

### Option 3: Docker

**Dockerfile:**
```dockerfile
FROM php:7.4-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite
COPY . /var/www/html/
COPY vendor /var/www/html/vendor
```

**docker-compose.yml:**
```yaml
version: '3'
services:
  web:
    build: .
    ports:
      - "80:80"
  db:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: muffeia
      MYSQL_ROOT_PASSWORD: password
```

### Pre-Deployment Checklist

- [ ] All environment variables in `.env`
- [ ] Database backed up
- [ ] HTTPS certificate installed
- [ ] `.htaccess` configured for production
- [ ] Error logs set to private location
- [ ] Admin account created
- [ ] Email testing successful
- [ ] OAuth apps configured
- [ ] Rate limiting configured
- [ ] Backup schedule set up
- [ ] Monitoring enabled
- [ ] Performance optimized

---

## 👥 Contributing

We welcome contributions! Here's how to get started.

### Code of Conduct

- Be respectful and inclusive
- Report issues responsibly
- Follow the project's code style
- Write clear commit messages

### Development Workflow

1. **Fork the repository**
   ```bash
   git clone https://github.com/yourusername/muffeia.git
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

3. **Make your changes**
   - Write clean, documented code
   - Follow PSR-12 PHP coding standards
   - Add comments for complex logic

4. **Test your changes**
   - Test in browser
   - Test across devices (mobile, tablet, desktop)
   - Test in light and dark mode

5. **Commit and push**
   ```bash
   git add .
   git commit -m "Add amazing feature"
   git push origin feature/amazing-feature
   ```

6. **Open a Pull Request**
   - Describe changes clearly
   - Link related issues
   - Wait for review

### Reporting Bugs

Open an issue with:
- Clear title
- Detailed description
- Steps to reproduce
- Expected vs actual behavior
- Screenshots if applicable
- Your environment (OS, browser, PHP version)

### Suggesting Features

Describe:
- Use case
- Expected behavior
- Mockups or wireframes
- Potential implementation approach

---

## 📄 License

This project is licensed under the **MIT License** - see [LICENSE](LICENSE) file for details.

---

## 💬 Support

### Getting Help

- **Documentation**: See [DOCUMENTATION_INDEX.txt](DOCUMENTATION_INDEX.txt)
- **FAQ**: Check existing GitHub issues
- **Email**: support@muffeia.com

### Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [OAuth2 Guide](https://oauth.net/2/)
- [OWASP Security](https://owasp.org/)

### Community

- GitHub Issues for bug reports
- GitHub Discussions for questions
- Contributing guidelines above

---

<svg width="100%" height="100" viewBox="0 0 800 100" xmlns="http://www.w3.org/2000/svg" style="max-width: 800px; margin: 40px auto; display: block;">
  <defs>
    <style>
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      .footer-text { animation: fadeIn 1s ease-in-out; }
    </style>
  </defs>
  
  <line x1="0" y1="20" x2="800" y2="20" stroke="#ddd" stroke-width="2"/>
  
  <text x="400" y="55" font-family="Arial, sans-serif" font-size="16" fill="#6366f1" text-anchor="middle" font-weight="bold" class="footer-text">
    Built with ❤️ by the Muffeia Community
  </text>
  
  <text x="400" y="80" font-family="Arial, sans-serif" font-size="12" fill="#999" text-anchor="middle" class="footer-text" style="animation-delay: 0.3s;">
    © 2026 Muffeia. Making communities safer, one problem at a time.
  </text>
</svg>

---

**Last Updated**: June 1, 2026  
**Version**: 3.0  
**Status**: Production Ready ✅

---

### Quick Links

- 🌐 [Visit Muffeia](https://muffeia.xo.je)
- 📚 [API Reference](API_REFERENCE.md)
- 🚀 [Quick Start](QUICK_SETUP.md)
- 🔧 [Setup Guide](SETUP_GUIDE.md)
- 🐛 [Report Issues](https://github.com/yourusername/muffeia/issues)
- 💡 [Request Feature](https://github.com/yourusername/muffeia/issues/new)
