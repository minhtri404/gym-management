<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    apiError('Phuong thuc khong hop le. Hay dung POST.', 405);
}

apiRequireCsrf();

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$email = $email === '' ? null : $email;

if ($fullName === '' || $password === '' || $confirmPassword === '') {
    apiError('Vui long nhap day du thong tin bat buoc.', 422);
}

if ($email === null && $phone === '') {
    apiError('Vui long nhap email hoac so dien thoai.', 422);
}

if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('Email khong hop le.', 422);
}

if ($phone !== '' && !preg_match('/^0\d{9,10}$/', $phone)) {
    apiError('So dien thoai khong hop le.', 422);
}

if ($password !== $confirmPassword) {
    apiError('Mat khau xac nhan khong khop.', 422);
}

if (strlen($password) < 8) {
    apiError('Mat khau phai co it nhat 8 ky tu.', 422);
}

try {
    $stmt = $conn->prepare('
        SELECT id
        FROM users
        WHERE (? IS NOT NULL AND email = ?)
           OR (? <> "" AND phone = ?)
        LIMIT 1
    ');
    $stmt->bind_param('ssss', $email, $email, $phone, $phone);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        apiError('Email hoac so dien thoai da ton tai.', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'member';
    $status = 1;

    $stmt = $conn->prepare('
        INSERT INTO users (full_name, email, phone, password, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->bind_param('sssssi', $fullName, $email, $phone, $passwordHash, $role, $status);
    $stmt->execute();
    $newUserId = (int) $stmt->insert_id;
    $stmt->close();

    apiSuccess('Dang ky tai khoan thanh cong.', [
        'id' => $newUserId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
    ], 201);
} catch (Throwable $e) {
    apiServerError('Co loi xay ra khi dang ky tai khoan.', $e);
}
