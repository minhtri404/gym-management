<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base_path . "contact-form.php");
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header("Location: " . $base_path . "contact-form.php?error=" . urlencode('CSRF token không hợp lệ.'));
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$preferred_contact_method = trim($_POST['preferred_contact_method'] ?? 'phone');

$allowed_methods = ['phone', 'zalo', 'email', 'facebook'];

if (
    $full_name === '' ||
    $phone === '' ||
    $subject === '' ||
    $message === ''
) {
    header("Location: " . $base_path . "contact-form.php?error=" . urlencode('Vui lòng nhập đầy đủ thông tin bắt buộc.'));
    exit();
}

if (!in_array($preferred_contact_method, $allowed_methods, true)) {
    $preferred_contact_method = 'phone';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $base_path . "contact-form.php?error=" . urlencode('Email không hợp lệ.'));
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO contact_messages (
        full_name,
        phone,
        email,
        subject,
        message,
        preferred_contact_method,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, 'new')
");

$stmt->bind_param(
    "ssssss",
    $full_name,
    $phone,
    $email,
    $subject,
    $message,
    $preferred_contact_method
);

$stmt->execute();
$stmt->close();

header("Location: " . $base_path . "contact-form.php?success=1");
exit();