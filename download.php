<?php
// Configuration
require_once __DIR__ . '/config.php';

// Get ID
$id = $_GET['id'] ?? '';

// Sanitize ID (alphanumeric only to prevent traversal)
if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
    die('Invalid ID');
}

$startFile = $uploadDir . $id;
$metaFile = $uploadDir . $id . '.json';

if (!file_exists($startFile) || !file_exists($metaFile)) {
    http_response_code(404);
    die('File not found');
}

// Read metadata
$metadata = json_decode(file_get_contents($metaFile), true);
$originalName = $metadata['original_name'] ?? 'download';
$mimeType = $metadata['mime_type'] ?? 'application/octet-stream';
$fileSize = $metadata['size'] ?? filesize($startFile);

// 1. Expiration Check
if (isset($metadata['expiration']) && !empty($metadata['expiration'])) {
    if (time() > $metadata['expiration']) {
        // Delete expired file
        unlink($startFile);
        unlink($metaFile);
        http_response_code(410); // Gone
        die('File has expired and been deleted.');
    }
}

// 2. Action Handling (Wait Page)
$action = $_GET['action'] ?? 'download';
$wait = isset($_GET['wait']);

// Detect CLI (curl/wget)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCli = (strpos($ua, 'curl') !== false || strpos($ua, 'Wget') !== false);

// Show Wait Page for Browser Downloads (unless already waited)
if (!$isCli && !$wait && $action === 'download') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Downloading...</title>
        <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
        <style>
            .ad-container {
                background: #000;
                border: 1px dashed var(--border);
                border-radius: 6px;
                height: 250px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 20px 0;
                color: var(--text-dim);
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            .countdown-circle {
                width: 80px;
                height: 80px;
                border: 4px solid var(--border);
                border-top-color: var(--success);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                font-weight: bold;
                margin: 0 auto 20px;
                animation: spin 1s linear infinite;
            }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .countdown-text { animation: none; transform: rotate(0deg); } /* fix for text rotating with border */
        </style>
    </head>
    <body style="display:flex; justify-content:center; align-items:center; min-height:100vh;">
        <div class="card" style="max-width: 500px; text-align: center; width: 100%;">
            <h1>Ready to Download</h1>
            <p class="subtitle"><?php echo htmlspecialchars($originalName); ?> (<?php echo round($fileSize/1024, 2); ?> KB)</p>
            
            <div class="ad-container">
                Advertisement Space
            </div>

            <div id="timerSection">
                <div class="countdown-circle" style="animation:none;">
                    <span id="countdown">5</span>
                </div>
                <p>Your download will start shortly...</p>
            </div>
            
            <p id="downloadingText" style="display:none; color:var(--success);">Starting Download...</p>
        </div>

        <script>
            let seconds = 5;
            const el = document.getElementById('countdown');
            const timer = setInterval(() => {
                seconds--;
                el.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    document.getElementById('timerSection').style.display = 'none';
                    document.getElementById('downloadingText').style.display = 'block';
                    
                    // Redirect to download with wait=1
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('wait', '1');
                    window.location.href = currentUrl.toString();
                }
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 3. Password Check
if (isset($metadata['password_hash']) && !empty($metadata['password_hash'])) {
    $authenticated = false;
    
    // Check if password was submitted
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], $metadata['password_hash'])) {
            $authenticated = true;
        } else {
            $error = 'Incorrect password';
        }
    }
    
    if (!$authenticated) {
        // Show Password Form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Required</title>
            <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h1>Protected File</h1>
                    <p class="subtitle">This file is password protected.</p>
                    
                    <?php if (isset($error)): ?>
                        <div class="result-card" style="border-left-color: #da3633; margin-bottom: 1rem;">
                            <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post">
                        <div class="cli-input-group">
                            <label>Password Required</label>
                            <div class="cli-input-wrapper">
                                <span class="cli-prompt">&gt;</span>
                                <input type="password" name="password" placeholder="UNLOCK_FILE" required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Unlock & Download</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}



if ($action === 'view') {
    // Preview Mode
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    
    // basic mapping for prism
    $langClass = 'language-plaintext';
    $codeMap = [
        'php' => 'language-php',
        'js' => 'language-javascript',
        'css' => 'language-css',
        'html' => 'language-html',
        'py' => 'language-python',
        'java' => 'language-java',
        'c' => 'language-c',
        'cpp' => 'language-cpp',
        'json' => 'language-json',
        'xml' => 'language-xml',
        'sql' => 'language-sql',
        'sh' => 'language-bash',
        'txt' => 'language-plaintext'
    ];
    
    if (isset($codeMap[strtolower($ext)])) {
        $langClass = $codeMap[strtolower($ext)];
    }
    
    // Read content safely
    // Check size first - don't preview huge files
    if ($fileSize > 1 * 1024 * 1024) { // 1MB limit for preview
        die('File too large to preview.');
    }
    
    $content = file_get_contents($startFile);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Preview: <?php echo htmlspecialchars($originalName); ?></title>
        <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-tomorrow.min.css" rel="stylesheet" />
        <style>
            .preview-container { text-align: left; }
            pre { border-radius: 8px; border: 1px solid var(--border-color); }
        </style>
    </head>
    <body style="display:block; padding-top:2rem;">
        <div class="container" style="max-width: 1000px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h1><?php echo htmlspecialchars($originalName); ?></h1>
                <a href="?id=<?php echo $id; ?>" class="btn" style="width:auto; text-decoration:none;">Download Raw</a>
            </div>
            
            <div class="card preview-container">
                <pre><code class="<?php echo $langClass; ?>"><?php echo htmlspecialchars($content); ?></code></pre>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-python.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-bash.min.js"></script>
    </body>
    </html>
    <?php
    // If 1-View, delete after preview
    if (isset($metadata['one_time_view']) && $metadata['one_time_view']) {
        unlink($startFile);
        unlink($metaFile);
    }
    exit;
}

// 4. Encryption Handling
$isEncrypted = $metadata['is_encrypted'] ?? false;
$raw = isset($_GET['raw']);

if ($isEncrypted && !$raw) {
    // Serve Decryption Page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Decrypting File...</title>
        <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    </head>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh;">
        <div class="card" style="max-width:500px;">
            <h1>Decrypting...</h1>
            <p class="subtitle" id="status">Fetching encrypted file...</p>
            <div class="progress-bar-bg" style="margin: 20px 0;">
                <div class="progress-bar-fill" id="progressBar" style="width:0%"></div>
            </div>
            <p id="error" style="color:#f85149; display:none;"></p>
        </div>

        <script>
            async function decryptAndDownload() {
                try {
                    // 1. Get Key from URL
                    const hash = window.location.hash;
                    if (!hash.includes('key=')) {
                        throw new Error('Decryption key missing from URL.');
                    }
                    const hexKey = hash.split('key=')[1];
                    const keyBytes = new Uint8Array(hexKey.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
                    
                    // 2. Fetch Encrypted Blob
                    document.getElementById('status').textContent = 'Downloading encrypted data...';
                    const response = await fetch('download.php?id=<?php echo $id; ?>&raw=1');
                    if (!response.ok) throw new Error('Failed to download file.');
                    
                    const total = response.headers.get('Content-Length');
                    const reader = response.body.getReader();
                    let receivedLength = 0;
                    let chunks = [];
                    
                    while(true) {
                        const {done, value} = await reader.read();
                        if (done) break;
                        chunks.push(value);
                        receivedLength += value.length;
                        if (total) {
                             document.getElementById('progressBar').style.width = Math.round((receivedLength / total) * 100) + '%';
                        }
                    }
                    
                    const encryptedBlob = new Blob(chunks);
                    const encryptedBuf = await encryptedBlob.arrayBuffer();
                    
                    // 3. Decrypt
                    if (encryptedBuf.byteLength < 28) { // 12 bytes IV + 16 bytes tag (min)
                         throw new Error('Download incomplete or file empty (' + encryptedBuf.byteLength + ' bytes).');
                    }

                    document.getElementById('status').textContent = 'Decrypting...';
                    
                    // Extract IV (first 12 bytes)
                    const iv = encryptedBuf.slice(0, 12);
                    const data = encryptedBuf.slice(12);
                    
                    const alg = { name: 'AES-GCM', iv: iv };
                    const keyObj = await window.crypto.subtle.importKey(
                        'raw', keyBytes, alg, false, ['decrypt']
                    );
                    
                    const decryptedBuf = await window.crypto.subtle.decrypt(alg, keyObj, data);
                    
                    // 4. Trigger Download
                    document.getElementById('status').textContent = 'Done! Saving...';
                    document.getElementById('progressBar').style.width = '100%';
                    
                    const decryptedBlob = new Blob([decryptedBuf]);
                    const url = URL.createObjectURL(decryptedBlob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = '<?php echo htmlspecialchars(str_replace(".enc", "", $originalName)); ?>';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                } catch (err) {
                    console.error(err);
                    document.getElementById('error').textContent = err.name + ': ' + err.message;
                    document.getElementById('error').style.display = 'block';
                    document.getElementById('status').textContent = 'Error';
                }
            }
            
            decryptAndDownload();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Security: Force download for potentially dangerous types to prevent XSS
$dangerousTypes = [
    'text/html', 'text/javascript', 'application/javascript', 
    'application/x-httpd-php', 'text/php'
];

// Determine disposition
$disposition = 'attachment'; // Always force download

// Override to attachment if dangerous
if (in_array($mimeType, $dangerousTypes)) {
    $disposition = 'attachment';
    $mimeType = 'application/octet-stream'; // Force browser to treat as binary
}

// Send headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . $originalName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Clean output buffer to ensure no extra whitespace corrupts the file
while (ob_get_level()) {
    ob_end_clean();
}

readfile($startFile);

// 1-View Expiration: Delete after serving
if (isset($metadata['one_time_view']) && $metadata['one_time_view']) {
    // Only delete if we actually served the file (which we just did)
    // For encrypted files, this happens when ?raw=1 is called.
    // For regular files, this happens on direct download.
    // For preview (action=view), we normally just show content. 
    // If preview is "1 view", should we delete? Yes.
    // Logic for preview was handled above with exit. Need to handle deletion there too.
    
    unlink($startFile);
    unlink($metaFile);
}

exit;
