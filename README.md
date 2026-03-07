# ☁️ CliUpload - Secure & Anonymous File Sharing

A modern, terminal-centric file sharing service. Focus on privacy, speed, and simplicity. Upload from your command line (`curl`) or the beautiful web interface with zero-knowledge encryption.

**🌐 Live Demo:** [cliupload.com](https://cliupload.com)

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-777bb4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](https://opensource.org/licenses/MIT)
[![Privacy](https://img.shields.io/badge/Privacy-Zero--Knowledge-blueviolet?style=flat-square)](https://cliupload.com)

---

## ✨ Features

- **🚀 Terminal-Native:** Upload files directly via `curl` or `wget`. No complex tools required.
- **🔒 Zero-Knowledge Encryption:** End-to-end client-side encryption (AES-GCM). The server never sees your unencrypted data.
- **🛡️ Privacy First:** No registration, no logs, anonymous uploads by default.
- **🔥 Self-Destructing Files:** Set expiration (10m, 1h, 1d, 1w) or choose **Burn-after-reading** (1-View mode).
- **🔑 Password Protection:** Secure any file with a password (server-side PBKDF2 hashing).
- **🎨 Beautiful Responsive UI:** Modern, dark-themed interface with QR codes for mobile downloads.
- **📝 Live Previews:** View text, code, and images directly in the browser with Prism.js syntax highlighting.
- **⚙️ Lightweight:** No database required (uses filesystem). Fast, clean, and easy to deploy.

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

CliUpload implements multiple layers of security:

1.  **Client-Side Encryption:** When enabled, files are encrypted using `AES-GCM-256` in your browser. The key is appended to the URL as a `#` fragment, meaning it is **never** sent to the server.
2.  **Server-Side Hashing:** Passwords for protected files are hashed using PHP's `password_hash()` and never stored in plaintext.
3.  **Automatic Cleanup:** A background cron job purges expired files, ensuring your data doesn't linger longer than intended.
4.  **Security Hardening:** The server forces `Content-Disposition: attachment` for **all** file types to prevent accidental execution/rendering in the browser.
5.  **Metadata Protection:** Direct web access to the `uploads/` directory is strictly forbidden via `.htaccess`.
6.  **CSRF Protection:** All administrative actions (deletion, logout) are protected by anti-CSRF tokens.
7.  **XSS Protection:** Strict sanitization of IDs and content-types to prevent traversal and injection attacks.

---

## 🛠️ Installation & Self-Hosting

### Prerequisites
- PHP 7.4+
- Nginx or Apache
- PHP-FPM

### Steps
1.  **Clone the Repo:**
    ```bash
    git clone https://github.com/mrhussnain/cliupload.git
    cd cliupload
    ```
2.  **Set Permissions:**
    Ensure the `uploads/` directory exists and is writable by the web server.
    ```bash
    mkdir uploads
    chmod 775 uploads
    chown -R www-data:www-data uploads
    ```
3.  **Configure Cron:**
    Add the cleanup script to your crontab to handle file expiration automatically.
    ```bash
    # Run every minute to check for expired files
    * * * * * php /var/www/cliupload.com/cleanup.php >> /var/www/cliupload.com/cleanup.log 2>&1
    ```
4.  **Admin Secret & Configuration:**
    Copy the example config and change the admin password:
    ```bash
    cp config.example.php config.php
    ```
    Then edit `config.php`:
    ```php
    $adminPassword = 'YOUR_NEW_PASSWORD';
    ```

### Server Configuration (Pretty URLs)
For clean links (`cliupload.com/abc123`), use the following rewrite rules:

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /download.php?id=$uri&$args;
}
```

**Apache (`.htaccess`):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9]+)$ download.php?id=$1 [L,QSA]
```

---

## 🛠️ Tech Stack

- **Backend:** PHP 7.4+ (Standard filesystem storage)
- **Frontend:** Vanilla CSS (Modern Dark Mode), JavaScript (Web Crypto API)
- **Libraries:** [QRCode.js](https://davidshimjs.github.io/qrcodejs/), [Prism.js](https://prismjs.com/)

---

## 📄 License

This project is licensed under the MIT License - see the `LICENSE` file for details.

---
*Developed with ❤️ by **[mrhussnain](https://github.com/mrhussnain)** for the CLI community. Powered by [HosterOcean.CoM](https://hosterocean.com).*
