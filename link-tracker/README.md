# 📁 Link Tracker - Folder Contents

This folder contains the complete Link Tracker system.

---

## 🚀 Quick Start

1. **Upload this entire folder** to your domain root
2. **Run `INSTALL.sql`** in PHPMyAdmin
3. **Edit `config.php`** with your database credentials
4. **Add .htaccess rule** to root directory
5. **Visit** `/link-tracker/link-tracking.php` and login

**Default Login:**
- Username: `admin`
- Password: `changeme123`
- ⚠️ Change password immediately!

---

## 📄 File Overview

### **Installation Files:**
- `INSTALL.sql` - Complete database setup (run this in PHPMyAdmin)
- `README.md` - This file
- `config.php` - Configuration (**EDIT THIS with your settings**)
- `install.php` - Web-based installer (optional alternative to INSTALL.sql)
- `schema.sql` - Database reference/documentation

### **Core System Files:**
- `tracker.php` - Handles all redirects and click tracking
- `db.php` - Database PDO connection
- `functions.php` - Helper functions library
- `auth.php` - Authentication and session management

### **Admin Pages:**
- `link-tracking.php` - Login page
- `dashboard.php` - Overview dashboard with stats
- `links.php` - Link management (create/edit/delete)
- `placements.php` - View all link placements
- `link-detail.php` - Manage placements for specific link
- `placement-form.php` - Add/edit placement form
- `analytics.php` - Detailed analytics with charts
- `settings.php` - Settings and password management
- `logout.php` - Logout handler

### **Folders:**
- `ajax/` - AJAX endpoints for all operations (12 files)
- `assets/` - CSS, JavaScript, and favicon
- `includes/` - Header and footer templates

### **Security:**
- `.htaccess` - Protects sensitive files (config.php, etc.)

---

## ⚙️ Configuration

**Edit `config.php` and update:**

```php
// Database (REQUIRED)
define('DB_NAME', 'your_database_name');     // ← Your database
define('DB_USER', 'your_username');          // ← Your DB user
define('DB_PASS', 'your_password');          // ← Your DB password

// Domain (REQUIRED)
define('SITE_URL', 'https://yourdomain.com'); // ← Your domain

// Security (RECOMMENDED)  
define('FINGERPRINT_SALT', 'random-string');  // ← Generate random
define('SESSION_SECRET', 'random-string');    // ← Generate random
define('CSRF_SECRET', 'random-string');       // ← Generate random
```

---

## 🔗 URL Structure

**Admin Panel:**
```
https://yourdomain.com/link-tracker/link-tracking.php
```

**Tracked Links:**
```
https://yourdomain.com/best/{slug}
```

Example: `https://yourdomain.com/best/letslucky-casino`

---

## 📊 Database Tables

**7 tables created by INSTALL.sql:**

1. `lt_links` - Redirect links
2. `lt_clicks` - Click tracking data
3. `lt_placements` - Link placement tracking
4. `lt_users` - Admin users
5. `lt_sessions` - Login sessions
6. `lt_login_attempts` - Security tracking
7. `lt_system_resets` - Data reset history

---

## 🛡️ Security

**Protected Files (via .htaccess):**
- config.php
- db.php
- functions.php
- auth.php
- schema.sql
- All log files

**Session Security:**
- Token-based authentication
- IP + User Agent validation
- Automatic expiration (24 hours)

**Brute Force Protection:**
- CAPTCHA after 3 failed attempts
- 15-minute lockout after 5 attempts
- 1-hour lockout after 10 attempts

---

## 📝 Support

**For installation help:**
- See main README.md in root directory
- Check DEPLOYMENT_CHECKLIST.md for step-by-step guide
- Review Troubleshooting section in main README

**Common Issues:**
- Database connection → Check config.php credentials
- 404 on /best/ links → Check root .htaccess rewrite rule
- Blank pages → Enable DEBUG_MODE in config.php
- Can't login → Reset password via SQL query

---

## 🔄 Updates

**To update:**
1. Backup current config.php
2. Upload new files
3. Restore your config.php
4. Test admin panel

**Database is backward compatible** - no migration needed for minor updates.

---

## ⚡ Quick Commands

**Reset Admin Password:**
```sql
UPDATE lt_users 
SET password_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GkJRvVVVpBVG' 
WHERE username = 'admin';
-- Password will be: changeme123
```

**Clear All Click Data:**
```sql
TRUNCATE TABLE lt_clicks;
```

**View Recent Clicks:**
```sql
SELECT * FROM lt_clicks ORDER BY clicked_at DESC LIMIT 20;
```

---

**Version:** 1.0.0  
**Last Updated:** October 2024  
**PHP Requirement:** 8.0+  
**MySQL Requirement:** 5.7+

---

*For complete documentation, see main README.md in root directory*

