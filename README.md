# CostsCompared.com - Link Tracking System

Professional link tracking and analytics software deployed on **costscompared.com**.

[![Deploy Status](https://img.shields.io/badge/deploy-automated-success)](https://github.com)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)

---

## 🌐 Live Website

**Website:** https://costscompared.com

**Admin Panel:** https://costscompared.com/link-tracker/link-tracking.php

**GitHub Repository:** https://github.com/WarrenEnterprises/costscomparedcom

**Default Login:**
- Username: `Links`
- Password: `VerySecretTesting888**!!`

---

## 🚀 Deployment

This repository uses **GitHub Actions** for automatic deployment to Hostinger via FTP.

### Every push to `main` branch automatically:
1. ✅ Triggers GitHub Actions workflow
2. ✅ Deploys all files to Hostinger FTP
3. ✅ Updates costscompared.com live site
4. ✅ Takes 2-5 minutes to complete

### Required GitHub Secrets

Configure these 4 secrets in your repository:

| Secret Name | Value |
|------------|--------|
| `FTP_SERVER` | `195.35.31.80` |
| `FTP_USERNAME` | `u609212978.costscomparedun` |
| `FTP_PASSWORD` | `&erAFQRDJ&BRcBnR%w5M*L` |
| `FTP_SERVER_DIR` | `/` |

**To add secrets:**
1. Go to repository **Settings**
2. Navigate to **Secrets and variables** → **Actions**
3. Click **"New repository secret"**
4. Add each secret one by one

---

## 📦 Database Information

**Database:** `u609212978_costscompared`
**User:** `u609212978_costscomparedu`
**Password:** `&erAFQRDJ&BRcBnR%w5M*L`
**Host:** `localhost`

**Tables:** 7 total (all prefixed with `lt_`)
- `lt_links` - Redirect links
- `lt_clicks` - Click tracking data
- `lt_placements` - Link placements
- `lt_users` - Admin users
- `lt_sessions` - Active sessions
- `lt_login_attempts` - Security logs
- `lt_system_resets` - Data management

---

## ✨ Features

- ✅ Custom redirect URLs (`costscompared.com/best/your-slug`)
- ✅ Comprehensive analytics (20+ data points per click)
- ✅ Bot detection & GeoIP tracking
- ✅ Placement management & tracking
- ✅ Beautiful responsive admin dashboard
- ✅ CSV exports & interactive charts
- ✅ Secure authentication & session management

---

## 🧪 Quick Setup

### 1. Run Database Setup (Required!)

1. Log into Hostinger PHPMyAdmin
2. Select database: `u609212978_costscompared`
3. Click **SQL** tab
4. Copy ALL contents of `link-tracker/INSTALL.sql`
5. Paste and click **Go**
6. Verify 7 tables created

### 2. Test Admin Login

Visit: https://costscompared.com/link-tracker/link-tracking.php

Login:
- Username: `Links`
- Password: `VerySecretTesting888**!!`

### 3. Create Test Link

1. Manage Links → Create New Link
2. Title: `Test Link`
3. Slug: `test`
4. Destination: `https://google.com`
5. Save

### 4. Test Redirect

Visit: https://costscompared.com/best/test

Should redirect to Google and track the click.

---

## 🔄 Making Changes

1. Edit files locally in Cursor
2. Save changes
3. Commit: `git commit -am "Description"`
4. Push: `git push origin main`
5. GitHub Actions deploys automatically (2-5 minutes)
6. Verify changes on costscompared.com

---

## 🔒 Security Notes

- ✅ `config.php` excluded from Git (contains database password)
- ✅ FTP credentials stored as GitHub Secrets
- ✅ Security salts generated and configured
- ✅ Admin password set via reset script

---

**🚀 Link tracker successfully deployed to costscompared.com!**

*Last Updated: November 2025*

