<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'register.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('CSRF token không hợp lệ.'));
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('Vui lòng nhập đầy đủ thông tin bắt buộc.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('Email không hợp lệ.'));
    exit;
}

if (strlen($password) < 6) {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('Mật khẩu phải từ 6 ký tự trở lên.'));
    exit;
}

if ($password !== $confirm_password) {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('Xác nhận mật khẩu không khớp.'));
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$exists = $result->fetch_assoc();
$stmt->close();

if ($exists) {
    header('Location: ' . $base_path . 'register.php?error=' . urlencode('Email này đã tồn tại.'));
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'member';
$status = 1;

$stmt = $conn->prepare("
    INSERT INTO users (full_name, email, phone, password, role, status)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("sssssi", $full_name, $email, $phone, $password_hash, $role, $status);
$stmt->execute();
$stmt->close();

header('Location: ' . $base_path . 'register.php?success=1');
exit;