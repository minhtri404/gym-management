<?php
include __DIR__ . '/../../includes/config.php';

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
$full_name = trim((string) ($_POST['full_name'] ?? ''));
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$date_of_birth = trim((string) ($_POST['date_of_birth'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
$payment_method = trim((string) ($_POST['payment_method'] ?? 'cash'));

if ($package_id <= 0 || $phone === '' || $email === '') {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Vui lòng nhập đầy đủ số điện thoại và email.'));
    exit;
}

if (!preg_match('/^0\d{9,10}$/', $phone)) {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Số điện thoại không hợp lệ.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Email không hợp lệ.'));
    exit;
}

$otpSession = $_SESSION['package_registration_otp'] ?? null;
$emailKey = mb_strtolower($email, 'UTF-8');
$otpIsValid =
    is_array($otpSession) &&
    !empty($otpSession['verified']) &&
    (int) ($otpSession['expires_at'] ?? 0) >= time() &&
    (int) ($otpSession['package_id'] ?? 0) === $package_id &&
    (string) ($otpSession['phone'] ?? '') === $phone &&
    (string) ($otpSession['email'] ?? '') === $emailKey;

if (!$otpIsValid) {
    header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Vui lòng xác thực OTP qua email trước khi hoàn tất đăng ký.'));
    exit;
}

if ($full_name === '') {
    $full_name = 'Khách hàng ' . $phone;
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

$allowed_payment_methods = ['cash', 'vnpay', 'free_trial'];
if (!in_array($payment_method, $allowed_payment_methods, true)) {
    $payment_method = 'cash';
}

$stmt = $conn->prepare('
    SELECT 
        id,
        package_type,
        duration_days,
        trial_once_per_user,
        price
    FROM packages
    WHERE id = ?
      AND status = "active"
    LIMIT 1
');
$stmt->bind_param('i', $package_id);
$stmt->execute();
$package = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$package) {
    header('Location: ' . $base_path . 'user/package/index.php');
    exit;
}
$is_free_trial = (($package['package_type'] ?? 'paid') === 'free_trial');
$trial_once = (int)($package['trial_once_per_user'] ?? 0) === 1;
$current_user_id = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($is_free_trial) {
    $payment_method = 'free_trial';

    if (empty($current_user_id)) {
        header('Location: ' . $base_path . 'login.php?error=' . urlencode('Vui lòng đăng nhập để nhận gói dùng thử 7 ngày.'));
        exit;
    }
}

if ($is_free_trial && $trial_once) {
    $stmt_check = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM package_registrations pr
        JOIN packages p ON p.id = pr.package_id
        WHERE pr.user_id = ?
          AND p.package_type = "free_trial"
          AND pr.status IN ("new", "approved", "closed")
    ');
    $stmt_check->bind_param('i', $current_user_id);
    $stmt_check->execute();
    $used_trial = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ((int)($used_trial['total'] ?? 0) > 0) {
        header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Bạn đã sử dụng gói dùng thử 7 ngày trước đó.'));
        exit;
    }

    $stmt_check_history = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM member_package_history mph
        JOIN packages p ON p.id = mph.package_id
        WHERE mph.member_id IN (
            SELECT id
            FROM members
            WHERE email = ?
               OR phone = ?
        )
          AND p.package_type = "free_trial"
          AND mph.status IN ("active", "expired", "cancelled")
    ');
    $stmt_check_history->bind_param('ss', $email, $phone);
    $stmt_check_history->execute();
    $used_history = $stmt_check_history->get_result()->fetch_assoc();
    $stmt_check_history->close();

    if ((int)($used_history['total'] ?? 0) > 0) {
        header('Location: ' . $base_path . 'user/package/register.php?package_id=' . $package_id . '&error=' . urlencode('Thông tin này đã từng sử dụng gói dùng thử 7 ngày.'));
        exit;
    }
}

// Tất cả kiểm tra đã qua, tiến hành lưu đăng ký
$status = 'new';

if ($is_free_trial) {
    $payment_status = 'paid';
} elseif ($payment_method === 'vnpay' && !empty($_SESSION['user_id'])) {
    $payment_status = 'pending';
} else {
    $payment_status = 'unpaid';
}

$stmt = $conn->prepare('
    INSERT INTO package_registrations (
        user_id,
        full_name,
        phone,
        email,
        date_of_birth,
        address,
        package_id,
        note,
        status,
        payment_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$stmt->bind_param(
    'isssssisss',
    $current_user_id,
    $full_name,
    $phone,
    $email,
    $date_of_birth,
    $address,
    $package_id,
    $note,
    $status,
    $payment_status
);
$stmt->execute();
$registration_id = (int) $stmt->insert_id;
$stmt->close();

unset($_SESSION['package_registration_otp']);
if (!$is_free_trial && $payment_method === 'vnpay') {
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