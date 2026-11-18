<?php
require __DIR__ . '/auth.php';

// Only log out on a POST with a valid CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf()) {
    $_SESSION = [];
    session_destroy();
}

header('Location: login.php');
exit;
