# CliUpload - Setup Guide

This guide will help you set up and deploy the upgraded CliUpload with database integration and enhanced security features.

---

## 🚀 What's New

### Security Improvements
- ✅ **Cryptographically Secure ID Generation** - Uses `random_int()` instead of `rand()`
- ✅ **Environment Variable Configuration** - Sensitive data moved from code to `.env` file
- ✅ **Database Storage** - Metadata now stored in MySQL instead of JSON files
- ✅ **Session Regeneration** - Prevents session fixation attacks
- ✅ **CSRF Protection Enhanced** - Logout now uses POST with CSRF token
- ✅ **Rate Limiting** - Prevents abuse of uploads, downloads, and admin login
- ✅ **Comprehensive Logging** - Activity and error logs for monitoring
- ✅ **Password Hashing in DB** - Admin passwords stored securely

### New Features
- 📊 **Activity Logs** - Track all important actions
- 🔒 **Advanced Rate Limiting** - IP-based throttling with auto-blocking
- 📈 **View Counter** - Track download counts
- 🧹 **Orphaned File Cleanup** - Automatic removal of abandoned files
- 🗄️ **Database-Backed Admin** - Scalable admin management

---

## 📋 Prerequisites

- **PHP 7.4+** with PDO MySQL extension
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Web Server** - Apache or Nginx
- **PHP-FPM** (recommended)
- **Cron** access for cleanup tasks

---

## 🛠️ Installation Steps

### 1. Create Database

Login to MySQL and create the database:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE cliupload CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cliupload_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON cliupload.* TO 'cliupload_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Import Database Schema

```bash
mysql -u cliupload_user -p cliupload < schema.sql
```

### 3. Configure Environment

Copy the example configuration and edit it:

```bash
cp .env.example .env
nano .env
```

**Important Configuration Values:**

```env
# Database
DB_HOST=localhost
DB_NAME=cliupload
DB_USER=cliupload_user
DB_PASS=your_secure_password

# Admin (generate hash below)
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=$2y$10$...your_hash_here...

# Security
RATE_LIMIT_ENABLED=true
RATE_LIMIT_UPLOADS_PER_HOUR=20
RATE_LIMIT_DOWNLOADS_PER_HOUR=100
RATE_LIMIT_ADMIN_LOGIN_ATTEMPTS=5

# Production settings
DEBUG_MODE=false
DISPLAY_ERRORS=false
```

### 4. Generate Admin Password Hash

Run this command to generate a secure password hash:

```bash
php -r "echo password_hash('your_admin_password', PASSWORD_DEFAULT) . PHP_EOL;"
```

Copy the output and paste it into `.env` as `ADMIN_PASSWORD_HASH`.

### 5. Update Admin Password in Database

```bash
mysql -u cliupload_user -p cliupload
```

```sql
UPDATE admin_settings
SET password_hash = '$2y$10$your_generated_hash_here'
WHERE username = 'admin';
```

### 6. Migrate Existing Files (Optional)

If you have existing JSON-based uploads, run the migration:

```bash
php migrate.php
```

This will:
- Import all JSON metadata into the database
- Rename JSON files to `.json.bak`
- Preserve all file data

### 7. Set Permissions

```bash
# Ensure uploads directory is writable
chmod 775 uploads/
chown -R www-data:www-data uploads/

# Ensure logs directory exists
mkdir -p logs/
chmod 775 logs/
chown -R www-data:www-data logs/

# Protect sensitive files
chmod 600 .env
chmod 600 config.php
```

### 8. Configure Cron Job

Add the cleanup task to crontab:

```bash
crontab -e
```

Add this line:

```cron
# Run cleanup every 5 minutes
*/5 * * * * php /var/www/cliupload.com/cleanup.php >> /var/www/cliupload.com/logs/cleanup.log 2>&1
```

### 9. Verify Installation

Visit your site and check:

1. **Homepage**: `https://yourdomain.com/`
2. **Upload a test file**
3. **Admin Panel**: `https://yourdomain.com/admin.php`
   - Username: `admin`
   - Password: (your configured password)

---

## 🔒 Security Checklist

After installation, verify these security measures:

- [ ] `.env` file is **NOT** publicly accessible
- [ ] `.env` is in `.gitignore`
- [ ] `config.php` is in `.gitignore`
- [ ] Admin password is strong (20+ characters recommended)
- [ ] Admin password hash in database matches `.env`
- [ ] `uploads/.htaccess` denies direct access
- [ ] Rate limiting is enabled
- [ ] `DEBUG_MODE=false` in production
- [ ] `DISPLAY_ERRORS=false` in production
- [ ] Log files are writable by web server
- [ ] Database user has minimal required privileges

---

## 📊 Rate Limiting Configuration

Edit `.env` to adjust rate limits:

```env
# Uploads per hour from single IP
RATE_LIMIT_UPLOADS_PER_HOUR=20

# Downloads per hour from single IP
RATE_LIMIT_DOWNLOADS_PER_HOUR=100

# Admin login attempts before blocking
RATE_LIMIT_ADMIN_LOGIN_ATTEMPTS=5

# Block duration in seconds (1800 = 30 minutes)
RATE_LIMIT_BLOCK_DURATION=1800
```

---

## 🗄️ Database Management

### View Activity Logs

```sql
SELECT * FROM activity_logs
ORDER BY created_at DESC
LIMIT 100;
```

### View Rate Limits

```sql
SELECT ip_address, action, attempt_count, blocked_until
FROM rate_limits
WHERE blocked_until > NOW();
```

### Clear Rate Limits (Unblock All)

```sql
DELETE FROM rate_limits;
```

### View File Statistics

```sql
SELECT
    COUNT(*) as total_files,
    SUM(size) as total_size,
    AVG(size) as avg_size,
    SUM(view_count) as total_downloads
FROM files;
```

---

## 🔧 Troubleshooting

### "Database connection failed"

1. Verify MySQL is running: `systemctl status mysql`
2. Check `.env` credentials
3. Ensure database exists: `SHOW DATABASES;`
4. Check user privileges: `SHOW GRANTS FOR 'cliupload_user'@'localhost';`

### "Permission denied" on uploads

```bash
chmod 775 uploads/
chown -R www-data:www-data uploads/
```

### Admin login fails

1. Verify password hash in database:
   ```sql
   SELECT username, password_hash FROM admin_settings;
   ```
2. Regenerate password hash:
   ```bash
   php -r "echo password_hash('newpassword', PASSWORD_DEFAULT) . PHP_EOL;"
   ```
3. Update database:
   ```sql
   UPDATE admin_settings SET password_hash = '$2y$10$...' WHERE username = 'admin';
   ```

### Rate limiting too strict

Temporarily disable in `.env`:

```env
RATE_LIMIT_ENABLED=false
```

Or clear blocked IPs:

```sql
UPDATE rate_limits SET blocked_until = NULL;
```

---

## 🔄 Updating from Old Version

If you're upgrading from JSON-based version:

1. **Backup everything**:
   ```bash
   tar -czf cliupload-backup-$(date +%Y%m%d).tar.gz /var/www/cliupload.com/
   ```

2. **Follow installation steps 1-6**

3. **Run migration**:
   ```bash
   php migrate.php
   ```

4. **Verify files are accessible**

5. **Clean up old JSON files** (after verification):
   ```bash
   rm uploads/*.json.bak
   ```

---

## 📈 Monitoring

### Check Error Logs

```bash
tail -f logs/error.log
```

### Check Cleanup Logs

```bash
tail -f logs/cleanup.log
```

### Monitor Database Size

```bash
mysql -u cliupload_user -p -e "
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'cliupload'
ORDER BY (data_length + index_length) DESC;
"
```

---

## 🛡️ Hardening Tips

1. **Use HTTPS Only**
   ```apache
   # In .htaccess
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

2. **Add Security Headers**
   ```apache
   Header set X-Frame-Options "SAMEORIGIN"
   Header set X-Content-Type-Options "nosniff"
   Header set X-XSS-Protection "1; mode=block"
   Header set Referrer-Policy "strict-origin-when-cross-origin"
   ```

3. **Regular Backups**
   ```bash
   # Add to crontab
   0 2 * * * mysqldump -u cliupload_user -p'password' cliupload > /backups/cliupload-$(date +\%Y\%m\%d).sql
   ```

4. **Monitor Failed Login Attempts**
   ```sql
   SELECT ip_address, COUNT(*) as attempts
   FROM activity_logs
   WHERE action = 'admin_login_failed'
   AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
   GROUP BY ip_address
   HAVING attempts > 3;
   ```

---

## 📞 Support

For issues or questions:

- Check logs: `logs/error.log` and `logs/cleanup.log`
- Review database status
- Verify file permissions
- Check `.env` configuration

---

**Deployed with ❤️ by [mrhussnain](https://github.com/mrhussnain)**
