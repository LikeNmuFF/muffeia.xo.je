# 🚀 Quick Setup - How to Run Migrations

## Step-by-Step Setup Guide

### Option 1: Using the Setup Admin Page (Easiest)

1. **Open your browser** and go to:
   ```
   http://localhost/xampp/htdocs/setup_admin.php
   ```

2. **Create Admin Account**:
   - Enter Admin Username (e.g., `admin`)
   - Enter Admin Email (e.g., `admin@admin.com`)
   - Enter Admin Password (min 8 characters)
   - Click "Create Admin Account"

3. **You'll see confirmation**, then visit:
   ```
   http://localhost/xampp/htdocs/migrate.php
   ```

4. **Run Migrations**:
   - All database tables will be created automatically
   - You'll see success messages for each table
   - Categories and badges will be seeded

✅ **Done!** You now have:
- Admin account created
- All database tables set up
- Categories and badges initialized

---

### Option 2: Using MySQL Command Line (Alternative)

If you want to do it via MySQL:

1. **Open MySQL Command Line** or phpMyAdmin

2. **Create an admin user** with this command:
   ```sql
   UPDATE users SET is_admin = TRUE WHERE email = 'admin@admin.com';
   ```
   
   (Replace `admin@admin.com` with your actual admin email)

3. **Verify it worked**:
   ```sql
   SELECT email, is_admin FROM users WHERE is_admin = TRUE;
   ```

4. **Then visit**:
   ```
   http://localhost/xampp/htdocs/migrate.php
   ```

---

### Option 3: If You Access from Remote Server

If accessing from a remote server (not localhost):

1. **First time only**: The migration script allows first-time setup
   - Go to `/migrate.php`
   - It will detect this is the first setup
   - Migrations will run automatically

2. **After that**: You'll need to login as admin to run migrations

---

## What Happens When You Run Migrations

When you visit `/migrate.php`:

✅ **12 database tables created**:
- categories
- tags
- problem_tags
- reputation_points
- user_badges
- badges
- email_verifications
- admin_logs
- moderation_queue
- And more...

✅ **5 columns added to users table**:
- email_verified
- verification_token
- is_admin
- reputation_score
- is_banned

✅ **Default data seeded**:
- 8 categories (Personal, Career, Education, etc.)
- 6 achievement badges

---

## Troubleshooting

### "Migrations can only be run..."

**Solution**: Use `/setup_admin.php` to create admin account first, then try `/migrate.php`

### "Error: Unknown database"

**Solution**: Make sure your `includes/db.php` connects to the correct database (`muffeia2`)

### "Table already exists"

**Solution**: This is normal if migrations already ran. Everything is already set up!

### "Email not verified"

**Solution**: New users will need to verify their email. That's working as intended.

---

## After Setup is Complete

### Login as Admin

1. Go to `/auth/login.php`
2. Enter your admin credentials
3. You should be logged in

### Access Admin Dashboard

1. Go to `/pages/admin_dashboard.php`
2. You should see the admin panel

### Access New Features

- **Leaderboard**: `/pages/leaderboard.php`
- **Browse Categories**: `/pages/browse_category.php?category_id=1`
- **Browse Tags**: `/pages/browse_tags.php`
- **Search**: `/pages/search.php`

---

## URLs Reference

| Feature | URL | Access |
|---------|-----|--------|
| Setup Admin | `/setup_admin.php` | Anyone (first time) |
| Database Migration | `/migrate.php` | Admin or localhost |
| Admin Dashboard | `/pages/admin_dashboard.php` | Admin only |
| Leaderboard | `/pages/leaderboard.php` | Logged in |
| Email Verification | `/verify_email.php` | Auto on signup |

---

## Need Help?

If you get stuck:

1. **Check the logs**:
   - Look in browser console (F12)
   - Check server error logs

2. **Verify database connection**:
   - Check `includes/db.php` configuration
   - Make sure MySQL is running
   - Make sure `muffeia2` database exists

3. **Verify user exists**:
   - Use phpMyAdmin to check if your user exists
   - Check `is_admin` column value

---

**Status**: Ready to setup! 🎉

Start with `/setup_admin.php` → then `/migrate.php` → Enjoy MUFFEIA v3!
