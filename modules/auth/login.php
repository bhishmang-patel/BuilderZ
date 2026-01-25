<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } else {
            $db = Database::getInstance();
            $stmt = $db->select('users', 'username = ? AND status = ?', [$username, 'active']);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                startSession($user);
                redirect('modules/dashboard/index.php');
            } else {
                $error = 'Invalid username or password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <img src="<?= BASE_URL ?>assets/images/app_icon.png" alt="<?= APP_NAME ?>" style="width: 32px; height: 32px;">
            </div>
            <h1><?= APP_NAME ?></h1>
            <p>Manage your projects with precision</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <span class="error-icon">❌</span>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" 
                           placeholder="Enter your username"
                           required autofocus 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <span class="input-icon">👤</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••"
                           required>
                    <span class="input-icon">🔒</span>
                </div>
            </div>
            
            <button type="submit" class="btn-login">Login to Dashboard</button>
        </form>
    </div>
</body>
</html>
