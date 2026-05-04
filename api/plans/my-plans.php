<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/plan-functions.php';
include __DIR__ . '/../includes/response.php';

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id <= 0) {
apiError('Thiếu hoặc sai user_id.', 400);
}

try {
    $user = getUserById($conn, $user_id);

    if (!$user) {
  apiError('Không tìm thấy user.', 404);    
    }

    $phone = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    $member = findMemberByUserContact($conn, $phone, $email);

    if (!$member) {
 apiSuccess('Không tìm thấy hội viên liên kết với user này.', [
    'user' => $user,
    'member' => null,
    'workout_plans' => [],
    'meal_plans' => [],
]);

        exit;
    }

    $member_id = (int) $member['id'];
    $workout_plans = getWorkoutPlansByMemberId($conn, $member_id);
    $meal_plans = getMealPlansByMemberId($conn, $member_id);

apiSuccess('Lấy kế hoạch của user thành công.', [
    'user' => $user,
    'member' => $member,
    'workout_plans' => $workout_plans,
    'meal_plans' => $meal_plans,
]);

} catch (Throwable $e) {
  apiError('Có lỗi xảy ra khi lấy kế hoạch của user.', 500, $e->getMessage());
}