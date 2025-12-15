<?php
/**
 * Storage Router - Serves files from storage/app/public when symlink is not available
 * 
 * This file should be placed in: public_html/atvrental/public/storage/index.php
 * 
 * It will serve files from: storage/app/public/
 */

// Get the requested file path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /storage prefix
$path = preg_replace('#^/storage/?|^/?storage/?#', '', $path);
$path = ltrim($path, '/');

// Get the root directory (two levels up from public/storage)
$rootDir = dirname(dirname(__DIR__));
$filePath = $rootDir . '/storage/app/public/' . $path;

// Security: Prevent directory traversal
$realFilePath = realpath($filePath);
$realStoragePath = realpath($rootDir . '/storage/app/public');
if (!$realFilePath || strpos($realFilePath, $realStoragePath) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

// Check if file exists
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

// Get MIME type
$mimeType = mime_content_type($filePath);
if (!$mimeType) {
    // Fallback MIME types
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
}

// Set headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Output file
readfile($filePath);
exit;
