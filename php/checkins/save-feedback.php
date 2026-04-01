<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base_path . "checkins.php");
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    die('CSRF token không hợp lệ.');
}

$checkin_id = isset($_POST['checkin_id']) ? (int)$_POST['checkin_id'] : 0;
$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
$feedback = trim($_POST['feedback'] ?? '');

$allowed_feedbacks = ['Tốt', 'Bình thường', 'Mệt', 'Nghỉ sớm'];

if ($checkin_id <= 0 || $member_id <= 0 || !in_array($feedback, $allowed_feedbacks, true)) {
    die('Dữ liệu đánh giá không hợp lệ.');
}

$stmt_checkin = $conn->prepare("
    SELECT id, member_id, checkout_time
    FROM checkins
    WHERE id = ?
    LIMIT 1
");
$stmt_checkin->bind_param("i", $checkin_id);
$stmt_checkin->execute();
$result_checkin = $stmt_checkin->get_result();
$checkin = $result_checkin ? $result_checkin->fetch_assoc() : null;
$stmt_checkin->close();

if (!$checkin) {
    die('Không tìm thấy buổi tập.');
}

if ((int)$checkin['member_id'] !== $member_id) {
    die('Dữ liệu hội viên không khớp.');
}

if (empty($checkin['checkout_time'])) {
    die('Buổi tập chưa check-out, chưa thể đánh giá.');
}

$stmt_existing = $conn->prepare("
    SELECT id
    FROM workout_feedbacks
    WHERE checkin_id = ?
    LIMIT 1
");
$stmt_existing->bind_param("i", $checkin_id);
$stmt_existing->execute();
$result_existing = $stmt_existing->get_result();
$existing = $result_existing ? $result_existing->fetch_assoc() : null;
$stmt_existing->close();

if ($existing) {
    header("Location: " . $base_path . "feedback.php?checkin_id=" . $checkin_id);
    exit();
}

$stmt_insert = $conn->prepare("
    INSERT INTO workout_feedbacks (
        checkin_id,
        member_id,
        feedback
    ) VALUES (?, ?, ?)
");
$stmt_insert->bind_param("iis", $checkin_id, $member_id, $feedback);
$stmt_insert->execute();
$stmt_insert->close();

header("Location: " . $base_path . "feedback.php?checkin_id=" . $checkin_id);
exit();