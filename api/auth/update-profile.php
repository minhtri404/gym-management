<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($user_id <= 0 || $full_name === '' || $email === '' || $phone === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('Email không hợp lệ.', 422);
}

try {
    $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt_check_user->bind_param("i", $user_id);
    $stmt_check_user->execute();
    $result_check_user = $stmt_check_user->get_result();
    $existing_user = $result_check_user->fetch_assoc();
    $stmt_check_user->close();

    if (!$existing_user) {
        apiError('Không tìm thấy user.', 404);
    }

    $stmt_check_duplicate = $conn->prepare("
        SELECT id 
        FROM users 
        WHERE (email = ? OR phone = ?) AND id <> ?
        LIMIT 1
    ");
    $stmt_check_duplicate->bind_param("ssi", $email, $phone, $user_id);
    $stmt_check_duplicate->execute();
    $result_check_duplicate = $stmt_check_duplicate->get_result();
    $duplicate = $result_check_duplicate->fetch_assoc();
    $stmt_check_duplicate->close();

    if ($duplicate) {
        apiError('Email hoặc số điện thoại đã được dùng bởi tài khoản khác.', 409);
    }

    $stmt_update = $conn->prepare("
        UPDATE users
        SET full_name = ?, email = ?, phone = ?
        WHERE id = ?
    ");
    $stmt_update->bind_param("sssi", $full_name, $email, $phone, $user_id);

    if (!$stmt_update->execute()) {
        throw new Exception('Không thể cập nhật hồ sơ.');
    }

    $stmt_update->close();

    $stmt_user = $conn->prepare("
        SELECT id, full_name, email, phone, role, status, avatar, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();
    $stmt_user->close();

    apiSuccess('Cập nhật hồ sơ thành công.', $user);

} catch (Throwable $e) {
    apiError('Có lỗi xảy ra khi cập nhật hồ sơ.', 500, $e->getMessage());
}