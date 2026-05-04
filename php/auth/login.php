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

// Create OTP and save it for verification
$otp = rand(100000, 999999);

$stmtOtp = $conn->prepare("
    INSERT INTO otp_codes (user_id, email, otp_code, expires_at, is_used)
    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
");
$stmtOtp->bind_param("iss", $user['id'], $user['email'], $otp);
$stmtOtp->execute();
$stmtOtp->close();

// Send OTP email
require_once __DIR__ . '/../../includes/mailer.php';
try {
    sendOTP($user['email'], $otp);
} catch (Exception $e) {
    error_log('OTP mail send failed: ' . $e->getMessage());
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Khong gui duoc ma OTP. Kiem tra cau hinh email SMTP.'));
    exit;
}

// store temporary session info for OTP verification
$_SESSION['otp_user_id'] = (int)$user['id'];
$_SESSION['otp_user_email'] = $user['email'];

// Redirect to OTP verification page
header('Location: ' . $base_path . 'php/auth/verify-otp.php');
exit;
