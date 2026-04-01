<?php
$page_title = "Kế hoạch tập luyện";
include __DIR__ . '/includes/auth-check.php';

$base_path = '';

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

$selected_member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$selected_member = null;
$members = [];
$plans = [];

$resultMembers = $conn->query("SELECT id, full_name, phone, status FROM members ORDER BY id DESC");
if ($resultMembers && $resultMembers->num_rows > 0) {
    while ($row = $resultMembers->fetch_assoc()) {
        $members[] = $row;

        if ($selected_member_id > 0 && (int)$row['id'] === $selected_member_id) {
            $selected_member = $row;
        }
    }
}

$sqlPlans = "
    SELECT 
        awp.id,
        awp.member_id,
        awp.goal,
        awp.level,
        awp.days_per_week,
        awp.health_note,
        awp.ai_response,
        awp.status,
        awp.created_at,
        m.full_name,
        m.phone
    FROM ai_workout_plans awp
    INNER JOIN members m ON awp.member_id = m.id
";

if ($selected_member_id > 0) {
    $sqlPlans .= " WHERE awp.member_id = " . (int)$selected_member_id;
}

$sqlPlans .= " ORDER BY awp.id DESC LIMIT 10";

$resultPlans = $conn->query($sqlPlans);
if ($resultPlans && $resultPlans->num_rows > 0) {
    while ($row = $resultPlans->fetch_assoc()) {
        $plans[] = $row;
    }
}

$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success_message = 'Đã tạo kế hoạch tập luyện thành công.';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'missing_fields') {
        $error_message = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif ($_GET['error'] === 'member_not_found') {
        $error_message = 'Không tìm thấy hội viên.';
    } elseif ($_GET['error'] === 'gemini_key_missing') {
        $error_message = 'Chưa cấu hình GEMINI_API_KEY.';
    } elseif ($_GET['error'] === 'save_failed') {
        $error_message = 'Tạo được kế hoạch nhưng lưu database thất bại.';
    } else {
        $error_message = 'Đã xảy ra lỗi khi tạo kế hoạch.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kế hoạch tập luyện - Gym Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .plan-box {
            white-space: pre-line;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e9ecef;
            max-height: 260px;
            overflow-y: auto;
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
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Tạo kế hoạch tập luyện bằng AI</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($success_message !== ''): ?>
                                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                                <?php endif; ?>

                                <?php if ($error_message !== ''): ?>
                                    <div class="alert alert-warning"><?php echo h($error_message); ?></div>
                                <?php endif; ?>

                                <form method="POST" action="<?php echo $base_path; ?>php/ai/create-workout-plan.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Hội viên</label>
                                        <select name="member_id" class="form-select" required>
                                            <option value="">-- Chọn hội viên --</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo (int)$member['id']; ?>" <?php echo ($selected_member_id === (int)$member['id']) ? 'selected' : ''; ?>>
                                                    <?php echo h($member['full_name']); ?> - <?php echo h($member['phone']); ?> (<?php echo h($member['status']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Mục tiêu</label>
                                        <select name="goal" class="form-select" required>
                                            <option value="">-- Chọn mục tiêu --</option>
                                            <option value="weight-loss">Giảm cân</option>
                                            <option value="muscle-gain">Tăng cơ</option>
                                            <option value="maintain">Giữ dáng</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Trình độ</label>
                                        <select name="level" class="form-select" required>
                                            <option value="">-- Chọn trình độ --</option>
                                            <option value="beginner">Mới bắt đầu</option>
                                            <option value="intermediate">Trung bình</option>
                                            <option value="advanced">Nâng cao</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Số buổi / tuần</label>
                                        <select name="days_per_week" class="form-select" required>
                                            <option value="">-- Chọn số buổi --</option>
                                            <option value="3">3 buổi</option>
                                            <option value="4">4 buổi</option>
                                            <option value="5">5 buổi</option>
                                            <option value="6">6 buổi</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Ghi chú sức khỏe / lưu ý</label>
                                        <textarea name="health_note" class="form-control" rows="3" placeholder="Ví dụ: đau gối nhẹ, mới quay lại tập, tránh squat nặng"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-stars me-1"></i>Tạo kế hoạch
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Danh sách kế hoạch tập luyện</h5>
                                <?php if ($selected_member_id > 0): ?>
                                    <a href="workout-plans.php" class="btn btn-sm btn-outline-secondary">Bỏ lọc hội viên</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($plans)): ?>
                                    <div class="text-muted">Chưa có kế hoạch tập luyện nào.</div>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($plans as $plan): ?>
                                            <div class="border rounded p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-semibold"><?php echo h($plan['full_name']); ?> - <?php echo h($plan['phone']); ?></div>
                                                        <div class="small text-muted">
                                                            <?php echo h(getGoalLabel($plan['goal'])); ?> |
                                                            <?php echo h(getLevelLabel($plan['level'])); ?> |
                                                            <?php echo (int)$plan['days_per_week']; ?> buổi/tuần
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-success"><?php echo h($plan['status']); ?></span>
                                                </div>

                                                <?php if (!empty($plan['health_note'])): ?>
                                                    <div class="small text-muted mb-2">
                                                        <strong>Lưu ý:</strong> <?php echo h($plan['health_note']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="plan-box"><?php echo nl2br(h($plan['ai_response'])); ?></div>

                                                <div class="small text-muted mt-2">
                                                    Tạo lúc: <?php echo h($plan['created_at']); ?>
                                                </div>
                                                <a href="<?php echo $base_path; ?>php/ai/edit-workout-plan.php?id=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-outline-warning">
        <i class="bi bi-pencil-square me-1"></i>Chỉnh sửa
    </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 