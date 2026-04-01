<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../';

function getGoalLabel($goal)
{
    switch ($goal) {
        case 'weight-loss':
            return 'Giảm cân';
        case 'muscle-gain':
            return 'Tăng cơ';
        case 'maintain':
            return 'Giữ dáng';
        default:
            return 'Chưa xác định';
    }
}

function getLevelLabel($level)
{
    switch ($level) {
        case 'beginner':
            return 'Mới bắt đầu';
        case 'intermediate':
            return 'Trung bình';
        case 'advanced':
            return 'Nâng cao';
        default:
            return 'Chưa xác định';
    }
}

function buildFallbackPlan($memberName, $goal, $level, $daysPerWeek, $healthNote = '')
{
    $goalLabel = getGoalLabel($goal);
    $levelLabel = getLevelLabel($level);

    $text = "Kế hoạch tập luyện cho {$memberName}\n";
    $text .= "Mục tiêu: {$goalLabel}\n";
    $text .= "Trình độ: {$levelLabel}\n";
    $text .= "Số buổi/tuần: {$daysPerWeek}\n\n";

    for ($i = 1; $i <= $daysPerWeek; $i++) {
        $text .= "Buổi {$i}:\n";
        $text .= "- Khởi động 10 phút\n";
        $text .= "- 4 đến 5 bài tập chính\n";
        $text .= "- Cardio nhẹ 10 đến 15 phút\n";
        $text .= "- Giãn cơ cuối buổi\n\n";
    }

    if ($healthNote !== '') {
        $text .= "Lưu ý sức khỏe: {$healthNote}\n";
    }

    $text .= "Ưu tiên kỹ thuật đúng, tăng dần cường độ theo khả năng.";
    return trim($text);
}

function callGeminiWorkoutPlan($apiKey, $memberName, $goal, $level, $daysPerWeek, $healthNote = '')
{
    $goalLabel = getGoalLabel($goal);
    $levelLabel = getLevelLabel($level);

    $prompt = "Hãy tạo kế hoạch tập gym bằng tiếng Việt cho hội viên tên {$memberName}. "
        . "Mục tiêu là {$goalLabel}. "
        . "Trình độ {$levelLabel}. "
        . "Tập {$daysPerWeek} buổi mỗi tuần. ";

    if ($healthNote !== '') {
        $prompt .= "Lưu ý sức khỏe: {$healthNote}. ";
    }

    $prompt .= "Hãy trả lời dạng văn bản dễ đọc, chia theo từng buổi, mỗi buổi có nhóm cơ, bài tập, số set, số rep, và lưu ý an toàn. Không dùng markdown phức tạp.";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7
        ]
    ];

    $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new Exception('Không gọi được Gemini API.');
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Gemini API lỗi.');
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim($text);

    if ($text === '') {
        throw new Exception('Gemini không trả về nội dung hợp lệ.');
    }

    return $text;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base_path . "workout-plans.php");
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

$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
$goal = trim($_POST['goal'] ?? '');
$level = trim($_POST['level'] ?? '');
$days_per_week = isset($_POST['days_per_week']) ? (int)$_POST['days_per_week'] : 0;
$health_note = trim($_POST['health_note'] ?? '');

if ($member_id <= 0 || $goal === '' || $level === '' || $days_per_week <= 0) {
    header("Location: " . $base_path . "workout-plans.php?error=missing_fields");
    exit();
}

$stmtMember = $conn->prepare("SELECT id, full_name FROM members WHERE id = ? LIMIT 1");
$stmtMember->bind_param("i", $member_id);
$stmtMember->execute();
$resultMember = $stmtMember->get_result();
$member = $resultMember ? $resultMember->fetch_assoc() : null;
$stmtMember->close();

if (!$member) {
    header("Location: " . $base_path . "workout-plans.php?error=member_not_found");
    exit();
}

$ai_prompt = "Mục tiêu: " . getGoalLabel($goal)
    . " | Trình độ: " . getLevelLabel($level)
    . " | Số buổi/tuần: " . $days_per_week
    . " | Lưu ý sức khỏe: " . $health_note;

$ai_response = '';

try {
    if (!isset($gemini_api_key) || trim($gemini_api_key) === '') {
        header("Location: " . $base_path . "workout-plans.php?error=gemini_key_missing");
        exit();
    }

    $ai_response = callGeminiWorkoutPlan(
        $gemini_api_key,
        $member['full_name'],
        $goal,
        $level,
        $days_per_week,
        $health_note
    );
} catch (Exception $e) {
    $ai_response = buildFallbackPlan(
        $member['full_name'],
        $goal,
        $level,
        $days_per_week,
        $health_note
    );
}

$status = 'active';

$stmtInsert = $conn->prepare("
    INSERT INTO ai_workout_plans (
        member_id,
        goal,
        level,
        days_per_week,
        health_note,
        ai_prompt,
        ai_response,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmtInsert->bind_param(
    "ississss",
    $member_id,
    $goal,
    $level,
    $days_per_week,
    $health_note,
    $ai_prompt,
    $ai_response,
    $status
);

if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    header("Location: " . $base_path . "workout-plans.php?error=save_failed");
    exit();
}

$stmtInsert->close();

header("Location: " . $base_path . "workout-plans.php?success=1&member_id=" . $member_id);
exit();