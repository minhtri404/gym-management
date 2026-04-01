<?php
$page_title = "Chỉnh sửa kế hoạch tập luyện";
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . $base_path . "workout-plans.php");
    exit();
}

$success_message = '';
$error_message = '';

$stmt = $conn->prepare("
    SELECT 
        awp.*,
        m.full_name,
        m.phone
    FROM ai_workout_plans awp
    INNER JOIN members m ON awp.member_id = m.id
    WHERE awp.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$plan = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$plan) {
    header("Location: " . $base_path . "workout-plans.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        $csrf_token === '' ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error_message = 'CSRF token không hợp lệ.';
    } else {
        $goal = trim($_POST['goal'] ?? '');
        $level = trim($_POST['level'] ?? '');
        $days_per_week = (int)($_POST['days_per_week'] ?? 0);
        $health_note = trim($_POST['health_note'] ?? '');
        $ai_response = trim($_POST['ai_response'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if ($goal === '' || $level === '' || $days_per_week <= 0 || $ai_response === '') {
            $error_message = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            $stmt_update = $conn->prepare("
                UPDATE ai_workout_plans
                SET 
                    goal = ?,
                    level = ?,
                    days_per_week = ?,
                    health_note = ?,
                    ai_response = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt_update->bind_param(
                "ssisssi",
                $goal,
                $level,
                $days_per_week,
                $health_note,
                $ai_response,
                $status,
                $id
            );

            if ($stmt_update->execute()) {
                $success_message = 'Đã cập nhật kế hoạch tập luyện.';
            } else {
                $error_message = 'Không thể cập nhật kế hoạch.';
            }

            $stmt_update->close();

            $stmt_reload = $conn->prepare("
                SELECT 
                    awp.*,
                    m.full_name,
                    m.phone
                FROM ai_workout_plans awp
                INNER JOIN members m ON awp.member_id = m.id
                WHERE awp.id = ?
                LIMIT 1
            ");
            $stmt_reload->bind_param("i", $id);
            $stmt_reload->execute();
            $result_reload = $stmt_reload->get_result();
            $plan = $result_reload ? $result_reload->fetch_assoc() : $plan;
            $stmt_reload->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa kế hoạch tập luyện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <div class="main-content flex-grow-1">
            <?php include __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0">Chỉnh sửa kế hoạch tập luyện</h2>
                    <a href="<?php echo $base_path; ?>workout-plans.php?member_id=<?php echo (int)$plan['member_id']; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            Hội viên: <?php echo h($plan['full_name']); ?> - <?php echo h($plan['phone']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message !== ''): ?>
                            <div class="alert alert-success"><?php echo h($success_message); ?></div>
                        <?php endif; ?>

                        <?php if ($error_message !== ''): ?>
                            <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Mục tiêu</label>
                                    <select name="goal" class="form-select" required>
                                        <option value="weight-loss" <?php echo ($plan['goal'] === 'weight-loss') ? 'selected' : ''; ?>>Giảm cân</option>
                                        <option value="muscle-gain" <?php echo ($plan['goal'] === 'muscle-gain') ? 'selected' : ''; ?>>Tăng cơ</option>
                                        <option value="maintain" <?php echo ($plan['goal'] === 'maintain') ? 'selected' : ''; ?>>Giữ dáng</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Trình độ</label>
                                    <select name="level" class="form-select" required>
                                        <option value="beginner" <?php echo ($plan['level'] === 'beginner') ? 'selected' : ''; ?>>Mới bắt đầu</option>
                                        <option value="intermediate" <?php echo ($plan['level'] === 'intermediate') ? 'selected' : ''; ?>>Trung bình</option>
                                        <option value="advanced" <?php echo ($plan['level'] === 'advanced') ? 'selected' : ''; ?>>Nâng cao</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Số buổi / tuần</label>
                                    <select name="days_per_week" class="form-select" required>
                                        <option value="3" <?php echo ((int)$plan['days_per_week'] === 3) ? 'selected' : ''; ?>>3 buổi</option>
                                        <option value="4" <?php echo ((int)$plan['days_per_week'] === 4) ? 'selected' : ''; ?>>4 buổi</option>
                                        <option value="5" <?php echo ((int)$plan['days_per_week'] === 5) ? 'selected' : ''; ?>>5 buổi</option>
                                        <option value="6" <?php echo ((int)$plan['days_per_week'] === 6) ? 'selected' : ''; ?>>6 buổi</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo ($plan['status'] === 'active') ? 'selected' : ''; ?>>active</option>
                                        <option value="inactive" <?php echo ($plan['status'] === 'inactive') ? 'selected' : ''; ?>>inactive</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Ghi chú sức khỏe / lưu ý</label>
                                    <textarea name="health_note" class="form-control" rows="3"><?php echo h($plan['health_note']); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Nội dung kế hoạch tập luyện</label>
                                    <textarea name="ai_response" class="form-control" rows="16" required><?php echo h($plan['ai_response']); ?></textarea>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Lưu thay đổi
                                </button>

                                <a href="<?php echo $base_path; ?>workout-plans.php?member_id=<?php echo (int)$plan['member_id']; ?>" class="btn btn-outline-secondary">
                                    Hủy
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>