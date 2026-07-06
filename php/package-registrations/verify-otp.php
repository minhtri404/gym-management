<?php
include __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');

function json_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Phương thức không hợp lệ.');
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    json_response(false, 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
}

$package_id = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
$otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
$otpSession = $_SESSION['package_registration_otp'] ?? null;

if (!is_array($otpSession)) {
    json_response(false, 'Bạn chưa yêu cầu OTP hoặc phiên đã hết hạn.');
}

if ((int) ($otpSession['expires_at'] ?? 0) < time()) {
    unset($_SESSION['package_registration_otp']);
    json_response(false, 'OTP đã hết hạn. Vui lòng gửi lại mã mới.');
}

if ((int) ($otpSession['attempts'] ?? 0) >= 5) {
    unset($_SESSION['package_registration_otp']);
    json_response(false, 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng gửi lại mã mới.');
}

$matchesContext =
    (int) ($otpSession['package_id'] ?? 0) === $package_id &&
    (string) ($otpSession['phone'] ?? '') === $phone &&
    (string) ($otpSession['email'] ?? '') === $email;

if (!$matchesContext) {
    json_response(false, 'Thông tin xác thực không khớp. Vui lòng gửi lại OTP.');
}

if (!preg_match('/^\d{4}$/', $otp) || !hash_equals((string) ($otpSession['code'] ?? ''), $otp)) {
    $_SESSION['package_registration_otp']['attempts'] = ((int) ($otpSession['attempts'] ?? 0)) + 1;
    json_response(false, 'OTP không đúng. Vui lòng kiểm tra lại email.');
}

$_SESSION['package_registration_otp']['verified'] = true;
$_SESSION['package_registration_otp']['verified_at'] = time();

json_response(true, 'Xác thực OTP thành công.');
