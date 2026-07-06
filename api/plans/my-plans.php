<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/plan-functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

$user = apiRequireAuth($conn);
$userId = (int) $user['id'];
apiRejectForeignUserId($userId, 'GET');

try {
    $phone = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    $member = findMemberByUserContact($conn, $phone, $email);

    if (!$member) {
        apiSuccess('Tài khoản chưa liên kết với hồ sơ hội viên.', [
            'user' => $user,
            'member' => null,
            'workout_plans' => [],
            'meal_plans' => [],
        ]);
    }

    $memberId = (int) $member['id'];

    apiSuccess('Lấy kế hoạch của user thành công.', [
        'user' => $user,
        'member' => $member,
        'workout_plans' => getWorkoutPlansByMemberId($conn, $memberId),
        'meal_plans' => getMealPlansByMemberId($conn, $memberId),
    ]);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi lấy kế hoạch của user.', $e);
}
