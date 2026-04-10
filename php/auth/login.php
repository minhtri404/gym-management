<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'login.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('CSRF token không hợp lệ.'));
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Vui lòng nhập email và mật khẩu.'));
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, email, password, role, status
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Email hoặc mật khẩu không đúng.'));
    exit;
}

if ((int)$user['status'] !== 1) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Tài khoản đã bị khóa.'));
    exit;
}

if (!password_verify($password, $user['password'])) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Email hoặc mật khẩu không đúng.'));
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

if ($user['role'] === 'admin') {
    header('Location: ' . $base_path . 'dashboard.php');
    exit;
}

header('Location: ' . $base_path . 'user/home.php');
exit;