<?php
include __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/recaptcha.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'user/package/index.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'user/package/index.php');
    exit;
}

$package_id = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$address = trim($_POST['address'] ?? '');
$note = trim($_POST['note'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'cash');
$recaptcha_token = trim($_POST['g-recaptcha-response'] ?? '');

if ($package_id <= 0 || $full_name === '' || $phone === '') {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Vui lòng nhập đầy đủ thông tin bắt buộc.'));
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Email không hợp lệ.'));
    exit;
}

$captchaResult = verify_recaptcha_response($recaptcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
if (!is_array($captchaResult) || empty($captchaResult['success'])) {
    $captchaMessage = is_array($captchaResult)
        ? ($captchaResult['message'] ?? 'Vui lòng xác thực CAPTCHA.')
        : 'Vui lòng xác thực CAPTCHA.';
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode($captchaMessage));
    exit;
}

if ($date_of_birth !== '') {
    $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
    $dob_errors = DateTime::getLastErrors();

    if ($dob_errors === false) {
        $dob_errors = ['warning_count' => 0, 'error_count' => 0];
    }

    $dob_is_valid = $dob instanceof DateTime
        && $dob->format('Y-m-d') === $date_of_birth
        && (int) ($dob_errors['warning_count'] ?? 0) === 0
        && (int) ($dob_errors['error_count'] ?? 0) === 0;

    if (!$dob_is_valid) {
        header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Ngày sinh không hợp lệ.'));
        exit;
    }
} else {
    $date_of_birth = null;
}

$allowed_payment_methods = ['cash', 'vnpay'];
if (!in_array($payment_method, $allowed_payment_methods, true)) {
    $payment_method = 'cash';
}

$stmt = $conn->prepare('SELECT id FROM packages WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $package_id);
$stmt->execute();
$result = $stmt->get_result();
$package = $result->fetch_assoc();
$stmt->close();

if (!$package) {
    header('Location: ' . $base_path . 'user/package/index.php');
    exit;
}

$status = 'new';
$payment_status = ($payment_method === 'vnpay' && !empty($_SESSION['user_id'])) ? 'pending' : 'unpaid';

$stmt = $conn->prepare('
    INSERT INTO package_registrations (full_name, phone, email, date_of_birth, address, package_id, note, status, payment_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->bind_param('sssssisss', $full_name, $phone, $email, $date_of_birth, $address, $package_id, $note, $status, $payment_status);
$stmt->execute();
$registration_id = (int) $stmt->insert_id;
$stmt->close();

if ($payment_method === 'vnpay') {
    if (empty($_SESSION['user_id'])) {
        $_SESSION['post_login_redirect'] = 'php/vnpay/create-payment.php?registration_id=' . $registration_id;
        header('Location: ' . $base_path . 'login.php?error=' . urlencode('Vui lòng đăng nhập để tiếp tục thanh toán qua VNPAY.'));
        exit;
    }

    header('Location: ' . $base_path . 'php/vnpay/create-payment.php?registration_id=' . $registration_id);
    exit;
}

header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&success=1');
exit;
