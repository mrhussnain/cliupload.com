# ☁️ CliUpload - Secure & Anonymous File Sharing

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-777bb4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](https://opensource.org/licenses/MIT)
[![Privacy](https://img.shields.io/badge/Privacy-Zero--Knowledge-blueviolet?style=flat-square)](https://cliupload.com)
[![Security](https://img.shields.io/badge/Security-Hardened-success?style=flat-square)](SECURITY.md)
[![Version](https://img.shields.io/badge/Version-2.0-blue?style=flat-square)](CHANGELOG.md)

**A modern, terminal-centric file sharing service focused on privacy, speed, and simplicity.**

Upload from your command line (`curl`) or the beautiful web interface with optional zero-knowledge encryption.

**🌐 Live Demo:** [cliupload.com](https://cliupload.com)

[Features](#-features) • [Installation](#-installation--self-hosting) • [CLI Usage](#-cli-usage) • [Security](#-security-architecture) • [Documentation](#-documentation)

</div>

---

## ✨ Features

### 🚀 Terminal-Native Upload
- Upload files directly via `curl` or `wget` - no complex tools required
- RESTful API for programmatic access
- Optimized for CLI workflows and automation

### 🔒 Zero-Knowledge Encryption
- **Client-side AES-GCM-256 encryption** - Server never sees your unencrypted data
- Encryption key stored in URL fragment (never sent to server)
- Perfect for sharing sensitive documents

### 🛡️ Privacy First
- **No registration required** - Completely anonymous uploads
- **Minimal logging** - Only essential metadata for management
- IP addresses hashed for rate limiting

### 🔥 Self-Destructing Files
- Set custom expiration: **10 minutes**, **1 hour**, **1 day**, **1 week**
- **Burn-after-reading** mode (one-time view)
- Automatic cleanup of expired files

### 🔑 Password Protection
- Server-side Bcrypt password hashing
- Secure password verification
- Extra layer of security for confidential files

### 🎨 Beautiful UI
- Modern, dark-themed responsive interface
- QR codes for easy mobile downloads
- Live previews for text, code, and images
- Syntax highlighting with Prism.js

### ⚙️ Enterprise-Ready
- **Database-backed** (MySQL/MariaDB) for reliability and scalability
- **Rate limiting** - Prevent abuse with IP-based throttling
- **Activity logging** - Comprehensive audit trail
- **CSRF protection** - Secure admin panel
- **Session hardening** - Protection against attacks

---

## 💻 CLI Usage (The Core)

CliUpload is designed to be the fastest way to share files from your terminal.

### Simple Upload
```bash
# Upload a file and get a link
curl -T myfile.txt https://cliupload.com/myfile.txt
```

### Form Upload (Standard POST)
```bash
curl -F "file=@myfile.txt" https://cliupload.com/
```

### Upload with Protection
```bash
# Upload with a password
curl -F "file=@myfile.txt" -F "password=SECRET_PASS" https://cliupload.com/

# Upload with custom expiration (e.g. 1 hour)
curl -F "file=@myfile.txt" -F "expiration=1h" https://cliupload.com/
```

### Download with Wget
```bash
# Download a password-protected file
wget --content-disposition --post-data="password=SECRET_PASS" https://cliupload.com/download.php?id=Zg282q
```

---

## 🔒 Security Architecture

CliUpload implements **defense in depth** with multiple security layers:

### Client-Side Encryption
- **AES-GCM-256** encryption in browser before upload
- Encryption key never sent to server (stored in URL fragment)
- True zero-knowledge architecture

### Server-Side Security
1. **Cryptographically Secure IDs** - Uses `random_int()` for unpredictable file IDs
2. **Password Hashing** - Bcrypt hashing (never plaintext)
3. **Rate Limiting** - IP-based throttling prevents DoS attacks
4. **CSRF Protection** - Anti-CSRF tokens on all state-changing operations
5. **Session Security** - Session regeneration prevents fixation attacks
6. **Input Validation** - Strict sanitization prevents injection
7. **Forced Downloads** - `Content-Disposition: attachment` prevents script execution
8. **Database Security** - Prepared statements prevent SQL injection

### Additional Protections
- Automatic cleanup of expired files
- Activity logging for audit trails
- Metadata protection (direct access forbidden)
- Environment-based configuration (no hardcoded secrets)

**See [SECURITY.md](SECURITY.md) for detailed security audit report.**

---

## 🛠️ Installation & Self-Hosting

### Prerequisites
- **PHP 7.4+** with PDO MySQL extension
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Web Server** - Nginx or Apache
- **PHP-FPM** (recommended)
- **Cron** access for automated cleanup

### Quick Installation

#### 1. Clone Repository
```bash
git clone https://github.com/mrhussnain/cliupload.git
cd cliupload
```

#### 2. Create Database
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

#### 3. Import Schema
```bash
mysql -u cliupload_user -p cliupload < schema.sql
```

#### 4. Configure Environment
```bash
cp .env.example .env
nano .env
```

**Update these values:**
```env
DB_HOST=localhost
DB_NAME=cliupload
DB_USER=cliupload_user
DB_PASS=your_secure_password
ADMIN_PASSWORD_HASH=$2y$10$...  # Generate with: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
```

#### 5. Set Permissions
```bash
mkdir -p uploads logs
chmod 775 uploads logs
chown -R www-data:www-data uploads logs
chmod 600 .env
```

#### 6. Setup Cron
```bash
crontab -e
# Add: */5 * * * * php /var/www/cliupload/cleanup.php >> /var/www/cliupload/logs/cleanup.log 2>&1
```

#### 7. Configure Web Server

**Nginx:**
```nginx
server {
    server_name yourdomain.com;
    root /var/www/cliupload;
    index index.php;

    client_max_body_size 2048M;

    location ~ ^/([a-zA-Z0-9]+)$ {
        rewrite ^/([a-zA-Z0-9]+)$ /download.php?id=$1 last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

**Apache (.htaccess included):**
```bash
# Enable mod_rewrite
a2enmod rewrite
systemctl restart apache2
```

### Migrating from v1.x

If upgrading from JSON-based version:
```bash
php migrate.php
```

**For detailed installation instructions, see [SETUP.md](SETUP.md)**

---

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ / MariaDB 10.2+ |
| **Storage** | Filesystem + Database (hybrid) |
| **Frontend** | Vanilla CSS + JavaScript |
| **Encryption** | Web Crypto API (AES-GCM) |
| **Syntax Highlighting** | Prism.js |
| **QR Codes** | QRCode.js |

---

## 📚 Documentation

Comprehensive documentation is available:

- **[SETUP.md](SETUP.md)** - Detailed installation and configuration guide
- **[SECURITY.md](SECURITY.md)** - Security audit report and best practices
- **[CHANGELOG.md](CHANGELOG.md)** - Version history and changes
- **[DEPLOYMENT_COMPLETE.md](DEPLOYMENT_COMPLETE.md)** - Deployment verification checklist

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Add tests for new features
- Update documentation
- Ensure security best practices

---

## 🐛 Bug Reports & Feature Requests

- **Security Issues**: Please report privately (see [SECURITY.md](SECURITY.md))
- **Bugs & Features**: [Open an issue](https://github.com/mrhussnain/cliupload/issues)

---

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- **Prism.js** - Syntax highlighting
- **QRCode.js** - QR code generation
- **Web Crypto API** - Client-side encryption
- **Let's Encrypt** - Free SSL certificates

---

## 🌟 Star History

If you find this project useful, please consider giving it a star ⭐

---

## 📞 Support

- **Website**: [cliupload.com](https://cliupload.com)
- **GitHub**: [@mrhussnain](https://github.com/mrhussnain)
- **Issues**: [GitHub Issues](https://github.com/mrhussnain/cliupload/issues)

---

<div align="center">

**Built with ❤️ for the CLI community**

Powered by [HosterOcean.com](https://hosterocean.com)

</div>
