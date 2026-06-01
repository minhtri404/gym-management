<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

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

function callGeminiMealPlan($apiKey, $memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '', $model = 'gemini-2.5-flash')
{
    $goalLabel = getMealGoalLabel($goal);
    $bodyTypeLabel = getBodyTypeLabel($bodyType);

    $prompt = "
Bạn là chuyên gia dinh dưỡng cho phòng gym.

Hãy tạo kế hoạch ăn uống bằng tiếng Việt cho hội viên với thông tin sau:
- Họ tên: {$memberName}
- Mục tiêu: {$goalLabel}
- Thể trạng: {$bodyTypeLabel}
- Số bữa mỗi ngày: {$mealsPerDay}
- Lưu ý sức khỏe / ăn uống: {$healthNote}

Yêu cầu bắt buộc:
1. Chia rõ theo từng bữa trong ngày.
2. Mỗi bữa phải bắt đầu đúng định dạng: Bữa 1:, Bữa 2:, Bữa 3:...
3. Sau tiêu đề mỗi bữa, liệt kê món ăn bằng dấu gạch đầu dòng '-'.
4. Mỗi món nên ghi khẩu phần tương đối, ví dụ: 150g ức gà, 1 chén cơm, 1 quả chuối.
5. Có thể thêm 1 dòng ghi chú ngắn cho từng bữa nếu cần.
6. Nội dung thực tế, dễ mua, dễ áp dụng ở Việt Nam.
7. Phù hợp mục tiêu tăng cơ / giảm mỡ / giữ dáng của hội viên.
8. Nếu có lưu ý sức khỏe hoặc dị ứng thì phải điều chỉnh thực đơn theo lưu ý đó.
9. Không viết mở đầu dài dòng, không viết kết luận dài.
10. Không dùng bảng markdown.
11. Không dùng ký hiệu lạ, chỉ dùng văn bản thuần để hiển thị trên website.

Mẫu bắt buộc phải giống như sau:

Bữa 1: Bữa sáng
- Yến mạch: 50g
- Trứng luộc: 2 quả
- Chuối: 1 quả
- Ghi chú: Ưu tiên đạm và tinh bột hấp thu chậm

Bữa 2: Bữa phụ
- Sữa chua không đường: 1 hũ
- Hạnh nhân: 15g

Bữa 3: Bữa trưa
- Ức gà áp chảo: 150g
- Cơm gạo lứt: 1 chén
- Rau luộc: 1 đĩa

Chỉ trả về nội dung kế hoạch dinh dưỡng đúng format trên.
";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new Exception('Không gọi được Gemini API: ' . $curlError);
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = 'Gemini API lỗi';
        if (!empty($decoded['error']['message'])) {
            $message .= ': ' . $decoded['error']['message'];
        }
        $message .= ' (HTTP ' . $httpCode . ')';
        throw new Exception($message);
    }

    $text = trim($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($text === '') {
        throw new Exception('Gemini không trả về nội dung hợp lệ.');
    }

    return $text;
}

function callGeminiMealPlanWithRetry($apiKey, $memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '')
{
    $models = ['gemini-2.5-flash', 'gemini-2.5-pro'];
    $retryable = [429, 500, 502, 503, 504];
    $lastError = 'Unknown Gemini error';

    foreach ($models as $model) {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return callGeminiMealPlan(
                    $apiKey,
                    $memberName,
                    $goal,
                    $bodyType,
                    $mealsPerDay,
                    $healthNote,
                    $model
                );
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $httpCode = 0;
                if (preg_match('/HTTP\s+(\d{3})/', $lastError, $matches)) {
                    $httpCode = (int) $matches[1];
                }

                if ($httpCode !== 0 && !in_array($httpCode, $retryable, true)) {
                    throw $e;
                }

                if ($attempt < 2) {
                    sleep($attempt + 1);
                }
            }
        }
    }

    throw new Exception($lastError);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'meal-plans.php');
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

$member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
$goal = trim($_POST['goal'] ?? '');
$body_type = trim($_POST['body_type'] ?? '');
$meals_per_day = isset($_POST['meals_per_day']) ? (int) $_POST['meals_per_day'] : 0;
$health_note = trim($_POST['health_note'] ?? '');

if ($member_id <= 0 || $goal === '' || $body_type === '' || $meals_per_day <= 0) {
    header('Location: ' . $base_path . 'meal-plans.php?error=missing_fields');
    exit();
}

$stmtMember = $conn->prepare('SELECT id, full_name FROM members WHERE id = ? LIMIT 1');
$stmtMember->bind_param('i', $member_id);
$stmtMember->execute();
$resultMember = $stmtMember->get_result();
$member = $resultMember ? $resultMember->fetch_assoc() : null;
$stmtMember->close();

if (!$member) {
    header('Location: ' . $base_path . 'meal-plans.php?error=member_not_found');
    exit();
}

$ai_prompt = 'Mục tiêu: ' . getMealGoalLabel($goal)
    . ' | Thể trạng: ' . getBodyTypeLabel($body_type)
    . ' | Số bữa/ngày: ' . $meals_per_day
    . ' | Lưu ý ăn uống: ' . $health_note;

$ai_response = '';

try {
    if (!isset($gemini_api_key) || trim($gemini_api_key) === '') {
        header('Location: ' . $base_path . 'meal-plans.php?error=gemini_key_missing');
        exit();
    }

    $ai_response = callGeminiMealPlanWithRetry(
        $gemini_api_key,
        $member['full_name'],
        $goal,
        $body_type,
        $meals_per_day,
        $health_note
    );
} catch (Exception $e) {
    error_log('Gemini meal error: ' . $e->getMessage());
    $ai_response = "[Kế hoạch dự phòng]\n" . buildFallbackMealPlan(
        $member['full_name'],
        $goal,
        $body_type,
        $meals_per_day,
        $health_note
    );
}

$status = 'active';

$stmtInsert = $conn->prepare('
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
');
$stmtInsert->bind_param(
    'ississss',
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
    header('Location: ' . $base_path . 'meal-plans.php?error=save_failed');
    exit();
}

$stmtInsert->close();

header('Location: ' . $base_path . 'meal-plans.php?success=1&member_id=' . $member_id);
exit();
