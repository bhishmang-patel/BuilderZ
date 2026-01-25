<?php
require_once __DIR__ . '/config/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Connected to MySQL at " . DB_HOST . "</p>";
    
    $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    if ($stmt->fetch()) {
        echo "<p>Database <strong>" . DB_NAME . "</strong> exists.</p>";
    } else {
        echo "<p style='color:red;'>Database <strong>" . DB_NAME . "</strong> does NOT exist. Please run <a href='config/install.php'>install.php</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Database Connection Failed</h1>";
    echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
}
