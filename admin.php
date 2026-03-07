<?php
session_start();

// Configuration
require_once __DIR__ . '/config.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle Login
if (isset($_POST['password'])) {
    if (check_csrf($_POST['csrf_token'] ?? '')) {
        if ($_POST['password'] === $adminPassword) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Invalid password';
            sleep(1); // Brute-force delay
        }
    } else {
        $error = 'CSRF validation failed';
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Check Auth
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>&fix=2">
    </head>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh; flex-direction:column;">
        <nav style="position:fixed; top:0; width:100%;">
            <a href="index.php" class="nav-brand">./cliupload.com</a>
            <div class="nav-links">
                <a href="index.php">HOME</a>
                <a href="faq.php">FAQ</a>
                <a href="admin.php" class="active">ADMIN</a>
            </div>
        </nav>
        <div class="card">
            <h1>Admin Login</h1>
            <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="cli-input-group">
                    <label>Password</label>
                    <div class="cli-input-wrapper">
                        <span class="cli-prompt">&gt;</span>
                        <input type="password" name="password" placeholder="ADMIN_ACCESS" required>
                    </div>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle Deletion
if (isset($_POST['delete'])) {
    if (check_csrf($_POST['csrf_token'] ?? '')) {
        $id = $_POST['delete'];
        // Validate ID
        if (preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            if (file_exists($uploadDir . $id)) unlink($uploadDir . $id);
            if (file_exists($uploadDir . $id . '.json')) unlink($uploadDir . $id . '.json');
            $msg = "File $id deleted.";
        }
    } else {
        $msg = "CSRF Error: Modification failed.";
    }
}

// Handle Delete All
if (isset($_POST['delete_all'])) {
    if (check_csrf($_POST['csrf_token'] ?? '')) {
        $count = 0;
        foreach (glob($uploadDir . '*.json') as $metaFile) {
            $id = basename($metaFile, '.json');
            $dataFile = $uploadDir . $id;
            
            if (file_exists($dataFile)) unlink($dataFile);
            if (file_exists($metaFile)) unlink($metaFile);
            $count++;
        }
        $msg = "All files deleted ($count total).";
    } else {
        $msg = "CSRF Error: Operation blocked.";
    }
}

// List Files
$files = [];
foreach (glob($uploadDir . '*.json') as $metaFile) {
    $metadata = json_decode(file_get_contents($metaFile), true);
    if ($metadata) {
        $files[] = $metadata;
    }
}

// Sort by date desc
usort($files, function($a, $b) {
    return $b['uploaded_at'] - $a['uploaded_at'];
});

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>&fix=2">
    <style>
        .container { max-width: 1000px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; color: #c9d1d9; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #30363d; }
        th { color: #8b949e; font-weight: 600; }
        tr:hover { background: rgba(110,118,129,0.1); }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; }
        .badge-red { background: rgba(218,54,51,0.2); color: #f85149; }
        .badge-blue { background: rgba(56,139,253,0.15); color: #58a6ff; }
    </style>
</head>
<body style="display:block;">
    <!-- Navbar -->
    <nav>
        <a href="index.php" class="nav-brand">./cliupload.com</a>
        <div class="nav-links">
            <a href="index.php">HOME</a>
            <a href="faq.php">FAQ</a>
            <a href="admin.php" class="active">ADMIN</a>
        </div>
    </nav>
    <div class="container page-content" style="max-width: 1000px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Dashboard</h1>
            <div style="display:flex; gap:10px;">
                <form method="post" onsubmit="return confirm('WARNING: This will delete ALL files. Are you sure?');" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn" style="width:auto; padding: 0.5rem 1rem; background: var(--error);">Delete All</button>
                </form>
                <a href="?logout" class="btn" style="width:auto; padding: 0.5rem 1rem;">Logout</a>
            </div>
        </div>
        
        <?php if (isset($msg)) echo "<div class='result-card' style='margin-bottom:1rem; border-left-color:#2ea043;'>$msg</div>"; ?>
        
        <div class="card" style="padding: 1rem; overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $f): ?>
                    <tr>
                        <td>
                            <a href="download.php?id=<?php echo $f['id']; ?>" target="_blank" style="color:var(--accent-color); text-decoration:none;">
                                <?php echo $f['id']; ?>
                            </a>
                        </td>
                        <td title="<?php echo htmlspecialchars($f['original_name']); ?>">
                            <?php echo htmlspecialchars(strlen($f['original_name']) > 20 ? substr($f['original_name'],0,20).'...' : $f['original_name']); ?>
                        </td>
                        <td><?php echo formatBytes($f['size']); ?></td>
                        <td><?php echo date('M j, H:i', $f['uploaded_at']); ?></td>
                        <td>
                            <?php 
                            if ($f['expiration']) {
                                $timeLeft = $f['expiration'] - time();
                                if ($timeLeft < 0) echo '<span class="badge badge-red">Expired</span>';
                                else echo date('M j, H:i', $f['expiration']);
                            } else {
                                echo '<span class="text-muted">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (isset($f['password_hash']) && $f['password_hash']): ?>
                                <span class="badge badge-blue">Locked</span>
                            <?php else: ?>
                                <span class="text-muted">Open</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this file?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="delete" value="<?php echo $f['id']; ?>">
                                <button type="submit" style="background:none; border:none; color:#f85149; cursor:pointer;" title="Delete">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($files)): ?>
                <p style="padding: 2rem; color: #8b949e;">No files uploaded yet.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 2rem; text-align: center;">
            <p>Total Files: <?php echo count($files); ?></p>
        </div>
    </div>
    
    <div class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Secure Upload. All rights reserved.</p>
        <p>Developed by <a href="https://github.com/mrhussnain" target="_blank" style="color:var(--accent-color); text-decoration:none; font-weight:600;">mrhussnain</a></p>
        <p>System Status: <span style="color:var(--success)">ONLINE</span></p>
    </div>
</body>
</html>
