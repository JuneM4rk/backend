<?php
echo "<h2>Database Connection Test</h2>";
echo "<pre>";

$hosts = ['localhost', '127.0.0.1'];
$db = 'u779443399_atv_rental';
$user = 'u779443399_atv_rental';
$pass = 'Junemark#25'; // Use your actual current password

foreach ($hosts as $host) {
    echo "Testing host: $host\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ SUCCESS! Connected with host: $host\n";
        echo "Database: $db\n";
        echo "User: $user\n\n";
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Users table exists. Row count: " . $result['count'] . "\n";
        break;
    } catch (PDOException $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n\n";
    }
}

echo "</pre>";