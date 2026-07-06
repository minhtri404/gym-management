<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

if ($scriptDir !== '' && str_starts_with($requestPath, $scriptDir . '/')) {
    $requestPath = substr($requestPath, strlen($scriptDir));
}

$route = trim($requestPath, '/');

$aliases = [
    '' => 'user/home.php',
    'home' => 'user/home.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'forgot-password' => 'forgot-password.php',
    'contact' => 'contact-form.php',
    'packages' => 'user/package/index.php',
    'dashboard' => 'user/dashboard/index.php',
    'admin' => 'admin/dashboard.php',
];

$target = $aliases[$route] ?? null;

if ($target === null && $route !== '') {
    $candidate = $route . '.php';

    if (is_file(__DIR__ . '/' . $candidate)) {
        $target = $candidate;
    }
}

if ($target === null || !is_file(__DIR__ . '/' . $target)) {
    http_response_code(404);
    echo '404 - Khong tim thay trang';
    exit;
}

$_SERVER['SCRIPT_NAME'] = ($scriptDir !== '' ? $scriptDir : '') . '/' . $target;
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

require __DIR__ . '/' . $target;
