<?php
$page_title = "AI gợi ý lịch tập";
include __DIR__ . '/includes/auth-check.php';
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

function buildFallbackPlan($goal, $level, $days_per_week, $notes = '')
{
  $goal_label = getGoalLabel($goal);
  $level_label = getLevelLabel($level);

  $days = [];
  if ($goal === 'weight-loss') {
    if ($days_per_week == 3) {
      $days = [
        "Buổi 1: Cardio 20 phút + Full Body cơ bản",
        "Buổi 2: Chân + Bụng + đi bộ dốc",
        "Buổi 3: Lưng + Vai + HIIT nhẹ"
      ];
    } elseif ($days_per_week == 4) {
      $days = [
        "Buổi 1: Cardio + Ngực + Tay sau",
        "Buổi 2: Chân + Bụng",
        "Buổi 3: Lưng + Tay trước + cardio nhẹ",
        "Buổi 4: Vai + HIIT nhẹ"
      ];
    } else {
      $days = [
        "Buổi 1: Ngực + cardio",
        "Buổi 2: Chân",
        "Buổi 3: Lưng + bụng",
        "Buổi 4: Vai + tay",
        "Buổi 5: HIIT + full body"
      ];
    }
  } elseif ($goal === 'muscle-gain') {
    if ($days_per_week == 3) {
      $days = [
        "Buổi 1: Ngực + Tay sau",
        "Buổi 2: Lưng + Tay trước",
        "Buổi 3: Chân + Vai"
      ];
    } elseif ($days_per_week == 4) {
      $days = [
        "Buổi 1: Ngực + Tay sau",
        "Buổi 2: Lưng + Tay trước",
        "Buổi 3: Chân",
        "Buổi 4: Vai + Bụng"
      ];
    } else {
      $days = [
        "Buổi 1: Ngực",
        "Buổi 2: Lưng",
        "Buổi 3: Chân",
        "Buổi 4: Vai",
        "Buổi 5: Tay + bụng"
      ];
    }
  } else {
    if ($days_per_week == 3) {
      $days = [
        "Buổi 1: Full Body",
        "Buổi 2: Cardio + Core",
        "Buổi 3: Full Body + giãn cơ"
      ];
    } elseif ($days_per_week == 4) {
      $days = [
        "Buổi 1: Thân trên",
        "Buổi 2: Thân dưới",
        "Buổi 3: Cardio + Bụng",
        "Buổi 4: Full Body nhẹ"
      ];
    } else {
      $days = [
        "Buổi 1: Ngực + Tay",
        "Buổi 2: Chân",
        "Buổi 3: Lưng + Vai",
        "Buổi 4: Cardio + Bụng",
        "Buổi 5: Full Body nhẹ"
      ];
    }
  }

  $text = "Kế hoạch tập luyện - {$goal_label}\n";
  $text .= "Trình độ: {$level_label}\n";
  $text .= "Số buổi/tuần: {$days_per_week}\n\n";
  foreach ($days as $item) {
    $text .= "- {$item}\n";
  }

  $text .= "\nLưu ý:\n";
  if ($level === 'beginner') {
    $text .= "- Ưu tiên đúng kỹ thuật, cường độ nhẹ đến vừa.\n";
    $text .= "- Nghỉ 60-90 giây giữa các hiệp.\n";
  } elseif ($level === 'intermediate') {
    $text .= "- Tăng dần mức tạ theo tuần.\n";
    $text .= "- Kết hợp cardio 2-3 buổi nếu cần.\n";
  } else {
    $text .= "- Có thể áp dụng progressive overload.\n";
    $text .= "- Theo dõi recovery và ngủ nghỉ đầy đủ.\n";
  }

  if (!empty($notes)) {
    $text .= "- Ghi chú thêm: {$notes}\n";
  }

  return [
    'title' => "Kế hoạch {$goal_label}",
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

  $prompt = "H?y t?o k? ho?ch t?p luy?n cho h?i vi?n t?n {$memberName}.\n"
    . "M?c ti?u: {$goalLabel}.\n"
    . "Tr?nh ??: {$levelLabel}.\n"
    . "S? bu?i/tu?n: {$daysPerWeek}.\n"
    . "Ghi ch?: {$notes}.\n"
    . "Tr? v? JSON g?m title, level, days (array), note.\n"
    . "?u ti?n an to?n, d? ?p d?ng, kh?ng qu? d?i d?ng.";

  $payload = [
    'contents' => [
      [
        'parts' => [
          ['text' => "B?n l? tr? l? hu?n luy?n vi?n gym. Tr? v? JSON h?p l? ??ng schema, kh?ng th?m k? t? th?a."],
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
  <link rel="stylesheet" href="css/style.css">
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

  <script src="js/main.js"></script>
</body>

</html>
