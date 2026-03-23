<?php
/**
 * Migration Script: JSON to Database
 * Run this once to migrate existing JSON metadata to MySQL database
 *
 * Usage: php migrate.php
 */

require_once __DIR__ . '/config.php';

echo "========================================\n";
echo "CliUpload Migration Tool\n";
echo "========================================\n\n";

// Check if database connection exists
if (!isset($db) || !$db) {
    die("ERROR: Database connection not available. Please configure database in config.php\n");
}

// Check if tables exist
try {
    $db->query("SELECT 1 FROM files LIMIT 1");
} catch (PDOException $e) {
    die("ERROR: Database tables not found. Please run schema.sql first:\n" .
        "mysql -u your_user -p your_database < schema.sql\n");
}

$migrated = 0;
$skipped = 0;
$errors = 0;

echo "Scanning uploads directory: $uploadDir\n";
echo "----------------------------------------\n";

foreach (glob($uploadDir . '*.json') as $metaFile) {
    $id = basename($metaFile, '.json');
    $dataFile = $uploadDir . $id;

    // Check if actual file exists
    if (!file_exists($dataFile)) {
        echo "⚠ Skipping $id - data file missing\n";
        $skipped++;
        continue;
    }

    // Read metadata
    $metadata = json_decode(file_get_contents($metaFile), true);

    if (!$metadata) {
        echo "⚠ Skipping $id - invalid JSON\n";
        $skipped++;
        continue;
    }

    try {
        // Check if already migrated
        $stmt = $db->prepare("SELECT id FROM files WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->fetch()) {
            echo "⊘ Skipping $id - already in database\n";
            $skipped++;
            continue;
        }

        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO files (
                id, original_name, mime_type, size, uploaded_at,
                password_hash, expiration, is_encrypted, one_time_view
            ) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), ?, FROM_UNIXTIME(?), ?, ?)
        ");

        $stmt->execute([
            $id,
            $metadata['original_name'] ?? 'unknown',
            $metadata['mime_type'] ?? 'application/octet-stream',
            $metadata['size'] ?? filesize($dataFile),
            $metadata['uploaded_at'] ?? time(),
            $metadata['password_hash'] ?? null,
            $metadata['expiration'] ?? null,
            isset($metadata['is_encrypted']) ? (int)$metadata['is_encrypted'] : 0,
            isset($metadata['one_time_view']) ? (int)$metadata['one_time_view'] : 0
        ]);

        echo "✓ Migrated: $id ({$metadata['original_name']})\n";
        $migrated++;

        // Optionally backup the JSON file
        rename($metaFile, $metaFile . '.bak');

    } catch (PDOException $e) {
        echo "✗ Error migrating $id: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n========================================\n";
echo "Migration Complete\n";
echo "========================================\n";
echo "Migrated: $migrated files\n";
echo "Skipped:  $skipped files\n";
echo "Errors:   $errors files\n";
echo "\nJSON files have been renamed to .json.bak\n";
echo "You can safely delete them after verifying the migration.\n";
