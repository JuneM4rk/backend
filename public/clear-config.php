<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    $kernel->call('config:clear');
    $kernel->call('cache:clear');
    echo "✅ Config and cache cleared!<br>";
    echo "Now test the API: <a href='/api/atvs'>/api/atvs</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}