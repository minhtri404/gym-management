<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

function getGoalLabel($goal)
{
    switch ($goal) {
        case 'weight-loss':
            return 'Gi?m cân';
        case 'muscle-gain':
            return 'Tang co';
        case 'maintain':
            return 'Gi? dáng';
        default:
            return 'Chua xác d?nh';
    }
}

function getLevelLabel($level)
{
    switch ($level) {
        case 'beginner':
            return 'M?i b?t d?u';
        case 'intermediate':
            return 'Trung bình';
        case 'advanced':
            return 'Nâng cao';
        default:
            return 'Chua xác d?nh';
    }
}

function detectHealthAdjustments($healthNote)
{
    $note = mb_strtolower(trim((string)$healthNote), 'UTF-8');

    $adjustments = [
        'warmup' => 'Kh?i d?ng 8-10 phút di b? nhanh, xoay kh?p và kích ho?t co',
        'rest' => '45-60 giây',
        'cardio' => 'Cardio nh? 10-15 phút',
        'focus_note' => 'Uu tiên k? thu?t dúng và tang t?i t? t?.',
        'avoid' => [],
        'replacements' => [],
    ];

    if ($note === '') {
        return $adjustments;
    }

    if (preg_match('/dau g?i|g?i|kh?p g?i/u', $note)) {
        $adjustments['avoid'][] = 'h?n ch? squat quá sâu, jumping jack và HIIT b?t nh?y';
        $adjustments['replacements'][] = 'uu tiên leg press nh?, glute bridge, di b? d?c th?p và d?p xe nh?';
        $adjustments['cardio'] = 'Ði b? máy ho?c d?p xe nh? 10-12 phút';
        $adjustments['rest'] = '60-75 giây';
    }

    if (preg_match('/dau lung|lung du?i|thoát v?|c?t s?ng/u', $note)) {
        $adjustments['avoid'][] = 'tránh deadlift n?ng, good morning và các bài g?p lung sâu';
        $adjustments['replacements'][] = 'uu tiên chest-supported row, bird dog, plank và hip thrust nh?';
        $adjustments['focus_note'] = 'Gi? c?t s?ng trung l?p, si?t core và b? qua bài gây dau.';
    }

    if (preg_match('/vai|dau vai|kh?p vai/u', $note)) {
        $adjustments['avoid'][] = 'gi?m bài overhead press n?ng và d?ng tác dang tay quá r?ng';
        $adjustments['replacements'][] = 'uu tiên incline press nh?, cable row, face pull và lateral raise nh?';
    }

    if (preg_match('/tim m?ch|huy?t áp|cao huy?t áp|ti?n dình/u', $note)) {
        $adjustments['avoid'][] = 'tránh HIIT cu?ng d? cao và nín th? khi g?ng s?c';
        $adjustments['replacements'][] = 'uu tiên cardio ?n d?nh, m?c v?a, theo dõi nh?p tim';
        $adjustments['cardio'] = 'Cardio ?n d?nh 12-20 phút ? m?c v?a';
        $adjustments['rest'] = '60-90 giây';
    }

    if (preg_match('/m?i t?p|ít v?n d?ng|lâu không t?p/u', $note)) {
        $adjustments['focus_note'] = 'Gi? m?c t? nh?, d?ng tru?c khi quá m?i và uu tiên h?c k? thu?t.';
        $adjustments['rest'] = '60-90 giây';
    }

    if (preg_match('/th?a cân|béo|gi?m m?/u', $note)) {
        $adjustments['cardio'] = 'Ði b? d?c nh? ho?c xe d?p 15-20 phút';
    }

    return $adjustments;
}

function buildFallbackDayTemplates($goal, $daysPerWeek)
{
    $plans = [
        'weight-loss' => [
            3 => ['Full Body d?t m?', 'Thân du?i và core', 'Lung vai k?t h?p cardio'],
            4 => ['Ng?c vai tay sau', 'Chân và mông', 'Lung tay tru?c', 'Full Body và cardio'],
            5 => ['Ng?c tay sau', 'Chân mông', 'Lung tay tru?c', 'Vai core', 'Cardio và chuy?n hóa']
        ],
        'muscle-gain' => [
            3 => ['Ng?c tay sau', 'Lung tay tru?c', 'Chân vai'],
            4 => ['Ng?c tay sau', 'Lung tay tru?c', 'Chân mông', 'Vai core'],
            5 => ['Ng?c', 'Lung', 'Chân', 'Vai', 'Tay và b?ng']
        ],
        'maintain' => [
            3 => ['Full Body', 'Cardio và core', 'Thân trên thân du?i nh?'],
            4 => ['Thân trên', 'Thân du?i', 'Cardio và b?ng', 'Full Body nh?'],
            5 => ['Ng?c tay', 'Chân', 'Lung vai', 'Cardio core', 'Full Body']
        ],
    ];

    $dayKey = in_array($daysPerWeek, [3, 4], true) ? $daysPerWeek : 5;
    return $plans[$goal][$dayKey] ?? $plans['maintain'][$dayKey];
}

function buildExercisesForFocus($focus, $goal, $level, $adjustments)
{
    $focusLower = mb_strtolower($focus, 'UTF-8');
    $sets = $level === 'advanced' ? '4 hi?p' : '3 hi?p';
    $compoundReps = $goal === 'muscle-gain' ? '8-10 l?n' : '10-12 l?n';
    $accessoryReps = $goal === 'muscle-gain' ? '10-12 l?n' : '12-15 l?n';

    if (str_contains($focusLower, 'ng?c')) {
        return [
            "- Kh?i d?ng: {$adjustments['warmup']}",
            "- Ð?y ng?c máy ho?c dumbbell press: {$sets} x {$compoundReps}",
            "- Incline dumbbell press: {$sets} x {$compoundReps}",
            "- Cable fly ho?c pec deck: 3 hi?p x {$accessoryReps}",
            "- Ép tay sau cáp: 3 hi?p x 12-15 l?n",
            "- Ngh? gi?a hi?p: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'lung')) {
        return [
            "- Kh?i d?ng: {$adjustments['warmup']}",
            "- Lat pulldown: {$sets} x {$compoundReps}",
            "- Seated row ho?c chest-supported row: {$sets} x {$compoundReps}",
            "- One arm dumbbell row: 3 hi?p x 10-12 l?n m?i bên",
            "- Curl tay tru?c: 3 hi?p x 12 l?n",
            "- Ngh? gi?a hi?p: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'chân') || str_contains($focusLower, 'mông')) {
        return [
            "- Kh?i d?ng: {$adjustments['warmup']}",
            "- Goblet squat ho?c leg press nh?: {$sets} x {$compoundReps}",
            "- Romanian deadlift nh? ho?c hip hinge máy: {$sets} x 10-12 l?n",
            "- Glute bridge ho?c hip thrust nh?: 3 hi?p x 12 l?n",
            "- Leg curl: 3 hi?p x 12-15 l?n",
            "- Ngh? gi?a hi?p: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'vai')) {
        return [
            "- Kh?i d?ng: {$adjustments['warmup']}",
            "- Dumbbell shoulder press nh?: {$sets} x {$compoundReps}",
            "- Lateral raise: 3 hi?p x 12-15 l?n",
            "- Rear delt fly ho?c face pull: 3 hi?p x 12-15 l?n",
            "- Plank: 3 hi?p x 30-45 giây",
            "- Ngh? gi?a hi?p: {$adjustments['rest']}",
        ];
    }

    if (str_contains($focusLower, 'cardio') || str_contains($focusLower, 'd?t m?') || str_contains($focusLower, 'chuy?n hóa')) {
        return [
            "- Kh?i d?ng: {$adjustments['warmup']}",
            "- Walking lunge ho?c step-up th?p: 3 hi?p x 10-12 l?n m?i bên",
            "- Push-up trên gh? ho?c chest press máy: 3 hi?p x 10-12 l?n",
            "- Seated row: 3 hi?p x 10-12 l?n",
            "- {$adjustments['cardio']}",
            "- Ngh? gi?a hi?p: {$adjustments['rest']}",
        ];
    }

    return [
        "- Kh?i d?ng: {$adjustments['warmup']}",
        "- Leg press ho?c squat goblet nh?: {$sets} x {$compoundReps}",
        "- Chest press máy: {$sets} x {$compoundReps}",
        "- Lat pulldown: {$sets} x {$compoundReps}",
        "- Plank: 3 hi?p x 30-45 giây",
        "- Ngh? gi?a hi?p: {$adjustments['rest']}",
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
        $text .= "Ngày " . ($index + 1) . ": {$focus}\n";
        $text .= implode("\n", buildExercisesForFocus($focus, $goal, $level, $adjustments)) . "\n\n";
    }

    $extraNotes = [
        "- M?c tiêu: {$goalLabel}",
        "- Trình d?: {$levelLabel}",
        "- {$adjustments['focus_note']}",
    ];

    foreach ($adjustments['avoid'] as $avoid) {
        $extraNotes[] = "- Tránh: {$avoid}";
    }

    foreach ($adjustments['replacements'] as $replacement) {
        $extraNotes[] = "- Thay th? phù h?p: {$replacement}";
    }

    if ($healthNote !== '') {
        $extraNotes[] = "- Ghi chú s?c kh?e dã áp d?ng: {$healthNote}";
    }

    $text .= implode("\n", $extraNotes);
    return trim($text);
}

function callGeminiWorkoutPlan($apiKey, $memberName, $goal, $level, $daysPerWeek, $healthNote = '')
{
    $goalLabel = getGoalLabel($goal);
    $levelLabel = getLevelLabel($level);

    $prompt = "
B?n là hu?n luy?n viên gym chuyên nghi?p.

Hãy t?o k? ho?ch t?p luy?n b?ng ti?ng Vi?t cho h?i viên v?i thông tin sau:
- H? tên: {$memberName}
- M?c tiêu: {$goalLabel}
- Trình d?: {$levelLabel}
- S? ngày t?p m?i tu?n: {$daysPerWeek}
- Ghi chú s?c kh?e: {$healthNote}

Yêu c?u b?t bu?c:
1. Chia l?ch theo t?ng ngày rõ ràng.
2. M?i ngày ph?i b?t d?u dúng d?nh d?ng: Ngày 1:, Ngày 2:, Ngày 3:...
3. Sau tiêu d? m?i ngày, li?t kê các bài t?p b?ng d?u g?ch d?u dòng '-'.
4. M?i bài t?p ghi rõ s? hi?p và s? l?n, ví d?: 4 hi?p x 10 l?n.
5. Có dòng kh?i d?ng n?u phù h?p.
6. Có dòng ngh? gi?a hi?p n?u phù h?p.
7. N?i dung th?c t?, d? hi?u, phù h?p ngu?i t?p gym.
8. Không vi?t m? d?u dài dòng, không vi?t k?t lu?n dài.
9. Không dùng b?ng markdown.
10. Không dùng ký hi?u l?, ch? dùng van b?n thu?n d? hi?n th? trên website.

M?u b?t bu?c ph?i gi?ng nhu sau:

Ngày 1: Tên nhóm co ho?c m?c tiêu bu?i t?p
- Kh?i d?ng: ...
- Bài t?p 1: 4 hi?p x 10 l?n
- Bài t?p 2: 3 hi?p x 12 l?n
- Bài t?p 3: 3 hi?p x 12 l?n
- Ngh? gi?a hi?p: 45-60 giây

Ngày 2: Tên nhóm co ho?c m?c tiêu bu?i t?p
- Kh?i d?ng: ...
- Bài t?p 1: 4 hi?p x 10 l?n
- Bài t?p 2: 3 hi?p x 12 l?n
- Bài t?p 3: 3 hi?p x 12 l?n
- Ngh? gi?a hi?p: 45-60 giây

Ch? tr? v? n?i dung k? ho?ch t?p luy?n dúng format trên.
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
        throw new Exception('Không g?i du?c Gemini API.');
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Gemini API l?i.');
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim($text);

    if ($text === '') {
        throw new Exception('Gemini không tr? v? n?i dung h?p l?.');
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
    die('CSRF token không h?p l?.');
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

$ai_prompt = "M?c tiêu: " . getGoalLabel($goal)
    . " | Trình d?: " . getLevelLabel($level)
    . " | S? bu?i/tu?n: " . $days_per_week
    . " | Luu ý s?c kh?e: " . $health_note;

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


