<?php
// Temporary setup script - DELETE THIS FILE AFTER USE!

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>ATV Rental - Setup Script</h2>";
echo "<pre>";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Current directory: " . __DIR__ . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

if (!file_exists(__DIR__ . '/.env')) {
    die("ERROR: .env file not found!\n\nPlease create .env file from env-production.txt first!");
}

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("ERROR: vendor/autoload.php not found!\n\nPlease run 'composer install' first.");
}

try {
    echo "=== Generating App Key ===\n";
    $kernel->call('key:generate', ['--force' => true]);
    echo "✓ App key generated\n\n";

    echo "=== Skipping Migrations (Database imported directly) ===\n";
    echo "✓ Migrations skipped\n\n";

    echo "=== Skipping Seeders (Database imported directly) ===\n";
    echo "✓ Seeders skipped\n\n";

    echo "=== Creating Storage Link ===\n";
    $target = __DIR__ . '/storage/app/public';
    $link = __DIR__ . '/public/storage';
    if (!file_exists($link)) {
        if (function_exists('symlink')) {
            symlink($target, $link);
            echo "✓ Storage link created\n\n";
        } else {
            echo "⚠ Warning: Could not create symlink. You may need to create it manually.\n\n";
        }
    } else {
        echo "✓ Storage link already exists\n\n";
    }

    echo "=== Caching Config ===\n";
    $kernel->call('config:cache');
    echo "✓ Config cached\n\n";

    echo "=== Caching Routes ===\n";
    $kernel->call('route:cache');
    echo "✓ Routes cached\n\n";

    echo "</pre>";
    echo "<h3 style='color: green;'>✅ Setup completed successfully!</h3>";
    echo "<h3 style='color: red;'>⚠️ DELETE THIS FILE NOW! (run-setup.php)</h3>";

} catch (Exception $e) {
    echo "</pre>";
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<pre>Stack trace:\n" . $e->getTraceAsString() . "</pre>";
}