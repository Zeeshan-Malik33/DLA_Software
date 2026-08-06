<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard/index.php');
} else {
    header('Location: auth/login.php');
}
exit;
