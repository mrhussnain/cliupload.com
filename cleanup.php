<?php
// cleanup.php - Run this via cron, e.g., * * * * * php /path/to/cleanup.php

$uploadDir = __DIR__ . '/uploads/';
$now = time();
$deleted = 0;

if (!is_dir($uploadDir)) {
    die("Upload directory not found.\n");
}

foreach (glob($uploadDir . '*.json') as $metaFile) {
    $metadata = json_decode(file_get_contents($metaFile), true);
    
    if (isset($metadata['expiration']) && !empty($metadata['expiration'])) {
        if ($now > $metadata['expiration']) {
            $id = $metadata['id'];
            $file = $uploadDir . $id;
            
            // Delete file
            if (file_exists($file)) unlink($file);
            // Delete metadata
            if (file_exists($metaFile)) unlink($metaFile);
            
            echo "Deleted expired file: $id\n";
            $deleted++;
        }
    }
}

if ($deleted > 0) {
    echo "Cleanup complete. Removed $deleted files.\n";
}
?>
