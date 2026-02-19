<?php
require_once __DIR__ . '/../../includes/auth.php';

destroySession();
redirect('modules/auth/login.php');
