<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/contact-functions.php';
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

$errors = validateContactInput($full_name, $phone, $message);

if (!empty($errors)) {
    header('Location: ' . $base_path . 'contact-form.php?error=' . urlencode($errors[0]));
    exit;
}

if (!in_array($preferred_contact_method, $allowed_methods, true)) {
    $preferred_contact_method = 'phone';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $base_path . "contact-form.php?error=" . urlencode('Email không hợp lệ.'));
    exit();
}

$created = createContact(
    $conn,
    $full_name,
    $phone,
    $email,
    $subject,
    $message,
    $preferred_contact_method
);

if ($created) {
    header('Location: ' . $base_path . 'contact-form.php?success=1');
    exit;
}

header('Location: ' . $base_path . 'contact-form.php?error=' . urlencode('Không thể gửi liên hệ.'));
exit;
$stmt->close();

header("Location: " . $base_path . "contact-form.php?success=1");
exit();