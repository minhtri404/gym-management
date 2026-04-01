<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../';

function getMealGoalLabel($goal)
{
    switch ($goal) {
        case 'weight-loss':
            return 'Giảm cân';
        case 'muscle-gain':
            return 'Tăng cơ';
        case 'weight-gain':
            return 'Tăng cân';
        case 'maintain':
            return 'Giữ dáng';
        default:
            return 'Chưa xác định';
    }
}

function getBodyTypeLabel($bodyType)
{
    switch ($bodyType) {
        case 'thin':
            return 'Gầy';
        case 'normal':
            return 'Bình thường';
        case 'overweight':
            return 'Thừa cân';
        default:
            return 'Chưa xác định';
    }
}

function buildFallbackMealPlan($memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '')
{
    $goalLabel = getMealGoalLabel($goal);
    $bodyTypeLabel = getBodyTypeLabel($bodyType);

    $text = "Kế hoạch dinh dưỡng cho {$memberName}\n";
    $text .= "Mục tiêu: {$goalLabel}\n";
    $text .= "Thể trạng: {$bodyTypeLabel}\n";
    $text .= "Số bữa/ngày: {$mealsPerDay}\n\n";

    $mealNames = ['Bữa sáng', 'Bữa trưa', 'Bữa tối', 'Bữa phụ 1', 'Bữa phụ 2', 'Bữa nhẹ'];
    for ($i = 0; $i < $mealsPerDay; $i++) {
        $label = $mealNames[$i] ?? ('Bữa ' . ($i + 1));
        $text .= $label . ":\n";
        $text .= "- 1 nguồn đạm sạch\n";
        $text .= "- 1 phần tinh bột phù hợp\n";
        $text .= "- Rau xanh hoặc trái cây\n";
        $text .= "- Uống đủ nước\n\n";
    }

    if ($healthNote !== '') {
        $text .= "Lưu ý sức khỏe / ăn uống: {$healthNote}\n";
    }

    if ($goal === 'weight-loss') {
        $text .= "Ưu tiên giảm đồ ngọt, đồ chiên, nước có gas.\n";
    } elseif ($goal === 'muscle-gain') {
        $text .= "Ưu tiên tăng đạm, chia đều các bữa và ăn đủ sau tập.\n";
    } elseif ($goal === 'weight-gain') {
        $text .= "Ưu tiên tăng tổng năng lượng lành mạnh và thêm bữa phụ.\n";
    } else {
        $text .= "Ưu tiên ăn cân bằng, duy trì đều và ngủ nghỉ hợp lý.\n";
    }

    return trim($text);
}

function callGeminiMealPlan($apiKey, $memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '')
{
    $goalLabel = getMealGoalLabel($goal);
    $bodyTypeLabel = getBodyTypeLabel($bodyType);

    $prompt = "Hãy tạo kế hoạch dinh dưỡng bằng tiếng Việt cho hội viên tên {$memberName}. "
        . "Mục tiêu là {$goalLabel}. "
        . "Thể trạng {$bodyTypeLabel}. "
        . "Ăn {$mealsPerDay} bữa mỗi ngày. ";

    if ($healthNote !== '') {
        $prompt .= "Lưu ý sức khỏe / ăn uống: {$healthNote}. ";
    }

    $prompt .= "Hãy trả lời dạng văn bản dễ đọc, chia theo từng bữa trong ngày, có món gợi ý, lưu ý nên ăn gì và nên hạn chế gì. Không dùng markdown phức tạp.";

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
    header("Location: " . $base_path . "meal-plans.php");
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
$body_type = trim($_POST['body_type'] ?? '');
$meals_per_day = isset($_POST['meals_per_day']) ? (int)$_POST['meals_per_day'] : 0;
$health_note = trim($_POST['health_note'] ?? '');

if ($member_id <= 0 || $goal === '' || $body_type === '' || $meals_per_day <= 0) {
    header("Location: " . $base_path . "meal-plans.php?error=missing_fields");
    exit();
}

$stmtMember = $conn->prepare("SELECT id, full_name FROM members WHERE id = ? LIMIT 1");
$stmtMember->bind_param("i", $member_id);
$stmtMember->execute();
$resultMember = $stmtMember->get_result();
$member = $resultMember ? $resultMember->fetch_assoc() : null;
$stmtMember->close();

if (!$member) {
    header("Location: " . $base_path . "meal-plans.php?error=member_not_found");
    exit();
}

$ai_prompt = "Mục tiêu: " . getMealGoalLabel($goal)
    . " | Thể trạng: " . getBodyTypeLabel($body_type)
    . " | Số bữa/ngày: " . $meals_per_day
    . " | Lưu ý ăn uống: " . $health_note;

$ai_response = '';

try {
    if (!isset($gemini_api_key) || trim($gemini_api_key) === '') {
        header("Location: " . $base_path . "meal-plans.php?error=gemini_key_missing");
        exit();
    }

    $ai_response = callGeminiMealPlan(
        $gemini_api_key,
        $member['full_name'],
        $goal,
        $body_type,
        $meals_per_day,
        $health_note
    );
} catch (Exception $e) {
    $ai_response = buildFallbackMealPlan(
        $member['full_name'],
        $goal,
        $body_type,
        $meals_per_day,
        $health_note
    );
}

$status = 'active';

$stmtInsert = $conn->prepare("
    INSERT INTO ai_meal_plans (
        member_id,
        goal,
        body_type,
        meals_per_day,
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
    $body_type,
    $meals_per_day,
    $health_note,
    $ai_prompt,
    $ai_response,
    $status
);

if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    header("Location: " . $base_path . "meal-plans.php?error=save_failed");
    exit();
}

$stmtInsert->close();

header("Location: " . $base_path . "meal-plans.php?success=1&member_id=" . $member_id);
exit();