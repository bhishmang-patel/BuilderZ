<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function startSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['permissions'] = $user['permissions'] ?? '[]'; // JSON string
    $_SESSION['last_activity'] = time();
    
    logAudit('login', 'users', $user['id']);
}

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            destroySession();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function destroySession() {
    if (isset($_SESSION['user_id'])) {
        logAudit('logout', 'users', $_SESSION['user_id']);
    }
    
    session_unset();
    session_destroy();
}

function requireAuth() {
    if (!isLoggedIn()) {
        redirect('modules/auth/login.php');
    }
}
