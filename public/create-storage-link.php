<?php
/**
 * Create Storage Link Script for Hostinger
 * This script creates the symlink from public/storage to storage/app/public
 * 
 * Place this file in: public_html/atvrental/public/
 * Then visit: https://atvrental.muccs.site/create-storage-link.php
 */

echo "<h2>Create Storage Link</h2>";
echo "<pre>";

$rootDir = __DIR__ . '/..';
$publicStoragePath = $rootDir . '/public/storage';
$targetStoragePath = $rootDir . '/storage/app/public';

echo "Root directory: $rootDir\n";
echo "Public storage link: $publicStoragePath\n";
echo "Target storage: $targetStoragePath\n\n";

// Check if target directory exists
if (!is_dir($targetStoragePath)) {
    echo "Creating target directory: $targetStoragePath\n";
    if (!mkdir($targetStoragePath, 0755, true)) {
        die("❌ Failed to create target directory!\n");
    }
    echo "✅ Target directory created.\n\n";
}

// Remove existing link/file if it exists
if (file_exists($publicStoragePath) || is_link($publicStoragePath)) {
    echo "Removing existing link/file...\n";
    if (is_link($publicStoragePath)) {
        unlink($publicStoragePath);
        echo "✅ Existing symlink removed.\n";
    } elseif (is_dir($publicStoragePath)) {
        rmdir($publicStoragePath);
        echo "✅ Existing directory removed.\n";
    }
}

// Create the symlink
echo "\nCreating symlink...\n";
if (function_exists('symlink')) {
    if (symlink($targetStoragePath, $publicStoragePath)) {
        echo "✅ Symlink created successfully!\n";
        echo "   Link: $publicStoragePath\n";
        echo "   Target: $targetStoragePath\n\n";
        
        // Verify the link
        if (is_link($publicStoragePath) && readlink($publicStoragePath) === $targetStoragePath) {
            echo "✅ Symlink verified!\n\n";
            echo "Test your images at:\n";
            echo "https://atvrental.muccs.site/storage/atvs/[filename]\n";
            echo "https://atvrental.muccs.site/storage/avatars/[filename]\n";
        } else {
            echo "⚠️ Warning: Symlink created but verification failed.\n";
        }
    } else {
        echo "❌ Failed to create symlink!\n";
        echo "Error: " . error_get_last()['message'] . "\n\n";
        echo "Alternative: You may need to create the symlink manually via SSH:\n";
        echo "cd " . escapeshellarg($rootDir) . "\n";
        echo "ln -s " . escapeshellarg($targetStoragePath) . " " . escapeshellarg($publicStoragePath) . "\n";
    }
} else {
    echo "❌ symlink() function is not available on this server.\n";
    echo "You need to create the symlink manually via SSH:\n";
    echo "cd " . escapeshellarg($rootDir) . "\n";
    echo "ln -s " . escapeshellarg($targetStoragePath) . " " . escapeshellarg($publicStoragePath) . "\n";
}

// Check permissions
echo "\n=== Directory Permissions ===\n";
echo "storage/app/public: " . (is_dir($targetStoragePath) ? substr(sprintf('%o', fileperms($targetStoragePath)), -4) : 'NOT FOUND') . "\n";
echo "public/storage: " . (file_exists($publicStoragePath) ? (is_link($publicStoragePath) ? 'SYMLINK' : substr(sprintf('%o', fileperms($publicStoragePath)), -4)) : 'NOT FOUND') . "\n";

echo "</pre>";
echo "<p><strong>⚠️ IMPORTANT: Delete this file after use for security!</strong></p>";
?>
