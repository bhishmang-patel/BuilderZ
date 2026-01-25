<?php
require_once __DIR__ . '/config.php';

$installation_complete = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create database connection without database selection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);
        
        // Create tables
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($sql);
        
        // Create default admin user (if not exists)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute(['admin']);
        if (!$stmt->fetch()) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', $password, 'System Administrator', 'admin@builderz.local', 'admin', 'active']);
        }
        
        // Insert default settings (if not exists)
        $settings = [
            ['company_name', 'BuilderZ Construction'],
            ['financial_year', '2025-2026'],
            ['gst_number', ''],
            ['company_address', ''], // Updated keys to match schema
            ['company_phone', ''],
            ['company_email', ''],
            ['financial_year_start', 'April']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                              SELECT ?, ? FROM DUAL 
                              WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = ?)");
        foreach ($settings as $setting) {
            $stmt->execute([$setting[0], $setting[1], $setting[0]]);
        }
        
        $installation_complete = true;
        
    } catch (PDOException $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install <?= APP_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        .credentials {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .credentials strong {
            display: block;
            margin-bottom: 5px;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #721c24;
        }
        .login-link {
            display: inline-block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏗️ <?= APP_NAME ?> Installation</h1>
        <p class="subtitle">Real Estate Booking + Construction + Accounting ERP</p>
        
        <?php if ($installation_complete): ?>
            <div class="success">
                <strong>✅ Installation Successful!</strong>
                <p>The database has been created and initialized successfully.</p>
            </div>
            
            <div class="credentials">
                <strong>⚠️ Default Login Credentials:</strong>
                <p><strong>Username:</strong> admin</p>
                <p><strong>Password:</strong> admin123</p>
                <p style="margin-top: 10px; font-size: 13px;">⚠️ Please change the password immediately after first login!</p>
            </div>
            
            <a href="../modules/auth/login.php" class="btn">Go to Login Page</a>
            
        <?php elseif ($error_message): ?>
            <div class="error">
                <strong>❌ Installation Failed</strong>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn">Retry Installation</button>
            </form>
            
        <?php else: ?>
            <div class="info-box">
                <h3>📋 Before You Begin</h3>
                <p>This installer will:</p>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li>Create the database: <strong><?= DB_NAME ?></strong></li>
                    <li>Set up all required tables</li>
                    <li>Create a default admin user</li>
                    <li>Initialize system settings</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>⚙️ Requirements</h3>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li>PHP 7.4 or higher</li>
                    <li>MySQL 5.7 or higher</li>
                    <li>XAMPP/WAMP installed</li>
                    <li>MySQL service running</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn">Start Installation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
