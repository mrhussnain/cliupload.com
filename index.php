<?php
// Configuration
$uploadDir = __DIR__ . '/uploads/';
// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Function to generate a random ID
function generateId($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Check if it's a file upload (POST or PUT)
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' || $method === 'PUT') {
    // Disable timeout for large uploads
    set_time_limit(0);
    
    $response = [];
    $isCli = false;
    // Check if request is from curl/cli
    if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'curl') !== false || 
        strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Wget') !== false) {
        $isCli = true;
    }
    
    // Check if AJAX request expecting JSON
    $isAjax = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

    // Get optional metadata
    $password = null;
    $expiration = null; // timestamp
    $oneTimeView = false;

    // Function to calculate expiration
    function calculateExpiration($input) {
         if (!$input) return null;
         // Input format: "1h", "2d", "30m" or just seconds
         $multiplier = 1;
         $lastChar = strtolower(substr($input, -1));
         $val = intval($input);
         
         if ($lastChar === 'h') $multiplier = 3600;
         elseif ($lastChar === 'd') $multiplier = 86400;
         elseif ($lastChar === 'm') $multiplier = 60;
         elseif ($lastChar === 'w') $multiplier = 604800; // weeks
         
         return time() + ($val * $multiplier);
    }

    if ($method === 'PUT') {
        // Headers for CLI: X-Password, X-Expiration
        $headers = getallheaders(); // Note: getallheaders() might not work on all servers (nginx), but works on Apache/FPM usually.
        // Fallback for Nginx/FPM if needed:
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        
        // Normalize headers
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        if (isset($headers['x-password'])) $password = $headers['x-password'];
        if (isset($headers['x-expiration'])) {
            if ($headers['x-expiration'] === '1v') {
                $oneTimeView = true;
            } else {
                $expiration = calculateExpiration($headers['x-expiration']);
            }
        }

        // Default expiration for CLI: 10 minutes if not set
        if ($isCli && $expiration === null && !$oneTimeView) {
            $expiration = time() + 600;
        }

        $putData = fopen("php://input", "r");
        $tmpPath = tempnam(sys_get_temp_dir(), 'php_upload');
        $fp = fopen($tmpPath, "w");
        
        $size = 0;
        $maxSize = 2 * 1024 * 1024 * 1024; // 2GB
        
        while ($data = fread($putData, 8192)) {
            $size += strlen($data);
            if ($size > $maxSize) {
                $response = ['error' => 'File too large. Max 2GB.'];
                break;
            }
            fwrite($fp, $data);
        }
        
        fclose($fp);
        fclose($putData);
        
        if (!isset($response['error'])) {
            // Determine filename
            $requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            // Remove script name if present (e.g. index.php)
            $scriptName = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
            if ($scriptName && strpos($requestPath, $scriptName) === 0) {
                 $requestPath = substr($requestPath, strlen($scriptName));
                 $requestPath = trim($requestPath, '/');
            }
            
            $filename = basename($requestPath);
            if (empty($filename) || $filename === 'index.php') {
                $filename = 'upload.bin';
            }
            
            // Generate unique ID
            do {
                $id = generateId();
            } while (file_exists($uploadDir . $id));
            
            if (rename($tmpPath, $uploadDir . $id)) {
                 $metadata = [
                    'original_name' => $filename,
                    'mime_type' => 'application/octet-stream', // improved detection could be added here
                    'size' => $size,
                    'uploaded_at' => time(),
                    'id' => $id,
                    'password_hash' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
                    'expiration' => $expiration,
                    'one_time_view' => $oneTimeView
                ];
                file_put_contents($uploadDir . $id . '.json', json_encode($metadata));
                
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                    $protocol = 'https';
                }
                $host = $_SERVER['HTTP_HOST'];
                $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                 if ($scriptPath === '/') $scriptPath = '';
                 
                $downloadUrl = "$protocol://$host$scriptPath/download.php?id=$id";
                
                $response = [
                    'success' => true, 
                    'message' => 'File uploaded successfully',
                    'url' => $downloadUrl,
                    'id' => $id
                ];
            } else {
                 $response = ['error' => 'Failed to save file.'];
            }
        }
    } elseif ($method === 'POST') {
         if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            
            // Get post vars
            if (isset($_POST['password']) && !empty($_POST['password'])) {
                $password = $_POST['password'];
            }
            if (isset($_POST['expiration']) && !empty($_POST['expiration'])) {
                if ($_POST['expiration'] === '1v') {
                    $oneTimeView = true;
                } else {
                    $expiration = calculateExpiration($_POST['expiration']);
                }
            }

            // Default expiration for CLI: 10 minutes if not set
            if ($isCli && $expiration === null && !$oneTimeView) {
                $expiration = time() + 600;
            }
            
            // Basic validation
            $maxSize = 2 * 1024 * 1024 * 1024; // 2GB
            if ($file['size'] > $maxSize) {
                $response = ['error' => 'File too large. Max 2GB.'];
            } else {
                // Generate unique ID
                do {
                    $id = generateId();
                } while (file_exists($uploadDir . $id));
                
                // Move file
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $id)) {
                    // Store metadata
                    $metadata = [
                        'original_name' => basename($file['name']),
                        'mime_type' => $file['type'],
                        'size' => $file['size'],
                        'uploaded_at' => time(),
                        'id' => $id,
                        'password_hash' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
                        'expiration' => $expiration,
                        'is_encrypted' => isset($_POST['is_encrypted']) && $_POST['is_encrypted'] === '1',
                        'one_time_view' => $oneTimeView
                    ];
                    file_put_contents($uploadDir . $id . '.json', json_encode($metadata));
                    
                    // Construct URL configuration
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    // If running behind a proxy that handles SSL
                    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                        $protocol = 'https';
                    }
                    
                    $host = $_SERVER['HTTP_HOST'];
                    // Clean up any double slashes in path
                    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                    if ($scriptPath === '/') $scriptPath = '';
                     
                    $downloadUrl = "$protocol://$host$scriptPath/$id";
                    
                    $response = [
                        'success' => true, 
                        'message' => 'File uploaded successfully',
                        'url' => $downloadUrl,
                        'id' => $id
                    ];
                } else {
                    $response = ['error' => 'Failed to move uploaded file.'];
                }
            }
        } else {
            $response = ['error' => 'No file uploaded or upload error code: ' . ($_FILES['file']['error'] ?? 'unknown')];
        }
    }

    // Output for CLI immediately for PUT or if AJAX
    if ($isCli) {
         header('Content-Type: text/plain');
        if (isset($response['success']) && $response['success']) {
            echo "File uploaded successfully!\n";
            echo "Download URL: " . $response['url'] . "\n";
            
            if ($password) {
                 echo "Wget Command: wget --content-disposition --post-data=\"password=$password\" " . $response['url'] . "\n";
            } else {
                 echo "Wget Command: wget --content-disposition " . $response['url'] . "\n";
            }
        } else {
            echo "Error: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
        exit;
    }
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CliUpload - Secure & Anonymous File Sharing for CLI & Web</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Secure, anonymous, and free file sharing service. Upload files via command line (curl/wget) or web interface. Features self-destructing files, password protection, and privacy focus.">
    <meta name="keywords" content="cli upload, curl upload, bashupload, bashuploads, file sharing, secure upload, anonymous file transfer, command line upload, free file host">
    <meta name="author" content="CliUpload">
    <link rel="canonical" href="https://cliupload.com/">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://cliupload.com/">
    <meta property="og:title" content="CliUpload - Secure CLI & Web File Sharing">
    <meta property="og:description" content="Upload from your terminal with 'curl -T file cliupload.com'. Secure, anonymous, and easy.">
    <!-- <meta property="og:image" content="https://cliupload.com/og-image.png"> -->

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://cliupload.com/">
    <meta property="twitter:title" content="CliUpload - Secure CLI & Web File Sharing">
    <meta property="twitter:description" content="Upload from your terminal with 'curl -T file cliupload.com'. Secure, anonymous, and easy.">
    <!-- <meta property="twitter:image" content="https://cliupload.com/og-image.png"> -->

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>&fix=3">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav>
        <a href="index.php" class="nav-brand">./cliupload.com</a>
    </nav>
    <div class="container page-content">
        <div class="card">
            <h1>Secure Upload</h1>
            <p class="subtitle">Upload your files securely and anonymously.</p>
            
            <div id="resultContainer" style="display:none;">
                <div class="result-card" id="resultCard">
                    <p><strong>Success!</strong> File uploaded.</p>
                    <code id="resultUrl"></code>
                    <div id="qrcode" style="margin-top:15px; display:flex; justify-content:center;"></div>
                    <p style="margin-top:10px"><small>Scan to download on mobile</small></p>
                </div>
                <br>
                <button id="uploadAnotherBtn" class="btn" style="width: auto; margin-top: 1rem;">Upload Another</button>
            </div>

            <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="dropZone">
                    <span class="upload-icon">☁️</span>
                    <p>Drag & drop files here or click to browse</p>
                    <input type="file" name="file" id="fileInput" class="file-input" required>
                </div>
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progressBar"></div>
                    </div>
                    <div class="progress-text" id="progressText">0%</div>
                </div>

                <div class="options-grid">
                    <div class="cli-input-group">
                        <label>Password (Optional)</label>
                        <div class="cli-input-wrapper">
                            <span class="cli-prompt">&gt;</span>
                            <input type="password" name="password" placeholder="SET_PROTECTION" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="cli-input-group">
                        <label>Expiration</label>
                        <div class="cli-input-wrapper">
                            <span class="cli-prompt">&gt;</span>
                            <select name="expiration">

                                <option value="10m">10_MINUTES</option>
                                <option value="1h">1_HOUR</option>
                                <option value="1d">1_DAY</option>
                                <option value="1w">1_WEEK</option>
                                <option value="1v">1_VIEW</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem; text-align: left;">
                    <label style="display:flex; align-items:center; cursor:pointer;">
                        <input type="checkbox" id="encryptCheckbox" style="margin-right:10px;">
                        <span>🔒 Client-Side Encryption (Zero-Knowledge)</span>
                    </label>
                    <p style="font-size:0.8rem; color:#8b949e; margin-top:5px; margin-left: 24px;">File is encrypted in your browser before upload. Server cannot read it.</p>
                </div>

                <button type="submit" class="btn" id="submitBtn">Upload File</button>
            </form>
        </div>
        
        <footer class="cli-section">
            <p>Generate CLI Command:</p>
            <div class="cli-input-group" style="margin-bottom: 0;">
                <div class="cli-input-wrapper">
                    <span class="cli-prompt">$</span>
                    <input type="text" id="filenameGen" placeholder="filename.txt" style="text-align: left;">
                </div>
            </div>
            
            <div class="cli-input-group" style="margin-bottom: 0; margin-top: 10px;">
                <div class="cli-input-wrapper">
                    <span class="cli-prompt">🔑</span>
                    <input type="password" id="passwordGen" placeholder="password (optional)" style="text-align: left;">
                </div>
            </div>
            
            <div class="cmd-block" id="cmdBlock" data-baseurl="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>">
                <code>curl -T <span class="cmd-fname">filename.txt</span> <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/<span class="cmd-fname">filename.txt</span></code>
                <code>curl -F "file=@<span class="cmd-fname">filename.txt</span>" <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?></code>
                <!-- Password Protection Example -->
                <code>curl -F "file=@<span class="cmd-fname">filename.txt</span>" -F "password=SECRET" <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?></code>
            </div>
        </footer>

        <!-- FAQ Section (Merged) -->
        <div class="card" style="margin-top: 40px;">
            <h1>FAQ</h1>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Is my data secure?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        Yes. We use standard server-side security. For maximum privacy, enable <strong>Client-Side Encryption</strong> before uploading.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>What is the file size limit?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        The maximum file size is <strong>2GB</strong> per upload.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>How long do files last?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        You can set a custom expiration time or choose "1 View" (burn-after-reading).
                    </div>
                </div>

                <div class="faq-item">
                     <div class="faq-question">
                        <span>Can I upload from the command line?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        Yes! Use: <code>curl -T file.txt <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/file.txt</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Secure Upload. All rights reserved.</p>
        <p>System Status: <span style="color:var(--success)">ONLINE</span></p>
        <p>Website Powered by <a href="https://hosterocean.com" target="_blank" class="hoster-ocean-link">HosterOcean.CoM</a></p>
    </div>

    <script>
        // Command Generator Logic
        const filenameGen = document.getElementById('filenameGen');
        const passwordGen = document.getElementById('passwordGen');
        const cmdBlock = document.getElementById('cmdBlock');
        const baseUrl = cmdBlock ? cmdBlock.dataset.baseurl : '';
        
        function updateCommands() {
            if (!cmdBlock) return;
            const fname = filenameGen.value.trim() || 'filename.txt';
            const pass = passwordGen.value.trim();
            
            let html = '';
            
            if (pass) {
                 // Password Mode: Show curl -F with user's password
                 html += `<code>curl -F "file=@${fname}" -F "password=${pass}" ${baseUrl}</code>`;
                 // Maybe show download command too with generic password hint? 
                 // Actually uploading is the main thing here.
            } else {
                 // Standard Mode: Show all 3 examples (T, F, F+Pass)
                 html += `<code>curl -T ${fname} ${baseUrl}/${fname}</code> - Simple upload<br><br>`;
                 html += `<code>curl -F "file=@${fname}" ${baseUrl}</code> - Form upload<br><br>`;
                 html += `<code>curl -F "file=@${fname}" -F "password=SECRET" ${baseUrl}</code> - With password`;
            }
            
            cmdBlock.innerHTML = html;
        }
        
        if (filenameGen) {
            filenameGen.addEventListener('input', updateCommands);
        }
        if (passwordGen) {
            passwordGen.addEventListener('input', updateCommands);
        }

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const resultContainer = document.getElementById('resultContainer');
        const resultUrl = document.getElementById('resultUrl');
        const resultCard = document.getElementById('resultCard');
        const submitBtn = document.getElementById('submitBtn');
        const uploadAnotherBtn = document.getElementById('uploadAnotherBtn');
        const encryptCheckbox = document.getElementById('encryptCheckbox');

        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    document.querySelector('.upload-area p').textContent = e.dataTransfer.files[0].name;
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    document.querySelector('.upload-area p').textContent = fileInput.files[0].name;
                }
            });
        }

        async function encryptFile(file, key) {
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const alg = { name: 'AES-GCM', iv: iv };
            const keyObj = await window.crypto.subtle.importKey(
                'raw', key, alg, false, ['encrypt']
            );
            
            const fileData = await file.arrayBuffer();
            const encryptedData = await window.crypto.subtle.encrypt(alg, keyObj, fileData);
            
            // Prepend IV to encrypted data
            const blob = new Blob([iv, encryptedData], { type: 'application/octet-stream' });
            return blob;
        }

        function buf2hex(buffer) {
            return [...new Uint8Array(buffer)]
                .map(x => x.toString(16).padStart(2, '0'))
                .join('');
        }

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!fileInput.files.length) {
                alert('Please select a file first');
                return;
            }

            // UI updates
            submitBtn.disabled = true;
            submitBtn.textContent = 'Preparing...';
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';

            let fileToUpload = fileInput.files[0];
            let encryptionKey = null;
            let hexKey = null;

            if (encryptCheckbox.checked) {
                try {
                    // Generate random key
                    encryptionKey = window.crypto.getRandomValues(new Uint8Array(32)); // 256-bit key
                    hexKey = buf2hex(encryptionKey);
                    
                    submitBtn.textContent = 'Encrypting...';
                    fileToUpload = await encryptFile(fileToUpload, encryptionKey);
                    // Create a proper File object from the blob
                    fileToUpload = new File([fileToUpload], fileInput.files[0].name + '.enc', { type: 'application/octet-stream' });
                } catch (err) {
                    console.error(err);
                    alert('Encryption failed: ' + err.message);
                    resetForm();
                    return;
                }
            }

            const formData = new FormData(uploadForm);
            formData.set('file', fileToUpload);
            if (encryptCheckbox.checked) {
                formData.set('is_encrypted', '1');
            }
            
            submitBtn.textContent = 'Uploading...';

            const xhr = new XMLHttpRequest();

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.textContent = percentComplete + '%';
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Success UI
                            uploadForm.style.display = 'none';
                            resultContainer.style.display = 'block';
                            
                            let finalUrl = response.url;
                            if (hexKey) {
                                finalUrl += '#key=' + hexKey;
                            }
                            
                            resultUrl.textContent = finalUrl;
                            resultCard.style.borderLeftColor = '#2ea043';
                            resultCard.querySelector('p strong').textContent = 'Success!';
                            
                            // Generate QR Code
                            const qrContainer = document.getElementById('qrcode');
                            qrContainer.innerHTML = ''; // clear previous
                            new QRCode(qrContainer, {
                                text: finalUrl,
                                width: 128,
                                height: 128,
                                colorDark : "#000000",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });
                        } else {
                             // Error UI (revert to form but show error)
                             alert('Error: ' + response.error);
                             resetForm();
                        }
                    } catch (e) {
                         alert('Upload failed: ' + xhr.responseText);
                         resetForm();
                    }
                } else {
                    alert('Upload failed with status ' + xhr.status);
                    resetForm();
                }
            };

            xhr.onerror = () => {
                alert('Upload failed due to network error');
                resetForm();
            };

            xhr.open('POST', '', true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);
        });

        function resetForm() {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload File';
            progressContainer.style.display = 'none';
        }
        
        uploadAnotherBtn.addEventListener('click', () => {
             location.reload(); 
        });

        // FAQ Toggle Logic
        document.querySelectorAll('.faq-question').forEach(item => {
            item.addEventListener('click', () => {
                const parent = item.parentElement;
                parent.classList.toggle('active');
                const sign = item.querySelector('span:last-child');
                sign.textContent = parent.classList.contains('active') ? '-' : '+';
            });
        });
    </script>
</body>
</html>
