# Changelog

All notable changes to CliUpload are documented in this file.

## [2.0.0] - 2026-03-23

### 🔒 Security Fixes

#### CRITICAL
- **Fixed weak random ID generation** - Replaced `rand()` with cryptographically secure `random_int()` ([config.php:252-262](config.php#L252-L262))
- **Removed hardcoded credentials** - Moved all sensitive configuration to environment variables ([config.php](config.php), [.env.example](.env.example))

#### HIGH
- **Fixed race condition in uploads** - Added retry logic and database uniqueness validation ([index.php:116-126](index.php#L116-L126))
- **Implemented rate limiting** - IP-based throttling for uploads, downloads, and admin login ([config.php:173-249](config.php#L173-L249))

#### MEDIUM
- **Fixed session fixation** - Added `session_regenerate_id()` on admin login ([admin.php:32-33](admin.php#L32-L33))
- **Fixed logout CSRF** - Changed logout from GET to POST with CSRF token ([admin.php:61-67](admin.php#L61-L67))
- **Enhanced error handling** - Comprehensive logging to files and database ([config.php:132-170](config.php#L132-L170))
- **Protected metadata** - Migrated from JSON files to MySQL database

---

### ✨ New Features

#### Database Integration
- **MySQL/MariaDB backend** - All metadata now stored in database instead of JSON files
- **Migration script** - Automated migration from JSON to database ([migrate.php](migrate.php))
- **Activity logging** - Comprehensive audit trail in `activity_logs` table
- **View counter** - Track download counts per file

#### Rate Limiting
- Configurable upload rate (default: 20/hour)
- Configurable download rate (default: 100/hour)
- Admin login attempt limiting (default: 5 attempts)
- Automatic IP blocking with configurable duration
- Database-backed rate limit tracking
- Auto-cleanup of old rate limit entries

#### Enhanced Admin Panel
- Database-backed admin credentials
- Username/password authentication
- Session regeneration on login
- CSRF-protected logout
- Activity monitoring
- View count display
- Better error messages

#### Logging & Monitoring
- Error logging to file (`logs/error.log`)
- Activity logging to database
- Automatic log rotation (30 day retention)
- Rate limit block logging
- Failed login attempt tracking

#### Configuration Management
- Environment variable support via `.env` file
- Centralized configuration
- Example configuration template
- Secure credential storage
- Per-environment settings

---

### 🔧 Improvements

#### Code Quality
- Removed duplicate `generateId()` function from index.php
- Added helper function `checkIdUnique()` for database validation
- Improved error handling with try-catch blocks
- Better code organization and comments
- PSR-compliant code style

#### Database Schema
- **files** table - Core file metadata storage
- **admin_settings** table - Admin credentials
- **rate_limits** table - Rate limiting data
- **activity_logs** table - Audit trail
- Proper indexes for performance
- Foreign key constraints

#### Cleanup Script
- Database-backed expiration checking
- Orphaned file detection and removal
- Rate limit cleanup (24 hour retention)
- Activity log cleanup (30 day retention)
- Better error reporting

#### Security Hardening
- Input validation enhancements
- Output encoding with `htmlspecialchars()`
- Prepared statements for all queries
- Password hashing with `password_hash()`
- Timing-safe comparisons with `hash_equals()`

---

### 📁 New Files

- `schema.sql` - Database schema definition
- `migrate.php` - Migration script from JSON to database
- `.env.example` - Environment configuration template
- `SETUP.md` - Comprehensive setup documentation
- `SECURITY.md` - Security audit report and best practices
- `CHANGELOG.md` - This file

---

### 🔄 Modified Files

#### config.php
- Complete rewrite with environment variable support
- Database connection initialization
- Rate limiting functions
- Error and activity logging functions
- Cryptographically secure ID generation
- Helper functions

#### index.php
- Rate limiting integration
- Database storage for uploads
- Removed duplicate `generateId()` function
- Enhanced error handling
- Configuration-based size limits
- Activity logging on uploads

#### download.php
- Database metadata retrieval
- Rate limiting for downloads
- Enhanced expiration handling
- View counter increment
- Activity logging
- Better error handling

#### admin.php
- Database-backed authentication
- Session regeneration on login
- Username/password support
- POST-based logout with CSRF
- Database file listing
- Enhanced deletion with logging
- Rate limiting for login

#### cleanup.php
- Database-based expiration checking
- Orphaned file cleanup
- Rate limit table cleanup
- Activity log cleanup
- Better error handling

#### .gitignore
- Added `.env` protection
- Added logs directory
- Added database backups
- Added JSON backup files

---

### 📊 Database Schema

#### Tables Created

```sql
- admin_settings     -- Admin user credentials
- files              -- File metadata storage
- rate_limits        -- Rate limiting tracking
- activity_logs      -- Audit trail
```

#### Indexes Added

```sql
- idx_username       -- Fast admin lookups
- idx_expiration     -- Efficient cleanup queries
- idx_uploaded_at    -- Sorted file listings
- idx_one_time_view  -- Quick burn-after-reading checks
- idx_ip_action      -- Rate limit lookups
- idx_blocked_until  -- Blocked IP checks
- idx_file_id        -- Activity log queries
- idx_action         -- Activity filtering
- idx_created_at     -- Log chronology
```

---

### 🗑️ Deprecated

- JSON file-based metadata storage (replaced by database)
- Hardcoded admin password (moved to environment variables)
- GET-based logout (replaced with POST + CSRF)
- `rand()` for ID generation (replaced with `random_int()`)

---

### ⚠️ Breaking Changes

#### Migration Required

If upgrading from v1.x:

1. **Database Setup**: Must create database and import schema
2. **Environment Config**: Must create `.env` file from template
3. **Data Migration**: Run `migrate.php` to convert JSON to database
4. **Cron Update**: Update cron job path/command if changed
5. **Admin Password**: Must set new admin password in database

#### Configuration Changes

- `config.php` no longer contains hardcoded values
- All configuration moved to `.env` file
- Admin password now in database, not config file
- New configuration options for rate limiting and logging

#### File Structure

- Metadata no longer in `.json` files
- New `logs/` directory required
- `.env` file required
- Migration creates `.json.bak` backup files

---

### 📦 Dependencies

#### New Requirements

- **PDO MySQL Extension** - For database connectivity
- **MySQL 5.7+ or MariaDB 10.2+** - Database server
- **Writable logs directory** - For error and activity logging

#### Unchanged Requirements

- PHP 7.4+
- Web server (Apache/Nginx)
- `uploads/` directory writable
- Cron access

---

### 🐛 Bug Fixes

- Fixed potential file ID collision under high load
- Fixed missing error handling in file uploads
- Fixed directory traversal vulnerability in ID validation
- Fixed potential XSS in admin error messages
- Fixed cleanup script not handling errors gracefully
- Fixed session data not being cleared on logout

---

### 🔐 Security Improvements Summary

| Issue | Severity | Status |
|-------|----------|--------|
| Weak random ID generation | CRITICAL | ✅ Fixed |
| Exposed admin credentials | CRITICAL | ✅ Fixed |
| Race condition in uploads | HIGH | ✅ Fixed |
| No rate limiting | HIGH | ✅ Fixed |
| Session fixation | MEDIUM | ✅ Fixed |
| Logout CSRF | MEDIUM | ✅ Fixed |
| Insufficient error handling | MEDIUM | ✅ Fixed |
| Metadata exposure risk | MEDIUM | ✅ Fixed |
| No activity logging | LOW | ✅ Fixed |

---

### 📝 Migration Guide

See [SETUP.md](SETUP.md) for detailed migration instructions.

**Quick Migration Steps:**

```bash
# 1. Backup everything
tar -czf cliupload-backup.tar.gz .

# 2. Create database and user
mysql -u root -p < setup_database.sql

# 3. Import schema
mysql -u cliupload_user -p cliupload < schema.sql

# 4. Configure environment
cp .env.example .env
nano .env

# 5. Migrate existing files
php migrate.php

# 6. Test functionality
# - Upload a file
# - Download a file
# - Login to admin panel
```

---

### 🎯 Future Roadmap

Potential features for v2.1:

- [ ] Two-factor authentication
- [ ] File scanning integration
- [ ] API authentication with keys
- [ ] Advanced analytics dashboard
- [ ] Bulk operations in admin panel
- [ ] File versioning
- [ ] Bandwidth throttling
- [ ] Geographic restrictions

---

### 👥 Contributors

- **[@mrhussnain](https://github.com/mrhussnain)** - Original author and v2.0 security overhaul

---

### 📄 License

MIT License - See [LICENSE](LICENSE) file

---

**Note**: This is a major version update with breaking changes. Please review [SETUP.md](SETUP.md) and [SECURITY.md](SECURITY.md) before deploying to production.
