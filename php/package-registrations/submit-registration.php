<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'user/packages.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    header('Location: ' . $base_path . 'user/packages.php');
    exit;
}

$package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$address = trim($_POST['address'] ?? '');
$note = trim($_POST['note'] ?? '');

if ($package_id <= 0 || $full_name === '' || $phone === '') {
    header('Location: ' . $base_path . 'user/package-register.php?package_id=' . $package_id . '&error=' . urlencode('Vui lòng nhập đầy đủ thông tin bắt buộc.'));
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base_path . 'user/package-register.php?package_id=' . $package_id . '&error=' . urlencode('Email không hợp lệ.'));
    exit;
}

$stmt = $conn->prepare("SELECT id FROM packages WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();
$package = $result->fetch_assoc();
$stmt->close();

if (!$package) {
    header('Location: ' . $base_path . 'user/packages.php');
    exit;
}

$status = 'new';

$stmt = $conn->prepare("
    INSERT INTO package_registrations (full_name, phone, email, date_of_birth, address, package_id, note, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssisss", $full_name, $phone, $email, $date_of_birth, $address, $package_id, $note, $status);
$stmt->execute();
$stmt->close();

header('Location: ' . $base_path . 'user/package-register.php?package_id=' . $package_id . '&success=1');
exit;
