<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'auth/reset-password.php');
    exit;
}

if (empty($_SESSION['reset_user_id'])) {
    header('Location: ' . $base_path . 'forgot-password.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('CSRF token không hợp lệ.'));
    exit;
}

$user_id = (int)$_SESSION['reset_user_id'];
$otp = trim($_POST['otp'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($otp === '' || $new_password === '' || $confirm_password === '') {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('Vui lòng nhập đầy đủ thông tin.'));
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('OTP phải gồm 6 chữ số.'));
    exit;
}

if (strlen($new_password) < 6) {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('Mật khẩu phải từ 6 ký tự.'));
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('Xác nhận mật khẩu không khớp.'));
    exit;
}

// Kiểm tra OTP còn hạn, chưa dùng
$stmt = $conn->prepare("
    SELECT id
    FROM otp_codes
    WHERE user_id = ?
    AND otp_code = ?
    AND is_used = 0
    AND expires_at > NOW()
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $otp);
$stmt->execute();
$otpRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$otpRow) {
    header('Location: ' . $base_path . 'auth/reset-password.php?error=' . urlencode('OTP không hợp lệ hoặc đã hết hạn.'));
    exit;
}

// Cập nhật mật khẩu mới
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    UPDATE users
    SET password = ?
    WHERE id = ?
");
$stmt->bind_param("si", $password_hash, $user_id);
$stmt->execute();
$stmt->close();

// Đánh dấu OTP đã dùng
$stmt = $conn->prepare("
    UPDATE otp_codes
    SET is_used = 1
    WHERE id = ?
");
$stmt->bind_param("i", $otpRow['id']);
$stmt->execute();
$stmt->close();

// Xóa session reset mật khẩu
unset($_SESSION['reset_user_id'], $_SESSION['reset_user_email']);

header('Location: ' . $base_path . 'login.php?reset_success=1');
exit;