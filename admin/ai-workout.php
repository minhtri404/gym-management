<?php
$page_title = "AI gợi ý lịch tập";
include __DIR__ . '/../includes/auth-check.php';
$error = '';
$success = '';
$generated_plan = '';
$generated_title = '';
$selected_member_id = '';
$goal = '';
$level = '';
$days_per_week = '';
$notes = '';
$members = [];

$resultMembers = $conn->query("SELECT id, full_name, phone, status FROM members ORDER BY id DESC");
if ($resultMembers && $resultMembers->num_rows > 0) {
  while ($row = $resultMembers->fetch_assoc()) {
    $members[] = $row;
  }
}

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getGoalLabel($goal)
{
  switch ($goal) {
    case 'weight-loss':
      return 'Gi?m c�n';
    case 'muscle-gain':
      return 'Tang co';
    case 'maintain':
      return 'Gi? d�ng';
    default:
      return 'Chua x�c d?nh';
  }
}

function getLevelLabel($level)
{
  switch ($level) {
    case 'beginner':
      return 'M?i b?t d?u';
    case 'intermediate':
      return 'Trung b�nh';
    case 'advanced':
      return 'N�ng cao';
    default:
      return 'Chua x�c d?nh';
  }
}

function detectHealthAdjustments($notes)
{
  $note = mb_strtolower(trim((string)$notes), 'UTF-8');

  $adjustments = [
    'warmup' => 'Kh?i d?ng 8-10 ph�t di b? nhanh, xoay kh?p v� k�ch ho?t co',
    'rest' => '45-60 gi�y',
    'cardio' => 'Cardio nh? 10-15 ph�t',
    'focus_note' => 'Uu ti�n k? thu?t d�ng v� tang t?i t? t?.',
    'avoid' => [],
    'replacements' => [],         
  ];

  if ($note === '') {
    return $adjustments;
  }

  if (preg_match('/dau g?i|g?i|kh?p g?i/u', $note)) {
    $adjustments['avoid'][] = 'h?n ch? squat qu� s�u, jumping jack v� HIIT b?t nh?y';
    $adjustments['replacements'][] = 'uu ti�n leg press nh?, glute bridge, di b? d?c th?p v� d?p xe nh?';
    $adjustments['cardio'] = '�i b? m�y ho?c d?p xe nh? 10-12 ph�t';
    $adjustments['rest'] = '60-75 gi�y';
  }

  if (preg_match('/dau lung|lung du?i|tho�t v?|c?t s?ng/u', $note)) {
    $adjustments['avoid'][] = 'tr�nh deadlift n?ng, good morning v� c�c b�i g?p lung s�u';
    $adjustments['replacements'][] = 'uu ti�n chest-supported row, bird dog, plank v� hip thrust nh?';
    $adjustments['focus_note'] = 'Gi? c?t s?ng trung l?p, si?t core v� b? qua b�i g�y dau.';
  }

  if (preg_match('/vai|dau vai|kh?p vai/u', $note)) {
    $adjustments['avoid'][] = 'gi?m b�i overhead press n?ng v� d?ng t�c dang tay qu� r?ng';
    $adjustments['replacements'][] = 'uu ti�n incline press nh?, cable row, face pull v� lateral raise nh?';
  }

  if (preg_match('/tim m?ch|huy?t �p|cao huy?t �p|ti?n d�nh/u', $note)) {
    $adjustments['avoid'][] = 'tr�nh HIIT cu?ng d? cao v� n�n th? khi g?ng s?c';
    $adjustments['replacements'][] = 'uu ti�n cardio ?n d?nh, m?c v?a, theo d�i nh?p tim';
    $adjustments['cardio'] = 'Cardio ?n d?nh 12-20 ph�t ? m?c v?a';
    $adjustments['rest'] = '60-90 gi�y';
  }

  if (preg_match('/m?i t?p|�t v?n d?ng|l�u kh�ng t?p/u', $note)) {
    $adjustments['focus_note'] = 'Gi? m?c t? nh?, d?ng tru?c khi qu� m?i v� uu ti�n h?c k? thu?t.';
    $adjustments['rest'] = '60-90 gi�y';
  }

  if (preg_match('/th?a c�n|b�o|gi?m m?/u', $note)) {
    $adjustments['cardio'] = '�i b? d?c nh? ho?c xe d?p 15-20 ph�t';
  }

  return $adjustments;
}

function buildFallbackDayTemplates($goal, $daysPerWeek)
{
  $plans = [
    'weight-loss' => [
      3 => ['Full Body d?t m?', 'Th�n du?i v� core', 'Lung vai k?t h?p cardio'],
      4 => ['Ng?c vai tay sau', 'Ch�n v� m�ng', 'Lung tay tru?c', 'Full Body v� cardio'],
      5 => ['Ng?c tay sau', 'Ch�n m�ng', 'Lung tay tru?c', 'Vai core', 'Cardio v� chuy?n h�a']
    ],
    'muscle-gain' => [
      3 => ['Ng?c tay sau', 'Lung tay tru?c', 'Ch�n vai'],
      4 => ['Ng?c tay sau', 'Lung tay tru?c', 'Ch�n m�ng', 'Vai core'],
      5 => ['Ng?c', 'Lung', 'Ch�n', 'Vai', 'Tay v� b?ng']
    ],
    'maintain' => [
      3 => ['Full Body', 'Cardio v� core', 'Th�n tr�n th�n du?i nh?'],
      4 => ['Th�n tr�n', 'Th�n du?i', 'Cardio v� b?ng', 'Full Body nh?'],
      5 => ['Ng?c tay', 'Ch�n', 'Lung vai', 'Cardio core', 'Full Body']
    ],
  ];

  $dayKey = in_array((int)$daysPerWeek, [3, 4], true) ? (int)$daysPerWeek : 5;
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
      ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
      ['name' => '�?y ng?c m�y ho?c dumbbell press', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Incline dumbbell press', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Cable fly ho?c pec deck', 'sets' => '3 hi?p', 'reps' => $accessoryReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => '�p tay sau c�p', 'sets' => '3 hi?p', 'reps' => '12-15 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
    ];
  }

  if (str_contains($focusLower, 'lung')) {
    return [
      ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
      ['name' => 'Lat pulldown', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Seated row ho?c chest-supported row', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'One arm dumbbell row', 'sets' => '3 hi?p', 'reps' => '10-12 l?n m?i b�n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Curl tay tru?c', 'sets' => '3 hi?p', 'reps' => '12 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
    ];
  }

  if (str_contains($focusLower, 'ch�n') || str_contains($focusLower, 'm�ng')) {
    return [
      ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
      ['name' => 'Goblet squat ho?c leg press nh?', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Romanian deadlift nh? ho?c hip hinge m�y', 'sets' => $sets, 'reps' => '10-12 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Glute bridge ho?c hip thrust nh?', 'sets' => '3 hi?p', 'reps' => '12 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Leg curl', 'sets' => '3 hi?p', 'reps' => '12-15 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
    ];
  }

  if (str_contains($focusLower, 'vai')) {
    return [
      ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
      ['name' => 'Dumbbell shoulder press nh?', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Lateral raise', 'sets' => '3 hi?p', 'reps' => '12-15 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Rear delt fly ho?c face pull', 'sets' => '3 hi?p', 'reps' => '12-15 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Plank', 'sets' => '3 hi?p', 'reps' => '30-45 gi�y', 'rest' => $adjustments['rest'], 'note' => ''],
    ];
  }

  if (str_contains($focusLower, 'cardio') || str_contains($focusLower, 'd?t m?') || str_contains($focusLower, 'chuy?n h�a')) {
    return [
      ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
      ['name' => 'Walking lunge ho?c step-up th?p', 'sets' => '3 hi?p', 'reps' => '10-12 l?n m?i b�n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Push-up tr�n gh? ho?c chest press m�y', 'sets' => '3 hi?p', 'reps' => '10-12 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => 'Seated row', 'sets' => '3 hi?p', 'reps' => '10-12 l?n', 'rest' => $adjustments['rest'], 'note' => ''],
      ['name' => $adjustments['cardio'], 'sets' => '', 'reps' => '', 'rest' => '', 'note' => 'Gi? nh?p ?n d?nh, kh�ng qu� s?c'],
    ];
  }

  return [
    ['name' => 'Kh?i d?ng', 'sets' => '', 'reps' => '', 'rest' => '', 'note' => $adjustments['warmup']],
    ['name' => 'Leg press ho?c squat goblet nh?', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
    ['name' => 'Chest press m�y', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
    ['name' => 'Lat pulldown', 'sets' => $sets, 'reps' => $compoundReps, 'rest' => $adjustments['rest'], 'note' => ''],
    ['name' => 'Plank', 'sets' => '3 hi?p', 'reps' => '30-45 gi�y', 'rest' => $adjustments['rest'], 'note' => ''],
  ];
}

function buildFallbackPlan($goal, $level, $days_per_week, $notes = '')
{
  $goal_label = getGoalLabel($goal);
  $level_label = getLevelLabel($level);
  $adjustments = detectHealthAdjustments($notes);
  $focuses = buildFallbackDayTemplates($goal, $days_per_week);

  $days = [];
  $text = "K? ho?ch t?p luy?n - {$goal_label}\n";
  $text .= "Tr�nh d?: {$level_label}\n";
  $text .= "S? bu?i/tu?n: {$days_per_week}\n\n";

  foreach ($focuses as $index => $focus) {
    $exercises = buildExercisesForFocus($focus, $goal, $level, $adjustments);
    $days[] = [
      'day' => 'Ng�y ' . ($index + 1),
      'focus' => $focus,
      'exercises' => $exercises
    ];

    $text .= 'Ng�y ' . ($index + 1) . ": {$focus}\n";
    foreach ($exercises as $exercise) {
      $line = '- ' . ($exercise['name'] ?? 'B�i t?p');
      if (!empty($exercise['note']) && ($exercise['name'] ?? '') === 'Kh?i d?ng') {
        $line .= ': ' . $exercise['note'];
      } elseif (!empty($exercise['sets']) || !empty($exercise['reps'])) {
        $parts = [];
        if (!empty($exercise['sets'])) {
          $parts[] = $exercise['sets'];
        }
        if (!empty($exercise['reps'])) {
          $parts[] = $exercise['reps'];
        }
        $line .= ': ' . implode(' x ', $parts);
      } elseif (!empty($exercise['note'])) {
        $line .= ': ' . $exercise['note'];
      }
      $text .= $line . "\n";
      if (!empty($exercise['rest']) && ($exercise['name'] ?? '') !== 'Kh?i d?ng') {
        $text .= "- Ngh? gi?a hi?p: {$exercise['rest']}\n";
      }
    }
    $text .= "\n";
  }

  $noteLines = [
    $adjustments['focus_note']
  ];
  foreach ($adjustments['avoid'] as $avoid) {
    $noteLines[] = 'Tr�nh: ' . $avoid;
  }
  foreach ($adjustments['replacements'] as $replacement) {
    $noteLines[] = 'Thay th? ph� h?p: ' . $replacement;
  }
  if (!empty($notes)) {
    $noteLines[] = 'Ghi ch� s?c kh?e d� �p d?ng: ' . $notes;
  }

  $text .= "Luu � �p d?ng:\n";
  foreach ($noteLines as $noteLine) {
    $text .= '- ' . $noteLine . "\n";
  }

  return [
    'title' => "K? ho?ch {$goal_label}",
    'level' => $level_label,
    'days' => $days,
    'note' => trim(implode('; ', array_filter($noteLines))),
    'plan_text' => trim($text)
  ];
}
// Hàm này giúp loại bỏ các dấu ``` nếu Gemini trả về nội dung có định dạng code block, đồng thời trim khoảng trắng thừa.
function parseGeminiPlanText($text)
{
  $text = trim($text);
  if (str_starts_with($text, '```')) {
    $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
    $text = preg_replace('/```\s*$/', '', $text);
    $text = trim($text);
  }

  return $text;
}

function formatPlanToText($plan)
{
  if (!is_array($plan)) {
    return '';
  }

  $text = '';
  if (!empty($plan['title'])) {
    $text .= $plan['title'] . "\n";
  }

  if (!empty($plan['level'])) {
    $text .= "Trình độ: " . $plan['level'] . "\n";
  }

  if (!empty($plan['days']) && is_array($plan['days'])) {
    $text .= "\n";
    foreach ($plan['days'] as $index => $day) {
      if (is_array($day)) {
        $dayTitle = $day['day'] ?? $day['title'] ?? $day['name'] ?? ('Buổi ' . ($index + 1));
        $focus = $day['focus'] ?? '';
        $text .= "- " . $dayTitle;
        if ($focus !== '') {
          $text .= ": " . $focus;
        }
        $text .= "\n";

        if (!empty($day['exercises']) && is_array($day['exercises'])) {
          foreach ($day['exercises'] as $exercise) {
            if (!is_array($exercise)) {
              $text .= "  + " . $exercise . "\n";
              continue;
            }
            $line = "  + " . ($exercise['name'] ?? 'Bài tập');
            if (!empty($exercise['sets']) || !empty($exercise['reps'])) {
              $parts = [];
              if (!empty($exercise['sets'])) {
                $parts[] = $exercise['sets'];
              }
              if (!empty($exercise['reps'])) {
                $parts[] = $exercise['reps'];
              }
              $line .= " (" . implode(', ', $parts) . ")";
            }
            if (!empty($exercise['rest'])) {
              $line .= " - Nghỉ " . $exercise['rest'];
            }
            $text .= $line . "\n";
            if (!empty($exercise['note'])) {
              $text .= "    * " . $exercise['note'] . "\n";
            }
          }
        }
      } else {
        $text .= "- " . $day . "\n";
      }
    }
  }

  if (!empty($plan['note'])) {
    $text .= "\nLưu ý: " . $plan['note'] . "\n";
  }

  return trim($text);
}
// Hàm này sẽ gọi Gemini API để tạo kế hoạch tập luyện dựa trên thông tin đầu vào. Nó sẽ trả về một mảng kết quả đã được parse, hoặc ném lỗi nếu có vấn đề với API.
function callGeminiWorkoutPlan($apiKey, $memberName, $goal, $level, $daysPerWeek, $notes = '', $model = 'gemini-2.5-flash')
{
  $goalLabel = getGoalLabel($goal);
  $levelLabel = getLevelLabel($level);

  $prompt = <<<PROMPT
Bạn là huấn luyện viên gym chuyên nghiệp.

Hãy tạo kế hoạch tập luyện bằng tiếng Việt cho hội viên với thông tin sau:
- Họ tên: {$memberName}
- Mục tiêu: {$goalLabel}
- Trình độ: {$levelLabel}
- Số ngày tập mỗi tuần: {$daysPerWeek}
- Ghi chú sức khỏe: {$notes}

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
10. Không dùng ký hiệu lạ, chỉ dùng văn bản thuần dễ hiển thị trên website.

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

Thay vì trả về văn bản thuần, hãy chuyển đúng nội dung kế hoạch trên thành JSON hợp lệ theo schema sau:
{
  "title": "Kế hoạch tập luyện cho ...",
  "level": "Mới bắt đầu/Cơ bản/Nâng cao",
  "days": [
    {
      "day": "Ngày 1",
      "focus": "Tên nhóm cơ hoặc mục tiêu buổi tập",
      "exercises": [
        { "name": "Khởi động", "sets": "", "reps": "", "rest": "", "note": "..." },
        { "name": "Bài tập 1", "sets": "4 hiệp", "reps": "10 lần", "rest": "45-60 giây", "note": "" }
      ]
    }
  ],
  "note": "Lưu ý thêm nếu có"
}
Chỉ trả về JSON hợp lệ, không dùng markdown, không thêm giải thích ngoài JSON.
PROMPT;

  $payload = [
    'contents' => [
      [
        'parts' => [
          ['text' => "Bạn là huấn luyện viên gym chuyên nghiệp. Hãy trả về JSON hợp lệ đúng schema đã yêu cầu, không thêm markdown hay giải thích ngoài JSON."],
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

  if ($response === false || !empty($curlError)) {
    throw new Exception('Kh?ng g?i ???c Gemini API: ' . $curlError);
  }

  $decoded = json_decode($response, true);

  if ($httpCode < 200 || $httpCode >= 300) {
    $message = 'Gemini API l?i';
    if (!empty($decoded['error']['message'])) {
      $message .= ': ' . $decoded['error']['message'];
    }
    $message .= ' (HTTP ' . $httpCode . ')';
    error_log('Gemini debug: HTTP ' . $httpCode . ' response=' . $response);
    throw new Exception($message);
  }

  $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
  if ($text === '') {
    throw new Exception('Gemini kh?ng tr? v? n?i dung h?p l?.');
  }

  $text = parseGeminiPlanText($text);
  $plan = json_decode($text, true);

  if (!is_array($plan) || empty($plan['title']) || empty($plan['days'])) {
    throw new Exception('Kh?ng parse ???c JSON k? ho?ch t? Gemini.');
  }

  return $plan;
}
// Hàm này sẽ gọi Gemini với cơ chế retry cho các lỗi tạm thời như 429, 500, 502, 503, 504. Nó sẽ thử cả hai model 'gemini-2.5-flash' và 'gemini-2.5-pro' để tăng khả năng thành công.
function callGeminiWorkoutPlanWithRetry($apiKey, $memberName, $goal, $level, $daysPerWeek, $notes = '')
{
  $models = ['gemini-2.5-flash', 'gemini-2.5-pro'];
  $retryable = [429, 500, 502, 503, 504];
  $attempts = 0;
  $last_error = '';

  foreach ($models as $model) {
    for ($i = 0; $i < 3; $i++) {
      $attempts++;
      try {
        return callGeminiWorkoutPlan($apiKey, $memberName, $goal, $level, $daysPerWeek, $notes, $model);
      } catch (Exception $e) {
        $last_error = $e->getMessage();
        if (preg_match('/HTTP\s(\d{3})/', $last_error, $matches)) {
          $code = (int) $matches[1];
          if (!in_array($code, $retryable, true)) {
            throw $e;
          }
        }
        usleep(700000);
      }
    }
  }

  throw new Exception('Gemini t?m th?i l?i sau ' . $attempts . ' l?n th?. ' . $last_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selected_member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
  $goal = trim($_POST['goal'] ?? '');
  $level = trim($_POST['level'] ?? '');
  $days_per_week = isset($_POST['days_per_week']) ? (int)$_POST['days_per_week'] : 0;
  $notes = trim($_POST['notes'] ?? '');

  if ($selected_member_id <= 0 || $goal === '' || $level === '' || $days_per_week <= 0) {
    $error = 'Vui lòng nhập đầy đủ thông tin.';
  } else {
    $stmtMember = $conn->prepare("SELECT id, full_name, status FROM members WHERE id = ? LIMIT 1");
    $stmtMember->bind_param("i", $selected_member_id);
    $stmtMember->execute();
    $memberResult = $stmtMember->get_result();
    $member = $memberResult->fetch_assoc();
    $stmtMember->close();

    if (!$member) {
      $error = 'Hội viên không tồn tại.';
    } else {
      try {
        $missing_key = $gemini_api_key_missing ?? false;
        if ($missing_key || empty($gemini_api_key)) {
          throw new Exception('Chưa cấu hình GEMINI_API_KEY trong server.');
        }

        $aiPlan = callGeminiWorkoutPlanWithRetry(
          $gemini_api_key,
          $member['full_name'],
          $goal,
          $level,
          $days_per_week,
          $notes
        );

        $generated_title = $aiPlan['title'];
        $generated_plan = formatPlanToText($aiPlan);
      } catch (Exception $e) {
        $fallback = buildFallbackPlan($goal, $level, $days_per_week, $notes);
        $generated_title = $fallback['title'] . ' (fallback)';
        $generated_plan = $fallback['plan_text'];
        error_log('Gemini error: ' . $e->getMessage());
        if (!empty($missing_key)) {
          $error = 'Chưa đọc được GEMINI_API_KEY. Hãy set key và khởi động lại Laragon.';
        } else {
          $error = 'Gemini lỗi hoặc chưa cấu hình đúng. Đã dùng lịch dự phòng.';
        }
      }

      if ($generated_plan !== '') {
        $stmtInsert = $conn->prepare("
                    INSERT INTO workout_plans (member_id, goal, level, days_per_week, plan_text)
                    VALUES (?, ?, ?, ?, ?)
                ");
        $stmtInsert->bind_param(
          "issis",
          $selected_member_id,
          $goal,
          $level,
          $days_per_week,
          $generated_plan
        );

        if ($stmtInsert->execute()) {
          $success = 'Đã tạo và lưu lịch tập thành công.';
        } else {
          $error = 'Tạo được kế hoạch nhưng lưu database thất bại: ' . $stmtInsert->error;
        }
        $stmtInsert->close();
      }
    }
  }
}

// Recent plans
$recentPlans = [];
$sqlRecent = "
    SELECT wp.id, wp.goal, wp.level, wp.days_per_week, wp.plan_text, wp.created_at, m.full_name
    FROM workout_plans wp
    INNER JOIN members m ON wp.member_id = m.id
    ORDER BY wp.id DESC
    LIMIT 5
";
$resultRecent = $conn->query($sqlRecent);
if ($resultRecent && $resultRecent->num_rows > 0) {
  while ($row = $resultRecent->fetch_assoc()) {
    $recentPlans[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Workout - Gym Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .plan-box {
      white-space: pre-line;
      background: #f8f9fa;
      border-radius: 16px;
      padding: 20px;
      border: 1px solid #e9ecef;
    }

    .recent-plan-card {
      border: 1px solid #e9ecef;
      border-radius: 14px;
      padding: 16px;
      background: #fff;
    }

    .health-note-preview ul {
      padding-left: 1rem;
      margin-bottom: 0;
    }

    .health-note-preview li + li {
      margin-top: 0.25rem;
    }
  </style>
</head>

<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include __DIR__ . '/includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="row g-4">
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Tạo lịch tập bằng Gemini</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <?php if ($success): ?>
                  <div class="alert alert-success"><?php echo h($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                  <div class="alert alert-warning"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                  <div class="mb-3">
                    <label class="form-label">Hội viên</label>
                    <select name="member_id" class="form-select" required>
                      <option value="">-- Chọn hội viên --</option>
                      <?php foreach ($members as $member): ?>
                        <option value="<?php echo (int)$member['id']; ?>" <?php echo ((string)$selected_member_id === (string)$member['id']) ? 'selected' : ''; ?>>
                          <?php echo h($member['full_name']); ?> - <?php echo h($member['phone']); ?> (<?php echo h($member['status']); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Mục tiêu</label>
                    <select name="goal" class="form-select" required>
                      <option value="">-- Chọn mục tiêu --</option>
                      <option value="weight-loss" <?php echo $goal === 'weight-loss' ? 'selected' : ''; ?>>Giảm cân</option>
                      <option value="muscle-gain" <?php echo $goal === 'muscle-gain' ? 'selected' : ''; ?>>Tăng cơ</option>
                      <option value="maintain" <?php echo $goal === 'maintain' ? 'selected' : ''; ?>>Giữ dáng</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Số buổi / tuần</label>
                    <select name="days_per_week" class="form-select" required>
                      <option value="">-- Chọn số buổi --</option>
                      <option value="3" <?php echo $days_per_week == 3 ? 'selected' : ''; ?>>3 buổi</option>
                      <option value="4" <?php echo $days_per_week == 4 ? 'selected' : ''; ?>>4 buổi</option>
                      <option value="5" <?php echo $days_per_week == 5 ? 'selected' : ''; ?>>5 buổi</option>
                      <option value="6" <?php echo $days_per_week == 6 ? 'selected' : ''; ?>>6 buổi</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Kinh nghiệm tập</label>
                    <select name="level" class="form-select" required>
                      <option value="">-- Chọn trình độ --</option>
                      <option value="beginner" <?php echo $level === 'beginner' ? 'selected' : ''; ?>>Mới bắt đầu</option>
                      <option value="intermediate" <?php echo $level === 'intermediate' ? 'selected' : ''; ?>>Trung bình</option>
                      <option value="advanced" <?php echo $level === 'advanced' ? 'selected' : ''; ?>>Nâng cao</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Ví dụ: đau gối nhẹ, muốn ưu tiên cardio..."><?php echo h($notes); ?></textarea>
                    <div id="health-note-preview" class="alert alert-info d-none mt-2 mb-0 small health-note-preview"></div>
                  </div>

                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-stars me-2"></i>Tạo lịch tập bằng AI
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Kết quả vừa tạo</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <?php if ($generated_plan !== ''): ?>
                  <h6 class="fw-bold mb-3"><?php echo h($generated_title); ?></h6>
                  <div class="plan-box"><?php echo nl2br(h($generated_plan)); ?></div>
                <?php else: ?>
                  <div class="text-center text-muted py-5">
                    <i class="bi bi-clipboard2-pulse fs-1 d-block mb-3"></i>
                    Chưa có lịch tập. Hãy nhập thông tin và bấm tạo lịch tập.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">5 lịch tập gần nhất</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <?php if (!empty($recentPlans)): ?>
                  <div class="d-flex flex-column gap-3">
                    <?php foreach ($recentPlans as $plan): ?>
                      <div class="recent-plan-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <div>
                            <div class="fw-bold"><?php echo h($plan['full_name']); ?></div>
                            <div class="text-muted small">
                              <?php echo h(getGoalLabel($plan['goal'])); ?> •
                              <?php echo h(getLevelLabel($plan['level'])); ?> •
                              <?php echo (int)$plan['days_per_week']; ?> buổi/tuần
                            </div>
                          </div>
                          <span class="badge text-bg-light"><?php echo h($plan['created_at']); ?></span>
                        </div>
                        <div class="small" style="white-space: pre-line;"><?php echo h($plan['plan_text']); ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="text-muted">Chưa có lịch tập nào được lưu.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    (function() {
      const noteInput = document.querySelector('textarea[name="notes"]');
      const previewBox = document.getElementById('health-note-preview');

      if (!noteInput || !previewBox) {
        return;
      }

      const rules = [
        {
          pattern: /dau g?i|kh?p g?i|g?i/iu,
          title: 'Ph�t hi?n luu � v? g?i',
          items: [
            'H? th?ng s? gi?m squat qu� s�u, d?ng t�c b?t nh?y v� HIIT cu?ng d? cao.',
            'S? uu ti�n leg press nh?, glute bridge, di b? m�y ho?c xe d?p nh?.'
          ]
        },
        {
          pattern: /dau lung|lung du?i|tho�t v?|c?t s?ng/iu,
          title: 'Ph�t hi?n luu � v? lung',
          items: [
            'H? th?ng s? tr�nh deadlift n?ng, good morning v� b�i g?p lung s�u.',
            'S? uu ti�n row c� h? tr? ng?c, plank, bird dog v� b�i si?t core an to�n.'
          ]
        },
        {
          pattern: /dau vai|kh?p vai|vai/iu,
          title: 'Ph�t hi?n luu � v? vai',
          items: [
            'H? th?ng s? gi?m overhead press n?ng v� d?ng t�c dang tay qu� r?ng.',
            'S? uu ti�n incline press nh?, face pull v� lateral raise m?c nh?.'
          ]
        },
        {
          pattern: /tim m?ch|huy?t �p|cao huy?t �p|ti?n d�nh/iu,
          title: 'Ph�t hi?n luu � tim m?ch/huy?t �p',
          items: [
            'H? th?ng s? b? HIIT qu� g?t v� tang th?i gian ngh? gi?a hi?p.',
            'S? uu ti�n cardio ?n d?nh ? m?c v?a v� nh?c theo d�i nh?p tim.'
          ]
        },
        {
          pattern: /m?i t?p|�t v?n d?ng|l�u kh�ng t?p/iu,
          title: 'Ph�t hi?n ngu?i m?i t?p',
          items: [
            'H? th?ng s? gi?m cu?ng d? ban d?u v� tang th?i gian ngh?.',
            'L?ch t?p s? uu ti�n h?c k? thu?t, tr�nh volume qu� cao.'
          ]
        },
        {
          pattern: /th?a c�n|b�o|gi?m m?/iu,
          title: 'Ph�t hi?n nhu c?u ki?m so�t c�n n?ng',
          items: [
            'H? th?ng s? tang cardio an to�n hon trong c�c bu?i ph� h?p.',
            'S? uu ti�n di b? d?c nh? ho?c xe d?p thay v� b�i b?t nh?y n?ng.'
          ]
        }
      ];

      function renderPreview() {
        const value = noteInput.value.trim();

        if (!value) {
          previewBox.classList.add('d-none');
          previewBox.innerHTML = '';
          return;
        }

        const matched = rules.filter(rule => rule.pattern.test(value));

        if (matched.length === 0) {
          previewBox.classList.remove('d-none', 'alert-warning');
          previewBox.classList.add('alert-info');
          previewBox.innerHTML = '<strong>Kh�ng ph�t hi?n t? kh�a s?c kh?e d?c bi?t.</strong><div class="mt-1">H? th?ng v?n s? dua to�n b? ghi ch� n�y v�o prompt d? Gemini v� fallback c�n nh?c khi t?o l?ch t?p.</div>';
          return;
        }

        const html = matched.map(rule => {
          const items = rule.items.map(item => `<li>${item}</li>`).join('');
          return `<div class="mb-2"><strong>${rule.title}</strong><ul>${items}</ul></div>`;
        }).join('');

        previewBox.classList.remove('d-none', 'alert-info');
        previewBox.classList.add('alert-warning');
        previewBox.innerHTML = html;
      }

      noteInput.addEventListener('input', renderPreview);
      renderPreview();
    })();
  </script>
  <script src="../js/main.js"></script>
</body>

</html>

