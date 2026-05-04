<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

function getMealGoalLabel($goal)
{
    switch ($goal) {
        case 'weight-loss':
            return 'Gi?m cân';
        case 'muscle-gain':
            return 'Tang co';
        case 'weight-gain':
            return 'Tang cân';
        case 'maintain':
            return 'Gi? dáng';
        default:
            return 'Chua xác d?nh';
    }
}

function getBodyTypeLabel($bodyType)
{
    switch ($bodyType) {
        case 'thin':
            return 'G?y';
        case 'normal':
            return 'Bình thu?ng';
        case 'overweight':
            return 'Th?a cân';
        default:
            return 'Chua xác d?nh';
    }
}

function buildFallbackMealPlan($memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '')
{
    $goalLabel = getMealGoalLabel($goal);
    $bodyTypeLabel = getBodyTypeLabel($bodyType);

    $text = "K? ho?ch dinh du?ng cho {$memberName}\n";
    $text .= "M?c tiêu: {$goalLabel}\n";
    $text .= "Th? tr?ng: {$bodyTypeLabel}\n";
    $text .= "S? b?a/ngày: {$mealsPerDay}\n\n";

    $mealNames = ['B?a sáng', 'B?a trua', 'B?a t?i', 'B?a ph? 1', 'B?a ph? 2', 'B?a nh?'];
    for ($i = 0; $i < $mealsPerDay; $i++) {
        $label = $mealNames[$i] ?? ('B?a ' . ($i + 1));
        $text .= $label . ":\n";
        $text .= "- 1 ngu?n d?m s?ch\n";
        $text .= "- 1 ph?n tinh b?t phù h?p\n";
        $text .= "- Rau xanh ho?c trái cây\n";
        $text .= "- U?ng d? nu?c\n\n";
    }

    if ($healthNote !== '') {
        $text .= "Luu ý s?c kh?e / an u?ng: {$healthNote}\n";
    }

    if ($goal === 'weight-loss') {
        $text .= "Uu tiên gi?m d? ng?t, d? chiên, nu?c có gas.\n";
    } elseif ($goal === 'muscle-gain') {
        $text .= "Uu tiên tang d?m, chia d?u các b?a và an d? sau t?p.\n";
    } elseif ($goal === 'weight-gain') {
        $text .= "Uu tiên tang t?ng nang lu?ng lành m?nh và thêm b?a ph?.\n";
    } else {
        $text .= "Uu tiên an cân b?ng, duy trì d?u và ng? ngh? h?p lý.\n";
    }

    return trim($text);
}

function callGeminiMealPlan($apiKey, $memberName, $goal, $bodyType, $mealsPerDay, $healthNote = '', $model = 'gemini-2.5-flash')
{
    $goalLabel = getMealGoalLabel($goal);
    $bodyTypeLabel = getBodyTypeLabel($bodyType);

    $prompt = "
B?n là chuyên gia dinh du?ng cho phòng gym.

Hãy t?o k? ho?ch an u?ng b?ng ti?ng Vi?t cho h?i viên v?i thông tin sau:
- H? tên: {$memberName}
- M?c tiêu: {$goalLabel}
- Th? tr?ng: {$bodyTypeLabel}
- S? b?a m?i ngày: {$mealsPerDay}
- Luu ý s?c kh?e / an u?ng: {$healthNote}

Yêu c?u b?t bu?c:
1. Chia rõ theo t?ng b?a trong ngày.
2. M?i b?a ph?i b?t d?u dúng d?nh d?ng: B?a 1:, B?a 2:, B?a 3:...
3. Sau tiêu d? m?i b?a, li?t kê món an b?ng d?u g?ch d?u dòng '-'.
4. M?i món nên ghi kh?u ph?n tuong d?i, ví d?: 150g ?c gà, 1 chén com, 1 qu? chu?i.
5. Có th? thêm 1 dòng ghi chú ng?n cho t?ng b?a n?u c?n.
6. N?i dung th?c t?, d? mua, d? áp d?ng ? Vi?t Nam.
7. Phù h?p m?c tiêu tang co / gi?m m? / gi? dáng c?a h?i viên.
8. N?u có luu ý s?c kh?e ho?c d? ?ng thì ph?i di?u ch?nh th?c don theo luu ý dó.
9. Không vi?t m? d?u dài dòng, không vi?t k?t lu?n dài.
10. Không dùng b?ng markdown.
11. Không dùng ký hi?u l?, ch? dùng van b?n thu?n d? hi?n th? trên website.

M?u b?t bu?c ph?i gi?ng nhu sau:

B?a 1: B?a sáng
- Y?n m?ch: 50g
- Tr?ng lu?c: 2 qu?
- Chu?i: 1 qu?
- Ghi chú: Uu tiên d?m và tinh b?t h?p thu ch?m

B?a 2: B?a ph?
- S?a chua không du?ng: 1 hu
- H?nh nhân: 15g

B?a 3: B?a trua
- ?c gà áp ch?o: 150g
- Com g?o l?t: 1 chén
- Rau lu?c: 1 dia

Ch? tr? v? n?i dung k? ho?ch dinh du?ng dúng format trên.
";

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

    $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

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
        throw new Exception('Không g?i du?c Gemini API: ' . $curlError);
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = 'Gemini API l?i';
        if (!empty($decoded['error']['message'])) {
            $message .= ': ' . $decoded['error']['message'];
        }
        $message .= ' (HTTP ' . $httpCode . ')';
        throw new Exception($message);
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim($text);

    if ($text === '') {
        throw new Exception('Gemini không tr? v? n?i dung h?p l?.');
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
    header("Location: " . $base_path . "meal-plans.php");
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !isset($_SESSION['csrf_token']) ||
    $csrf_token === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    die('CSRF token không h?p l?.');
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

$ai_prompt = "M?c tiêu: " . getMealGoalLabel($goal)
    . " | Th? tr?ng: " . getBodyTypeLabel($body_type)
    . " | S? b?a/ngày: " . $meals_per_day
    . " | Luu ý an u?ng: " . $health_note;

$ai_response = '';

try {
    if (!isset($gemini_api_key) || trim($gemini_api_key) === '') {
        header("Location: " . $base_path . "meal-plans.php?error=gemini_key_missing");
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
    $ai_response = "[K? ho?ch d? phòng]\n" . buildFallbackMealPlan(
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


