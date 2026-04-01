<?php
$page_title = "Kế hoạch dinh dưỡng";
include __DIR__ . '/includes/auth-check.php';

$base_path = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

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
        amp.id,
        amp.member_id,
        amp.goal,
        amp.body_type,
        amp.meals_per_day,
        amp.health_note,
        amp.ai_response,
        amp.status,
        amp.created_at,
        m.full_name,
        m.phone
    FROM ai_meal_plans amp
    INNER JOIN members m ON amp.member_id = m.id
";

if ($selected_member_id > 0) {
    $sqlPlans .= " WHERE amp.member_id = " . (int)$selected_member_id;
}

$sqlPlans .= " ORDER BY amp.id DESC LIMIT 10";

$resultPlans = $conn->query($sqlPlans);
if ($resultPlans && $resultPlans->num_rows > 0) {
    while ($row = $resultPlans->fetch_assoc()) {
        $plans[] = $row;
    }
}

$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success_message = 'Đã tạo kế hoạch dinh dưỡng thành công.';
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
    <title>Kế hoạch dinh dưỡng - Gym Management</title>
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
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="d-flex dashboard-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
            <?php include __DIR__ . '/includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                <?php endif; ?>

                <?php if ($selected_member): ?>
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <div>
                            Đang xem kế hoạch dinh dưỡng của:
                            <strong><?php echo h($selected_member['full_name']); ?></strong>
                            - <?php echo h($selected_member['phone']); ?>
                        </div>
                        <a href="meal-plans.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Tạo kế hoạch dinh dưỡng</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <form method="POST" action="<?php echo $base_path; ?>php/ai/meal-plans.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Hội viên</label>
                                        <select name="member_id" class="form-select" required>
                                            <option value="">-- Chọn hội viên --</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo (int)$member['id']; ?>" <?php echo ($selected_member_id === (int)$member['id']) ? 'selected' : ''; ?>>
                                                    <?php echo h($member['full_name']); ?> - <?php echo h($member['phone']); ?>
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
                                            <option value="weight-gain">Tăng cân</option>
                                            <option value="maintain">Giữ dáng</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Thể trạng</label>
                                        <select name="body_type" class="form-select" required>
                                            <option value="">-- Chọn thể trạng --</option>
                                            <option value="thin">Gầy</option>
                                            <option value="normal">Bình thường</option>
                                            <option value="overweight">Thừa cân</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Số bữa/ngày</label>
                                        <select name="meals_per_day" class="form-select" required>
                                            <option value="">-- Chọn số bữa --</option>
                                            <option value="3">3 bữa</option>
                                            <option value="4">4 bữa</option>
                                            <option value="5">5 bữa</option>
                                            <option value="6">6 bữa</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Lưu ý sức khỏe / ăn uống</label>
                                        <textarea name="health_note" class="form-control" rows="3" placeholder="Ví dụ: dị ứng hải sản, đau dạ dày..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-stars me-1"></i>Tạo kế hoạch
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Kế hoạch dinh dưỡng gần đây</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($plans)): ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($plans as $plan): ?>
                                            <div class="plan-box">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="fw-semibold"><?php echo h($plan['full_name']); ?></div>
                                                        <div class="text-muted small">
                                                            <?php echo h(getMealGoalLabel($plan['goal'])); ?> •
                                                            <?php echo h(getBodyTypeLabel($plan['body_type'])); ?> •
                                                            <?php echo (int)$plan['meals_per_day']; ?> bữa/ngày
                                                        </div>
                                                    </div>
                                                    <span class="badge text-bg-light"><?php echo h($plan['created_at']); ?></span>
                                                </div>
                                                <div class="small"><?php echo nl2br(h($plan['ai_response'])); ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div class="small text-muted">
                                                        Tạo lúc: <?php echo h($plan['created_at']); ?>
                                                    </div>
                                                    <a href="<?php echo $base_path; ?>php/ai/edit-meal-plan.php?id=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="bi bi-pencil-square me-1"></i>Chỉnh sửa
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Chưa có kế hoạch dinh dưỡng nào.</div>
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
