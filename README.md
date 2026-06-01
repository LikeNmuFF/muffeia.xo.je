<div align="center">

<!-- ANIMATED HEADER WAVE -->
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0f0c29,50:302b63,100:24243e&height=200&section=header&text=Muffeia&fontSize=72&fontColor=ffffff&fontAlignY=38&desc=Community%20Problem-Sharing%20Platform&descAlignY=60&descSize=20&animation=fadeIn&fontAlign=50" width="100%"/>

<!-- ANIMATED TYPING HEADLINE -->

[![Typing SVG](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=700&size=22&pause=1000&color=A78BFA&center=true&vCenter=true&width=700&lines=%F0%9F%94%92+Privacy-first+community+platform;%F0%9F%92%AC+End-to-end+encrypted+messaging;%F0%9F%8C%8D+Where+problems+find+their+solutions;%F0%9F%9B%A1%EF%B8%8F+Intelligent+content+moderation;%E2%9A%A1+Real-time+notifications+%26+presence)](https://github.com/LikeNmuFF)

<br/>

<!-- SHIELD BADGES ROW 1 -->
<p>
  <img src="https://img.shields.io/badge/Version-3.0-blueviolet?style=for-the-badge&logo=rocket&logoColor=white" />
  <img src="https://img.shields.io/badge/Status-Production%20Ready-22c55e?style=for-the-badge&logo=checkmarx&logoColor=white" />
  <img src="https://img.shields.io/badge/License-MIT-f59e0b?style=for-the-badge&logo=opensourceinitiative&logoColor=white" />
  <img src="https://img.shields.io/badge/PHP-7.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" />
</p>

<!-- SHIELD BADGES ROW 2 -->
<p>
  <img src="https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/Apache-mod__rewrite-D22128?style=for-the-badge&logo=apache&logoColor=white" />
  <img src="https://img.shields.io/badge/Encryption-AES--256--CBC-0ea5e9?style=for-the-badge&logo=letsencrypt&logoColor=white" />
  <img src="https://img.shields.io/badge/Moderation-1360%2B%20Terms-ef4444?style=for-the-badge&logo=shield&logoColor=white" />
</p>

<br/>

</div>

---

## ✦ What is Muffeia?

> **A sophisticated, full-stack community platform** where people share their problems and find solutions together — built with **privacy**, **security**, and **inclusivity** at its core.

<table>
<tr>
<td width="50%">

**For Users**

- 📮 Post problems, get community solutions
- 💬 End-to-end encrypted DMs
- 🏆 Earn reputation & badges
- 🔖 Bookmark & follow topics
- 🕵️ Post anonymously if needed

</td>
<td width="50%">

**For the Community**

- 🛡️ Intelligent bad-word filtering
- 🌍 Multi-language (UTF-8MB4)
- 🔍 Full-text search & trending tags
- 📊 Leaderboard of top contributors
- 👥 Group chat rooms

</td>
</tr>
</table>

---

## ✦ Technology Stack

<div align="center">

<img src="https://skillicons.dev/icons?i=php,mysql,js,html,css,apache,git,docker,github,composer&theme=dark&perline=10" />

</div>

<br/>

<div align="center">

|      Layer      |        Technology        | Detail                                |
| :-------------: | :----------------------: | :------------------------------------ |
| 🖥️ **Backend**  |         PHP 7.0+         | 299 PHP files, MVC-style architecture |
| 🗄️ **Database** | MySQL 5.7 / MariaDB 10.2 | Prepared statements, full-text search |
| ⚡ **Frontend** |     Vanilla JS + CSS     | 9 JS files, 13 stylesheets, dark mode |
|   🔐 **Auth**   |  OAuth2 + JWT sessions   | Google, Facebook, Email/Password      |
|  📧 **Email**   |      PHPMailer 7.0       | SMTP with TLS                         |
|  🔒 **Crypto**  |   OpenSSL AES-256-CBC    | End-to-end encrypted messages         |

</div>

---

## ✦ Core Features

<div align="center">

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   👤 USER         📝 CONTENT        💬 COMMUNITY           │
│  ─────────       ──────────        ───────────             │
│  OAuth Login     Post Problems     Encrypted DMs           │
│  Email Verify    Submit Solutions  Group Chatrooms         │
│  Profiles        Accept Answer     Real-time Notifs        │
│  Online Status   Bookmarks         Leaderboard             │
│  Account Mgmt    View Analytics    Reputation Badges       │
│                                                             │
│   🔍 DISCOVERY    🛡️ MODERATION     🔒 SECURITY            │
│  ──────────      ────────────      ──────────             │
│  Full-text Search 1360+ Terms      bcrypt Passwords        │
│  Category Browse  Obfuscation Det. CSRF Tokens             │
│  Trending Tags    Report System    SQL Prepared Stmts      │
│  Advanced Filters Spam Detection   XSS Escaping            │
│  SEO Sitemap      Admin Dashboard  Rate Limit 120/min      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

</div>

---

## ✦ Project Structure

```
muffeia/                          📦 19MB  |  299 PHP  |  9 JS  |  13 CSS
│
├── 📄 index.php                  ← Main dashboard (authenticated)
├── 📄 landing.php                ← Public landing page
├── 📄 post_problem.php           ← Problem submission
├── 📄 submit_solution.php        ← Solution submission
│
├── 📁 api/          (20+ files)  ← AJAX endpoints (JSON responses)
│   ├── load_posts.php            ← Paginated posts
│   ├── post_message.php          ← Send encrypted message
│   ├── bookmark_post.php         ← Save posts
│   └── ...15+ more
│
├── 📁 auth/         (6 files)    ← OAuth + session management
├── 📁 pages/        (17 files)   ← Page views
├── 📁 includes/     (18 files)   ← Core libraries
│   ├── config.php                ← ⚠️ .gitignored — never commit
│   ├── moderation.php            ← Content filtering
│   ├── encryption.php            ← AES-256-CBC
│   └── reputation.php            ← Points & badges
│
├── 📁 js/  📁 css/               ← Frontend assets
├── 📁 community/                 ← Static pages (about, privacy…)
└── 📁 vendor/                    ← Composer dependencies
```

---

## ✦ Installation

### Prerequisites

<div align="center">

![PHP](https://img.shields.io/badge/PHP-≥7.0-777BB4?logo=php&logoColor=white&style=flat-square)
![MySQL](https://img.shields.io/badge/MySQL-≥5.7-4479A1?logo=mysql&logoColor=white&style=flat-square)
![Apache](https://img.shields.io/badge/Apache-mod__rewrite-D22128?logo=apache&logoColor=white&style=flat-square)
![Composer](https://img.shields.io/badge/Composer-required-885630?logo=composer&logoColor=white&style=flat-square)

</div>

```bash
# 1. Clone
git clone https://github.com/yourusername/muffeia.git && cd muffeia

# 2. Install PHP dependencies
composer install

# 3. Copy env template and fill in your credentials
cp .env.example .env

# 4. Run database migrations
php -S localhost:8000
# → visit http://localhost:8000/migrate.php

# 5. Set your account as admin
mysql -u root -p muffeia -e \
  "UPDATE users SET is_admin=TRUE WHERE email='you@example.com';"
```

### Environment Variables

```env
# ── Database ──────────────────────────────
DB_HOST=localhost
DB_USER=root
DB_PASS=your_secure_password
DB_NAME=muffeia

# ── Application ───────────────────────────
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila
APP_ENV=development

# ── Email (SMTP) ──────────────────────────
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your@gmail.com
MAIL_PASS=your_app_password

# ── OAuth ─────────────────────────────────
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxx
FACEBOOK_CLIENT_ID=xxxxx
FACEBOOK_CLIENT_SECRET=xxxxx
```

> [!WARNING]
> **Never commit `.env` or `includes/config.php`** — both are in `.gitignore`. These contain credentials and encryption keys.

---

## ✦ API Reference

All endpoints accept **POST** with a valid `csrf_token` and return **JSON**.

<details>
<summary><b>📮 Posts</b></summary>

| Endpoint                        | Purpose             |
| ------------------------------- | ------------------- |
| `POST /api/load_posts.php`      | Paginated post feed |
| `POST /api/bookmark_post.php`   | Toggle bookmark     |
| `POST /api/report_post.php`     | Flag content        |
| `POST /api/delete_post.php`     | Delete problem      |
| `POST /api/accept_solution.php` | Mark best solution  |

```js
// Example: fetch paginated posts
const res = await fetch("/api/load_posts.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    csrf_token: document.querySelector('[name="csrf_token"]').value,
    page: 1,
    category: "technology",
  }),
});
const { posts, total } = await res.json();
```

</details>

<details>
<summary><b>💬 Messaging</b></summary>

| Endpoint                          | Purpose                |
| --------------------------------- | ---------------------- |
| `POST /api/load_messages.php`     | Fetch conversation     |
| `POST /api/post_message.php`      | Send encrypted message |
| `POST /api/delete_message.php`    | Delete message         |
| `POST /api/get_message_count.php` | Unread count           |

</details>

<details>
<summary><b>🔔 Notifications</b></summary>

| Endpoint                                | Purpose             |
| --------------------------------------- | ------------------- |
| `POST /api/check_notifications.php`     | Fetch notifications |
| `POST /api/get_notification_count.php`  | Unread count        |
| `POST /api/clear_all_notifications.php` | Clear all           |

</details>

<details>
<summary><b>👤 Users & Auth</b></summary>

| Endpoint                             | Purpose               |
| ------------------------------------ | --------------------- |
| `POST /auth/api.php`                 | Login / OAuth handler |
| `POST /auth/logout.php`              | Logout                |
| `POST /auth/forgot_password.php`     | Request reset         |
| `POST /api/update_online_status.php` | Set presence          |
| `POST /api/delete_user.php`          | Delete account        |

</details>

---

## ✦ Security Architecture

<div align="center">

```
        ┌──────────────────────────────────────────┐
        │              REQUEST LIFECYCLE            │
        └──────────────────────────────────────────┘
                           │
              ┌────────────▼────────────┐
              │   🚦 Rate Limiter       │  120 req/min per IP
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │   🛡️ CSRF Validation    │  bin2hex(random_bytes(32))
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │   🔐 Auth Check         │  bcrypt · OAuth2 · Sessions
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │   🧹 Input Sanitization │  XSS escape · SQL prep stmts
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │   🤬 Content Moderation │  1360+ terms · obfusc. detect
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │   💾 Database / Storage │  Encrypted DMs · Hashed PW
              └─────────────────────────┘
```

</div>

| Threat            | Mitigation                                    |
| ----------------- | --------------------------------------------- |
| SQL Injection     | Prepared statements everywhere                |
| XSS               | `htmlspecialchars()` on all output            |
| CSRF              | Per-session token on every form               |
| Brute Force       | Rate limiting (429 after threshold)           |
| Password Leak     | bcrypt with `PASSWORD_DEFAULT`                |
| Message Intercept | AES-256-CBC end-to-end encryption             |
| Spam / Abuse      | 1360+ term moderation + obfuscation detection |

---

## ✦ Deployment

<details>
<summary><b>🐳 Docker (Recommended)</b></summary>

```yaml
# docker-compose.yml
version: "3"
services:
  web:
    build: .
    ports: ["80:80"]
    env_file: .env
  db:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: muffeia
      MYSQL_ROOT_PASSWORD: ${DB_PASS}
    volumes:
      - db_data:/var/lib/mysql
volumes:
  db_data:
```

```bash
docker-compose up -d
```

</details>

<details>
<summary><b>🖥️ VPS (DigitalOcean / Linode)</b></summary>

```bash
sudo apt update && sudo apt install php php-fpm php-mysql nginx mysql-server -y
git clone https://github.com/yourusername/muffeia.git /var/www/muffeia
cd /var/www/muffeia && composer install
sudo certbot --nginx -d muffeia.com   # Free SSL
```

</details>

<details>
<summary><b>📦 Shared Hosting (cPanel / InfinityFree)</b></summary>

1. Upload files via FTP/SFTP to `public_html/`
2. Create MySQL database via cPanel
3. Import `database/schema.sql`
4. Set `.env` with production credentials
5. Enable HTTPS via Let's Encrypt in cPanel

</details>

> [!TIP]
> Run through the **pre-launch checklist**: `.env` in place → HTTPS cert → OAuth apps pointed to prod domain → test email delivery → confirm admin access → enable error logging to file.

---

## ✦ Contributing

```bash
# Fork → Clone → Branch → Build → PR
git checkout -b feature/your-feature-name
# write clean, PSR-12 compliant PHP
git commit -m "feat: describe your change clearly"
git push origin feature/your-feature-name
```

- Follow **PSR-12** PHP coding standards
- Test on mobile + desktop, light + dark mode
- Open a PR with a clear description and linked issue

---

## ✦ License & Support

<div align="center">

![License](https://img.shields.io/badge/License-MIT-f59e0b?style=for-the-badge)
[![Email](https://img.shields.io/badge/Support-support%40muffeia.com-8b5cf6?style=for-the-badge&logo=gmail&logoColor=white)](mailto:support@muffeia.com)

</div>

- 📖 **Docs**: `DOCUMENTATION_INDEX.txt`
- 🐛 **Bugs**: [GitHub Issues](https://github.com/yourusername/muffeia/issues)
- 💬 **Questions**: [GitHub Discussions](https://github.com/yourusername/muffeia/discussions)
- 📚 **Resources**: [PHP Docs](https://www.php.net/docs.php) · [OWASP](https://owasp.org/) · [OAuth2](https://oauth.net/2/)

---

<div align="center">

<!-- ANIMATED FOOTER WAVE -->
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:24243e,50:302b63,100:0f0c29&height=120&section=footer&animation=fadeIn" width="100%"/>

**Built with ❤️ by the Muffeia Community**

_Making communities safer, one problem at a time._

![Visitors](https://visitor-badge.laobi.icu/badge?page_id=yourusername.muffeia&style=flat-square&color=blueviolet)
&nbsp;
![Last Commit](https://img.shields.io/github/last-commit/LikeNmuFF/muffeia?style=flat-square&color=302b63&label=last+commit)
&nbsp;
**v3.0 · June 2026**

</div>
