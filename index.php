<?php
require_once __DIR__ . '/config/config.php';

// Redirect to login
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit();
