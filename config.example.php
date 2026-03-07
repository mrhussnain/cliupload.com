<?php
// CliUpload - Example Configuration
// Copy this file to config.php and update the values.

// Admin Password for admin.php
$adminPassword = 'change_me_immediately'; 

// Upload Directory (absolute path recommended)
$uploadDir = __DIR__ . '/uploads/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
