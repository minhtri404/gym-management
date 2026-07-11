<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

function finish_web_login(array $user, string $base_path): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? '';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if (strtolower(trim($_SESSION['user_role'] ?? '')) === 'admin') {
        unset($_SESSION['redirect_after_login'], $_SESSION['post_login_redirect']);
        header('Location: ' . $base_path . 'admin/dashboard.php');
        exit;
    }

    $redirect_after_login = trim($_SESSION['redirect_after_login'] ?? '');
    unset($_SESSION['redirect_after_login']);

    if ($redirect_after_login !== '' && !preg_match('#^(?:https?:)?//#i', $redirect_after_login)) {
        header('Location: ' . $redirect_after_login);
        exit;
    }

    $post_login_redirect = trim($_SESSION['post_login_redirect'] ?? '');
    unset($_SESSION['post_login_redirect']);

    if ($post_login_redirect !== '' && !preg_match('#^(?:https?:)?//#i', $post_login_redirect)) {
        header('Location: ' . $base_path . ltrim($post_login_redirect, '/'));
        exit;
    }

    header('Location: ' . $base_path . 'user/home.php');
    exit;
}

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

$login_identifier = trim($_POST['email_or_phone'] ?? ($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if ($login_identifier === '' || $password === '') {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Vui lòng nhập email hoặc số điện thoại và mật khẩu.'));
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, email, password, role, status
    FROM users
    WHERE email = ? OR phone = ?
    LIMIT 1
");
$stmt->bind_param("ss", $login_identifier, $login_identifier);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Email, số điện thoại hoặc mật khẩu không đúng.'));
    exit;
}

if ((int)$user['status'] !== 1) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Tài khoản đã bị khóa.'));
    exit;
}

if (!password_verify($password, $user['password'])) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Email, số điện thoại hoặc mật khẩu không đúng.'));
    exit;
}

if (trim((string)($user['email'] ?? '')) === '') {
    finish_web_login($user, $base_path);
}

// Create OTP and save it for verification when the account has an email.
$otp = random_int(100000, 999999);

$stmtOtp = $conn->prepare("
    INSERT INTO otp_codes (user_id, email, otp_code, expires_at, is_used)
    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
");
$stmtOtp->bind_param("iss", $user['id'], $user['email'], $otp);
$stmtOtp->execute();
$stmtOtp->close();

require_once __DIR__ . '/../../includes/mailer.php';
try {
    sendOTP($user['email'], $otp);
} catch (Exception $e) {
    error_log('OTP mail send failed: ' . $e->getMessage());
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Không gửi được mã OTP. Vui lòng kiểm tra cấu hình email SMTP.'));
    exit;
}

$_SESSION['otp_user_id'] = (int)$user['id'];
$_SESSION['otp_user_email'] = $user['email'];

header('Location: ' . $base_path . 'php/auth/verify-otp.php');
exit;
