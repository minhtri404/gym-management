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

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Email không hợp lệ.'));
    exit;
}

$stmt = $conn->prepare("
    SELECT id, email, status
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Email không tồn tại trong hệ thống.'));
    exit;
}

if ((int)$user['status'] !== 1) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Tài khoản đã bị khóa.'));
    exit;
}

// Giới hạn tối đa 5 OTP trong 1 giờ để tránh spam
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM otp_codes
    WHERE user_id = ?
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ((int)$countRow['total'] >= 5) {
    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Bạn yêu cầu OTP quá nhiều lần. Vui lòng thử lại sau.'));
    exit;
}

$otp = (string) random_int(100000, 999999);

$stmt = $conn->prepare("
    INSERT INTO otp_codes 
    (user_id, email, otp_code, expires_at, is_used)
    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
");
$stmt->bind_param("iss", $user['id'], $user['email'], $otp);
$stmt->execute();
$stmt->close();

require_once __DIR__ . '/../../includes/mailer.php';

try {
    sendPasswordResetOTP($user['email'], $otp);
} catch (Exception $e) {
    error_log('Send reset OTP failed: ' . $e->getMessage());

    header('Location: ' . $base_path . 'forgot-password.php?error=' . urlencode('Không gửi được OTP. Vui lòng kiểm tra cấu hình SMTP.'));
    exit;
}

$_SESSION['reset_user_id'] = (int)$user['id'];
$_SESSION['reset_user_email'] = $user['email'];

header('Location: ' . $base_path . 'auth/reset-password.php?success=' . urlencode('OTP đã được gửi về email.'));
exit;