# Security Improvements & Audit Report

## 🔒 Security Fixes Implemented

This document details all security vulnerabilities that were identified and fixed in CliUpload.

---

## ✅ Fixed Vulnerabilities

### 1. **CRITICAL: Weak Random ID Generation**

**Vulnerability**: File IDs were generated using `rand()` instead of cryptographically secure functions.

**Risk**: Predictable file IDs could allow attackers to enumerate and access private files.

**Fix Applied**:
```php
// Before (INSECURE):
$randomString .= $characters[rand(0, $charactersLength - 1)];

// After (SECURE):
$randomString .= $characters[random_int(0, $charactersLength - 1)];
```

**Location**: [config.php:258](config.php#L258)

---

### 2. **CRITICAL: Exposed Admin Credentials**

**Vulnerability**: Admin password hardcoded in `config.php` as plain text.

**Risk**: Repository compromise exposes admin credentials.

**Fix Applied**:
- Moved all sensitive configuration to `.env` file
- Added `.env` to `.gitignore`
- Password stored as hash in database
- Environment variable loader implemented

**Files**:
- [config.php](config.php) - Now loads from `.env`
- [.env.example](.env.example) - Template for configuration
- [.gitignore](.gitignore) - Protects `.env` and `config.php`

---

### 3. **HIGH: Race Condition in File Upload**

**Vulnerability**: Non-atomic ID uniqueness check could lead to file overwrites.

**Fix Applied**:
```php
// Added retry limit and database uniqueness check
$attempts = 0;
do {
    $id = generateId();
    $attempts++;
    if ($attempts > 10) {
        // Fail safely
        break;
    }
} while (file_exists($uploadDir . $id) || !checkIdUnique($id));
```

**Location**: [index.php:116-126](index.php#L116-L126)

---

### 4. **HIGH: No Rate Limiting**

**Vulnerability**: No protection against DoS or brute-force attacks.

**Fix Applied**:
- Implemented IP-based rate limiting with database tracking
- Configurable limits for uploads, downloads, and admin login
- Automatic blocking with configurable duration
- Rate limit tracking table with cleanup

**Features**:
- Upload rate limiting (default: 20/hour)
- Download rate limiting (default: 100/hour)
- Admin login attempts (default: 5 attempts before block)
- Automatic unblocking after configured duration

**Location**: [config.php:173-249](config.php#L173-L249)

---

### 5. **MEDIUM: Session Fixation Vulnerability**

**Vulnerability**: No `session_regenerate_id()` after successful login.

**Risk**: Attackers could hijack admin sessions.

**Fix Applied**:
```php
if ($admin && password_verify($_POST['password'], $admin['password_hash'])) {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    // ...
}
```

**Location**: [admin.php:32-33](admin.php#L32-L33)

---

### 6. **MEDIUM: Logout CSRF Vulnerability**

**Vulnerability**: Logout used GET request without CSRF protection.

**Risk**: Users could be forcefully logged out via malicious links.

**Fix Applied**:
- Changed logout from GET to POST method
- Added CSRF token validation
- Updated UI to use form submission

**Location**: [admin.php:61-67](admin.php#L61-L67)

---

### 7. **MEDIUM: Insufficient Error Handling**

**Vulnerability**: Generic error messages, no logging.

**Risk**: Difficult to debug issues, no audit trail.

**Fix Applied**:
- Comprehensive error logging to file
- Activity logging for all important actions
- Separate error and activity log tables
- Configurable logging via `.env`

**Functions**:
- `logError()` - Logs errors to file
- `logActivity()` - Logs to database
- Auto-cleanup of old logs (30 days)

**Location**: [config.php:132-170](config.php#L132-L170)

---

### 8. **MEDIUM: Metadata Exposure Risk**

**Vulnerability**: JSON metadata files stored in uploads directory.

**Risk**: If `.htaccess` fails, metadata could be exposed.

**Fix Applied**:
- Migrated all metadata to MySQL database
- Enhanced `.htaccess` protection
- `.json` files explicitly denied in `.htaccess`

**Location**: [uploads/.htaccess](uploads/.htaccess)

---

### 9. **LOW: Directory Index Protection**

**Issue**: `.htaccess` already present but verified.

**Status**: ✅ Already properly configured with:
```apache
Options -Indexes
Require all denied
```

**Location**: [uploads/.htaccess](uploads/.htaccess)

---

## 🆕 New Security Features

### 1. **Database-Backed Storage**

All file metadata now stored in MySQL instead of JSON:

**Benefits**:
- ACID compliance
- Better concurrency handling
- Easier querying and reporting
- No file permission issues
- Indexed lookups

**Schema**: [schema.sql](schema.sql)

---

### 2. **Comprehensive Activity Logging**

Tracks all security-relevant actions:

```sql
SELECT * FROM activity_logs
WHERE action IN (
    'admin_login_failed',
    'admin_login_success',
    'rate_limit_blocked',
    'invalid_download_attempt'
)
ORDER BY created_at DESC;
```

**Logged Actions**:
- File uploads/downloads
- Admin login attempts
- Rate limit blocks
- File deletions
- CSRF failures
- Invalid access attempts

---

### 3. **Enhanced CSRF Protection**

All state-changing operations now protected:

- ✅ Admin login
- ✅ Admin logout (POST only)
- ✅ File deletion
- ✅ Delete all files
- ✅ Token validation with `hash_equals()`

---

### 4. **Brute-Force Protection**

Multiple layers:

1. **Rate Limiting**: Max login attempts per hour
2. **Progressive Delay**: `sleep(2)` on failed login
3. **Automatic Blocking**: Temporary IP ban
4. **Activity Logging**: All attempts logged

---

### 5. **Input Validation Enhancement**

Strengthened validation:

```php
// ID validation (prevents traversal)
if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
    logActivity('invalid_download_attempt', null, "Invalid ID: $id");
    die('Invalid ID');
}
```

- Strict alphanumeric-only IDs
- Size limits enforced from config
- MIME type sanitization
- HTML escaping on all output

---

## 📊 Security Configuration Reference

### Environment Variables (`.env`)

```env
# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_UPLOADS_PER_HOUR=20
RATE_LIMIT_DOWNLOADS_PER_HOUR=100
RATE_LIMIT_ADMIN_LOGIN_ATTEMPTS=5
RATE_LIMIT_BLOCK_DURATION=1800

# Security
SESSION_LIFETIME=3600
CSRF_TOKEN_LIFETIME=3600
MAX_FILE_SIZE=2147483648
MAX_PREVIEW_SIZE=1048576

# Logging
ERROR_LOG_ENABLED=true
ACTIVITY_LOG_ENABLED=true

# Production
DEBUG_MODE=false
DISPLAY_ERRORS=false
```

---

## 🔍 Security Audit Checklist

Use this checklist to verify your deployment:

### Pre-Deployment
- [ ] `.env` file created from `.env.example`
- [ ] Strong admin password set (20+ characters)
- [ ] Database credentials secured
- [ ] `.env` and `config.php` in `.gitignore`
- [ ] Database schema imported
- [ ] File permissions set correctly (775 for dirs, 644 for files)

### Post-Deployment
- [ ] Admin login works
- [ ] Rate limiting functional
- [ ] Uploads work correctly
- [ ] Downloads work correctly
- [ ] Encryption feature works
- [ ] Password protection works
- [ ] Cleanup cron job running
- [ ] Logs are being written

### Production Hardening
- [ ] `DEBUG_MODE=false`
- [ ] `DISPLAY_ERRORS=false`
- [ ] HTTPS enforced
- [ ] Security headers configured
- [ ] Regular database backups scheduled
- [ ] Log rotation configured
- [ ] Monitoring alerts set up

---

## 🛡️ Defense in Depth

CliUpload implements multiple security layers:

| Layer | Protection |
|-------|------------|
| **Application** | Input validation, output encoding, CSRF tokens |
| **Session** | Secure session handling, regeneration, timeout |
| **Database** | Prepared statements, parameterized queries |
| **Rate Limiting** | IP-based throttling, auto-blocking |
| **File System** | Directory protection, permission hardening |
| **Encryption** | Client-side AES-GCM, password hashing (bcrypt) |
| **Logging** | Comprehensive audit trail |
| **Configuration** | Environment variables, secrets management |

---

## 📝 Responsible Disclosure

If you discover a security vulnerability in CliUpload:

1. **DO NOT** open a public GitHub issue
2. Email security details to the maintainer
3. Allow 90 days for fix before public disclosure
4. Provide detailed reproduction steps

---

## 🔐 Security Best Practices

### For Administrators

1. **Use Strong Passwords**
   - Minimum 20 characters
   - Mix of uppercase, lowercase, numbers, symbols
   - Use a password manager

2. **Enable HTTPS**
   - Use Let's Encrypt for free SSL
   - Force HTTPS in `.htaccess`
   - Enable HSTS header

3. **Regular Updates**
   - Keep PHP updated
   - Update MySQL/MariaDB
   - Monitor security advisories

4. **Monitor Logs**
   ```bash
   # Watch for suspicious activity
   tail -f logs/error.log

   # Check failed logins
   mysql -e "SELECT * FROM activity_logs WHERE action='admin_login_failed' ORDER BY created_at DESC LIMIT 20;" cliupload
   ```

5. **Backup Regularly**
   - Daily database dumps
   - Weekly full backups
   - Test restore procedures

### For Users

1. **Use Client-Side Encryption**
   - Enable for sensitive files
   - Key stays in your browser
   - Server never sees plaintext

2. **Set Expiration Times**
   - Use shortest time needed
   - Consider burn-after-reading

3. **Use Password Protection**
   - For sensitive files
   - Share password separately

---

## 📈 Security Metrics

Monitor these metrics for security health:

```sql
-- Failed login attempts today
SELECT COUNT(*) FROM activity_logs
WHERE action = 'admin_login_failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Currently blocked IPs
SELECT COUNT(*) FROM rate_limits
WHERE blocked_until > NOW();

-- Most active uploaders
SELECT ip_address, COUNT(*) as uploads
FROM files
WHERE uploaded_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
GROUP BY ip_address
ORDER BY uploads DESC
LIMIT 10;
```

---

## 🎯 Future Security Enhancements

Potential improvements for consideration:

- [ ] Two-factor authentication for admin
- [ ] File scanning integration (ClamAV)
- [ ] Geolocation-based blocking
- [ ] Advanced anomaly detection
- [ ] API rate limiting with API keys
- [ ] Content Security Policy headers
- [ ] Subresource Integrity (SRI)

---

**Last Updated**: 2026-03-23
**Security Contact**: [GitHub Issues](https://github.com/mrhussnain/cliupload/issues)
