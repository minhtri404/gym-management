<?php
session_start();

if (isset($_SESSION['user_id']) && (($_SESSION['user_role'] ?? '') === 'admin')) {
    header("Location: admin/dashboard.php");
    exit();
}

if (isset($_SESSION['user_id'])) {
    header("Location: user/home.php");
    exit();
}

header("Location: login.php");
exit();
?>
