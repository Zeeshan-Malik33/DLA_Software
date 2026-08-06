<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    // Every protected page lives one folder below the project root,
    // so ../auth/login.php reaches the login page consistently.
    header('Location: ../auth/login.php');
    exit;
}
