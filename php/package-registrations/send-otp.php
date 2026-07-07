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
$email = trim((string) ($_POST['email'] ?? ''));

if ($package_id <= 0) {
    json_response(false, 'Vui lòng chọn gói tập trước.');
}

if (!preg_match('/^0\d{9,10}$/', $phone)) {
    json_response(false, 'Số điện thoại không hợp lệ.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Email nhận OTP không hợp lệ.');
}

$stmt = $conn->prepare("SELECT id FROM packages WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('i', $package_id);
$stmt->execute();
$package = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$package) {
    json_response(false, 'Gói tập không tồn tại hoặc đã ngừng hoạt động.');
}

$now = time();
$rate = $_SESSION['package_registration_otp_rate'] ?? [];
$rateWindowStart = (int) ($rate['window_start'] ?? 0);
$rateCount = (int) ($rate['count'] ?? 0);

if ($rateWindowStart <= 0 || ($now - $rateWindowStart) > 3600) {
    $rateWindowStart = $now;
    $rateCount = 0;
}

if ($rateCount >= 5) {
    json_response(false, 'Bạn đã yêu cầu OTP quá nhiều lần. Vui lòng thử lại sau 1 giờ.');
}

$otp = (string) random_int(1000, 9999);

require_once __DIR__ . '/../../includes/mailer.php';

try {
    sendPackageRegistrationOTP($email, $otp);
} catch (Throwable $e) {
    error_log('Package registration OTP mail failed: ' . $e->getMessage());
    json_response(false, 'Không gửi được OTP. Vui lòng kiểm tra cấu hình SMTP hoặc thử lại sau.');
}

$_SESSION['package_registration_otp'] = [
    'package_id' => $package_id,
    'phone' => $phone,
    'email' => mb_strtolower($email, 'UTF-8'),
    'code' => $otp,
    'expires_at' => $now + 300,
    'verified' => false,
    'attempts' => 0,
];

$_SESSION['package_registration_otp_rate'] = [
    'window_start' => $rateWindowStart,
    'count' => $rateCount + 1,
];

json_response(true, 'OTP đã được gửi đến email ' . $email . '. Mã có hiệu lực trong 5 phút.');
