<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../includes/response.php';

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id <= 0) {
    apiError('Thiếu hoặc sai user_id.', 400);
}

try {
    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, role, status, avatar, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        apiError('Không tìm thấy user.', 404);
    }

    apiSuccess('Lấy thông tin user thành công.', $user);

} catch (Throwable $e) {
    apiError('Có lỗi xảy ra khi lấy thông tin user.', 500, $e->getMessage());
}