<?php
/**
 * Complete Storage Setup Alternative (when symlink is not available)
 * 
 * Place this in: public_html/atvrental/public/
 * Visit: https://atvrental.muccs.site/setup-storage-alt.php
 */

echo "<h2>Alternative Storage Setup</h2>";
echo "<pre>";

$rootDir = dirname(__DIR__);
$publicStorageDir = __DIR__ . '/storage';

echo "Root: $rootDir\n";
echo "Public storage: $publicStorageDir\n\n";

// Step 1: Create public/storage directory
if (!is_dir($publicStorageDir)) {
    echo "Creating public/storage directory...\n";
    if (mkdir($publicStorageDir, 0755, true)) {
        echo "✅ Directory created.\n\n";
    } else {
        die("❌ Failed to create directory!\n");
    }
} else {
    echo "✅ Directory already exists.\n\n";
}

// Step 2: Copy the storage router
$routerFile = __DIR__ . '/storage-router.php';
$storageIndex = $publicStorageDir . '/index.php';

if (file_exists($routerFile)) {
    if (copy($routerFile, $storageIndex)) {
        echo "✅ Storage router installed.\n\n";
    } else {
        echo "⚠️ Failed to copy router. Please manually copy:\n";
        echo "   From: $routerFile\n";
        echo "   To: $storageIndex\n\n";
    }
} else {
    echo "⚠️ Router file not found. Creating manually...\n";
    $routerContent = <<<'PHP'
<?php
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/storage/?|^/?storage/?#', '', $path);
$path = ltrim($path, '/');

$rootDir = dirname(dirname(__DIR__));
$filePath = $rootDir . '/storage/app/public/' . $path;

$realFilePath = realpath($filePath);
$realStoragePath = realpath($rootDir . '/storage/app/public');
if (!$realFilePath || strpos($realFilePath, $realStoragePath) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

$mimeType = mime_content_type($filePath);
if (!$mimeType) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
PHP;
    if (file_put_contents($storageIndex, $routerContent)) {
        echo "✅ Storage router created.\n\n";
    } else {
        die("❌ Failed to create router file!\n");
    }
}

// Step 3: Create .htaccess for clean URLs
$htaccessContent = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /storage/
    
    # If file doesn't exist, route through index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>
HTACCESS;

$htaccessFile = $publicStorageDir . '/.htaccess';
if (file_put_contents($htaccessFile, $htaccessContent)) {
    echo "✅ .htaccess created.\n\n";
} else {
    echo "⚠️ Failed to create .htaccess (optional).\n\n";
}

// Step 4: Verify target storage exists
$targetStorage = $rootDir . '/storage/app/public';
if (is_dir($targetStorage)) {
    echo "✅ Target storage exists: $targetStorage\n";
} else {
    echo "⚠️ Creating target storage...\n";
    if (mkdir($targetStorage, 0755, true)) {
        echo "✅ Target storage created.\n\n";
    } else {
        echo "❌ Failed to create target storage!\n\n";
    }
}

echo "=== Setup Complete ===\n\n";
echo "Test your images:\n";
echo "https://atvrental.muccs.site/storage/atvs/[filename]\n";
echo "https://atvrental.muccs.site/storage/avatars/[filename]\n\n";

echo "✅ Images should now load correctly!\n";
echo "</pre>";
echo "<p><strong>⚠️ Delete setup-storage-alt.php and storage-router.php after use!</strong></p>";
?>
