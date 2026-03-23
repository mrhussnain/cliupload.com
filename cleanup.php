<?php
/**
 * cleanup.php - Cleanup expired files
 * Run this via cron, e.g., * * * * * php /path/to/cleanup.php
 */

// Configuration
require_once __DIR__ . '/config.php';

$deleted = 0;
$errors = 0;

if (!is_dir($uploadDir)) {
    logError("Upload directory not found: $uploadDir");
    die("Upload directory not found.\n");
}

try {
    // Find all expired files in database
    $stmt = $db->query("
        SELECT id FROM files
        WHERE expiration IS NOT NULL
        AND expiration < NOW()
    ");

    $expiredFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($expiredFiles as $id) {
        try {
            // Delete file from filesystem
            $filePath = $uploadDir . $id;
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete from database
            $deleteStmt = $db->prepare("DELETE FROM files WHERE id = ?");
            $deleteStmt->execute([$id]);

            echo "Deleted expired file: $id\n";
            logActivity('cleanup_expired', $id, 'File expired and deleted by cron');
            $deleted++;

        } catch (Exception $e) {
            echo "Error deleting file $id: " . $e->getMessage() . "\n";
            logError("Cleanup failed for file", ['id' => $id, 'error' => $e->getMessage()]);
            $errors++;
        }
    }

    // Also clean up orphaned files (files in filesystem but not in database)
    $filesInDir = array_filter(scandir($uploadDir), function($file) {
        return !in_array($file, ['.', '..', '.htaccess']) && !str_ends_with($file, '.json');
    });

    foreach ($filesInDir as $file) {
        $checkStmt = $db->prepare("SELECT id FROM files WHERE id = ?");
        $checkStmt->execute([$file]);

        if (!$checkStmt->fetch()) {
            // Orphaned file
            $orphanedPath = $uploadDir . $file;
            if (unlink($orphanedPath)) {
                echo "Deleted orphaned file: $file\n";
                logActivity('cleanup_orphaned', $file, 'Orphaned file removed');
                $deleted++;
            }
        }
    }

    // Clean up old rate limit entries (older than 24 hours)
    $db->exec("
        DELETE FROM rate_limits
        WHERE first_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND (blocked_until IS NULL OR blocked_until < NOW())
    ");

    // Clean up old activity logs (older than 30 days, optional)
    $db->exec("
        DELETE FROM activity_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    logError("Cleanup database error", ['error' => $e->getMessage()]);
    die("Cleanup failed.\n");
}

if ($deleted > 0) {
    echo "Cleanup complete. Removed $deleted files";
    if ($errors > 0) {
        echo " with $errors errors";
    }
    echo ".\n";
} else {
    echo "No files to clean up.\n";
}
?>
