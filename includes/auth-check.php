<?php
include __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (preg_match('#^(.*?)/(admin|api|assets|css|includes|js|php|uploads|user)(/.*)?$#', $scriptName, $matches)) {
    $projectBasePath = rtrim($matches[1], '/') . '/';
} else {
    $projectBasePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $projectBasePath . 'login.php');
    exit;
}

if (strtolower(trim($_SESSION['user_role'] ?? '')) !== 'admin') {
    header('Location: ' . $projectBasePath . 'user/home.php');
    exit;
}
?>
