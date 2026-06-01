<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

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

function detectHealthAdjustments($healthNote)
{
    $note = mb_strtolower(trim((string) $healthNote), 'UTF-8');

    $adjustments = [
        'warmup' => 'Khởi động 8-10 phút đi bộ nhanh, xoay khớp và kích hoạt cơ',
        'rest' => '45-60 giây',
        'cardio' => 'Cardio nhẹ 10-15 phút',
        'focus_note' => 'Ưu tiên kỹ thuật đúng và tăng tải từ từ.',
        'avoid' => [],
        'replacements' => [],
    ];

    if ($note === '') {
        return $adjustments;
    }

    if (preg_match('/đau gối|gối|khớp gối/u', $note)) {
        $adjustments['avoid'][] = 'hạn chế squat quá sâu, jumping jack và HIIT bật nhảy';
        $adjustments['replacements'][] = 'ưu tiên leg press nhẹ, glute bridge, đi bộ dốc thấp và đạp xe nhẹ';
        $adjustments['cardio'] = 'Đi bộ máy hoặc đạp xe nhẹ 10-12 phút';
        $adjustments['rest'] = '60-75 giây';
    }

    if (preg_match('/đau lưng|lưng dưới|thoát vị|cột sống/u', $note)) {
        $adjustments['avoid'][] = 'tránh deadlift nặng, good morning và các bài gập lưng sâu';
        $adjustments['replacements'][] = 'ưu tiên chest-supported row, bird dog, plank và hip thrust nhẹ';
        $adjustments['focus_note'] = 'Giữ cột sống trung lập, siết core và bỏ qua bài gây đau.';
    }

    if (preg_match('/vai|đau vai|khớp vai/u', $note)) {
        $adjustments['avoid'][] = 'giảm bài overhead press nặng và động tác dang tay quá rộng';
        $adjustments['replacements'][] = 'ưu tiên incline press nhẹ, cable row, face pull và lateral raise nhẹ';
    }

    if (preg_match('/tim mạch|huyết áp|cao huyết áp|tiền đình/u', $note)) {
        $adjustments['avoid'][] = 'tránh HIIT cường độ cao và nín thở khi gắng sức';
        $adjustments['replacements'][] = 'ưu tiên cardio ổn định, mức vừa, theo dõi nhịp tim';
        $adjustments['cardio'] = 'Cardio ổn định 12-20 phút ở mức vừa';
        $adjustments['rest'] = '60-90 giây';
    }

    if (preg_match('/mới tập|ít vận động|lâu không tập/u', $note)) {
        $adjustments['focus_note'] = 'Giữ mức tạ nhẹ, dừng trước khi quá mệt và ưu tiên học kỹ thuật.';
        $adjustments['rest'] = '60-90 giây';
    }

    if (preg_match('/thừa cân|béo|giảm mỡ/u', $note)) {
        $adjustments['cardio'] = 'Đi bộ dốc nhẹ hoặc xe đạp 15-20 phút';
    }

    return $adjustments;
}

function buildFallbackDayTemplates($goal, $daysPerWeek)
{
    $plans = [
        'weight-loss' => [
            3 => ['Full Body đốt mỡ', 'Thân dưới và core', 'Lưng vai kết hợp cardio'],
            4 => ['Ngực vai tay sau', 'Chân và mông', 'Lưng tay trước', 'Full Body và cardio'],
            5 => ['Ngực tay sau', 'Chân mông', 'Lưng tay trước', 'Vai core', 'Cardio và chuyển hóa'],
        ],
        'muscle-gain' => [
            3 => ['Ngực tay sau', 'Lưng tay trước', 'Chân vai'],
            4 => ['Ngực tay sau', 'Lưng tay trước', 'Chân mông', 'Vai core'],
            5 => ['Ngực', 'Lưng', 'Chân', 'Vai', 'Tay và bụng'],
        ],
        'maintain' => [
            3 => ['Full Body', 'Cardio và core', 'Thân trên thân dưới nhẹ'],
            4 => ['Thân trên', 'Thân dưới', 'Cardio và bụng', 'Full Body nhẹ'],
            5 => ['Ngực tay', 'Chân', 'Lưng vai', 'Cardio core', 'Full Body'],
        ],
    ];

    $dayKey = in_array($daysPerWeek, [3, 4], true) ? $daysPerWeek : 5;
    return $plans[$goal][$dayKey] ?? $plans['maintain'][$dayKey];
}

function buildExercisesForFocus($focus, $goal, $level, $adjustments)
{
    $focusLower = mb_strtolower($focus, 'UTF-8');
    $sets = $level === 'advanced' ? '4 hiệp' : '3 hiệp';
    $compoundReps = $goal === 'muscle-gain' ? '8-10 lần' : '10-12 lần';
    $accessoryReps = $goal === 'muscle-gain' ? '10-12 lần' : '12-15 lần';

    if (str_contains($focusLower, 'ngực')) {
        return [
            "- Khởi động: {$adjustments['warmup']}",
            "- Đẩy ngực máy hoặc dumbbell press: {$sets} x {$compoundReps}",
            "- Incline dumbbell press: {$sets} x {$compoundReps}",
            "- Cable fly hoặc pec deck: 3 hiệp x {$accessoryReps}",
            '- Ép tay sau cáp: 3 hiệp x 12-15 lần',
            "- Nghỉ giữa hiệp: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'lưng')) {
        return [
            "- Khởi động: {$adjustments['warmup']}",
            "- Lat pulldown: {$sets} x {$compoundReps}",
            "- Seated row hoặc chest-supported row: {$sets} x {$compoundReps}",
            '- One arm dumbbell row: 3 hiệp x 10-12 lần mỗi bên',
            '- Curl tay trước: 3 hiệp x 12 lần',
            "- Nghỉ giữa hiệp: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'chân') || str_contains($focusLower, 'mông')) {
        return [
            "- Khởi động: {$adjustments['warmup']}",
            "- Goblet squat hoặc leg press nhẹ: {$sets} x {$compoundReps}",
            "- Romanian deadlift nhẹ hoặc hip hinge máy: {$sets} x 10-12 lần",
            '- Glute bridge hoặc hip thrust nhẹ: 3 hiệp x 12 lần',
            '- Leg curl: 3 hiệp x 12-15 lần',
            "- Nghỉ giữa hiệp: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'vai')) {
        return [
            "- Khởi động: {$adjustments['warmup']}",
            "- Dumbbell shoulder press nhẹ: {$sets} x {$compoundReps}",
            '- Lateral raise: 3 hiệp x 12-15 lần',
            '- Rear delt fly hoặc face pull: 3 hiệp x 12-15 lần',
            '- Plank: 3 hiệp x 30-45 giây',
            "- Nghỉ giữa hiệp: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'cardio') || str_contains($focusLower, 'đốt mỡ') || str_contains($focusLower, 'chuyển hóa')) {
        return [
            "- Khởi động: {$adjustments['warmup']}",
            '- Walking lunge hoặc step-up thấp: 3 hiệp x 10-12 lần mỗi bên',
            '- Push-up trên ghế hoặc chest press máy: 3 hiệp x 10-12 lần',
            '- Seated row: 3 hiệp x 10-12 lần',
            '- ' . $adjustments['cardio'],
            "- Nghỉ giữa hiệp: {$adjustments['rest']}",
        ];
    }

    return [
        "- Khởi động: {$adjustments['warmup']}",
        "- Leg press hoặc squat goblet nhẹ: {$sets} x {$compoundReps}",
        "- Chest press máy: {$sets} x {$compoundReps}",
        "- Lat pulldown: {$sets} x {$compoundReps}",
        '- Plank: 3 hiệp x 30-45 giây',
        "- Nghỉ giữa hiệp: {$adjustments['rest']}",
    ];
}

function buildFallbackPlan($memberName, $goal, $level, $daysPerWeek, $healthNote = '')
{
    $goalLabel = getGoalLabel($goal);
    $levelLabel = getLevelLabel($level);
    $adjustments = detectHealthAdjustments($healthNote);
    $focuses = buildFallbackDayTemplates($goal, $daysPerWeek);

    $text = '';
    foreach ($focuses as $index => $focus) {
        $text .= 'Ngày ' . ($index + 1) . ": {$focus}\n";
        $text .= implode("\n", buildExercisesForFocus($focus, $goal, $level, $adjustments)) . "\n\n";
    }

    $extraNotes = [
        "- Mục tiêu: {$goalLabel}",
        "- Trình độ: {$levelLabel}",
        '- ' . $adjustments['focus_note'],
    ];

    foreach ($adjustments['avoid'] as $avoid) {
        $extraNotes[] = "- Tránh: {$avoid}";
    }

    foreach ($adjustments['replacements'] as $replacement) {
        $extraNotes[] = "- Thay thế phù hợp: {$replacement}";
    }

    if ($healthNote !== '') {
        $extraNotes[] = "- Ghi chú sức khỏe đã áp dụng: {$healthNote}";
    }

    $text .= implode("\n", $extraNotes);
    return trim($text);
}

function callGeminiWorkoutPlan($apiKey, $memberName, $goal, $level, $daysPerWeek, $healthNote = '', $model = 'gemini-2.5-flash')
{
    $goalLabel = getGoalLabel($goal);
    $levelLabel = getLevelLabel($level);

    $prompt = "
Bạn là huấn luyện viên gym chuyên nghiệp.

Hãy tạo kế hoạch tập luyện bằng tiếng Việt cho hội viên với thông tin sau:
- Họ tên: {$memberName}
- Mục tiêu: {$goalLabel}
- Trình độ: {$levelLabel}
- Số ngày tập mỗi tuần: {$daysPerWeek}
- Ghi chú sức khỏe: {$healthNote}

Yêu cầu bắt buộc:
1. Chia lịch theo từng ngày rõ ràng.
2. Mỗi ngày phải bắt đầu đúng định dạng: Ngày 1:, Ngày 2:, Ngày 3:...
3. Sau tiêu đề mỗi ngày, liệt kê các bài tập bằng dấu gạch đầu dòng '-'.
4. Mỗi bài tập ghi rõ số hiệp và số lần, ví dụ: 4 hiệp x 10 lần.
5. Có dòng khởi động nếu phù hợp.
6. Có dòng nghỉ giữa hiệp nếu phù hợp.
7. Nội dung thực tế, dễ hiểu, phù hợp người tập gym.
8. Không viết mở đầu dài dòng, không viết kết luận dài.
9. Không dùng bảng markdown.
10. Không dùng ký hiệu lạ, chỉ dùng văn bản thuần để hiển thị trên website.

Mẫu bắt buộc phải giống như sau:

Ngày 1: Tên nhóm cơ hoặc mục tiêu buổi tập
- Khởi động: ...
- Bài tập 1: 4 hiệp x 10 lần
- Bài tập 2: 3 hiệp x 12 lần
- Bài tập 3: 3 hiệp x 12 lần
- Nghỉ giữa hiệp: 45-60 giây

Ngày 2: Tên nhóm cơ hoặc mục tiêu buổi tập
- Khởi động: ...
- Bài tập 1: 4 hiệp x 10 lần
- Bài tập 2: 3 hiệp x 12 lần
- Bài tập 3: 3 hiệp x 12 lần
- Nghỉ giữa hiệp: 45-60 giây

Chỉ trả về nội dung kế hoạch tập luyện đúng format trên.
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
        throw new Exception('Không gọi được Gemini API.');
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Gemini API lỗi.');
    }

    $text = trim($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($text === '') {
        throw new Exception('Gemini không trả về nội dung hợp lệ.');
    }

    return $text;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'workout-plans.php');
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
$level = trim($_POST['level'] ?? '');
$days_per_week = isset($_POST['days_per_week']) ? (int) $_POST['days_per_week'] : 0;
$health_note = trim($_POST['health_note'] ?? '');

if ($member_id <= 0 || $goal === '' || $level === '' || $days_per_week <= 0) {
    header('Location: ' . $base_path . 'workout-plans.php?error=missing_fields');
    exit();
}

$stmtMember = $conn->prepare('SELECT id, full_name FROM members WHERE id = ? LIMIT 1');
$stmtMember->bind_param('i', $member_id);
$stmtMember->execute();
$resultMember = $stmtMember->get_result();
$member = $resultMember ? $resultMember->fetch_assoc() : null;
$stmtMember->close();

if (!$member) {
    header('Location: ' . $base_path . 'workout-plans.php?error=member_not_found');
    exit();
}

$ai_prompt = 'Mục tiêu: ' . getGoalLabel($goal)
    . ' | Trình độ: ' . getLevelLabel($level)
    . ' | Số buổi/tuần: ' . $days_per_week
    . ' | Lưu ý sức khỏe: ' . $health_note;

$ai_response = '';

try {
    if (!isset($gemini_api_key) || trim($gemini_api_key) === '') {
        header('Location: ' . $base_path . 'workout-plans.php?error=gemini_key_missing');
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

$stmtInsert = $conn->prepare('
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
');
$stmtInsert->bind_param(
    'ississss',
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
    header('Location: ' . $base_path . 'workout-plans.php?error=save_failed');
    exit();
}

$stmtInsert->close();

header('Location: ' . $base_path . 'workout-plans.php?success=1&member_id=' . $member_id);
exit();
