<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'forgot-password.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('CSRF token không hợp lệ.'));
    exit;
}

$email = trim($_POST['email'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($email === '' || $new_password === '' || $confirm_password === '') {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Vui lòng nhập đầy đủ thông tin.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Email không hợp lệ.'));
    exit;
}

if (strlen($new_password) < 6) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Mật khẩu mới phải từ 6 ký tự trở lên.'));
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Xác nhận mật khẩu không khớp.'));
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Email không tồn tại trong hệ thống.'));
    exit;
}

$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $new_password_hash, $email);
$stmt->execute();
$stmt->close();

header('Location: ' . $base_path . 'login.php?success=1');
exit;