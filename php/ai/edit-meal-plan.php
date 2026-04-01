<?php
$page_title = "Chỉnh sửa kế hoạch dinh dưỡng";
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . $base_path . "meal-plans.php");
    exit();
}

$success_message = '';
$error_message = '';

$stmt = $conn->prepare("
    SELECT 
        amp.*,
        m.full_name,
        m.phone
    FROM ai_meal_plans amp
    INNER JOIN members m ON amp.member_id = m.id
    WHERE amp.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$plan = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$plan) {
    header("Location: " . $base_path . "meal-plans.php");
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
        $body_type = trim($_POST['body_type'] ?? '');
        $meals_per_day = (int)($_POST['meals_per_day'] ?? 0);
        $health_note = trim($_POST['health_note'] ?? '');
        $ai_response = trim($_POST['ai_response'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if ($goal === '' || $body_type === '' || $meals_per_day <= 0 || $ai_response === '') {
            $error_message = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            $stmt_update = $conn->prepare("
                UPDATE ai_meal_plans
                SET 
                    goal = ?,
                    body_type = ?,
                    meals_per_day = ?,
                    health_note = ?,
                    ai_response = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt_update->bind_param(
                "ssisssi",
                $goal,
                $body_type,
                $meals_per_day,
                $health_note,
                $ai_response,
                $status,
                $id
            );

            if ($stmt_update->execute()) {
                $success_message = 'Đã cập nhật kế hoạch dinh dưỡng.';
            } else {
                $error_message = 'Không thể cập nhật kế hoạch dinh dưỡng.';
            }

            $stmt_update->close();

            $stmt_reload = $conn->prepare("
                SELECT 
                    amp.*,
                    m.full_name,
                    m.phone
                FROM ai_meal_plans amp
                INNER JOIN members m ON amp.member_id = m.id
                WHERE amp.id = ?
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
    <title>Chỉnh sửa kế hoạch dinh dưỡng</title>
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
                    <h2 class="fw-bold mb-0">Chỉnh sửa kế hoạch dinh dưỡng</h2>
                    <a href="<?php echo $base_path; ?>meal-plans.php?member_id=<?php echo (int)$plan['member_id']; ?>" class="btn btn-secondary">
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
                                        <option value="weight-gain" <?php echo ($plan['goal'] === 'weight-gain') ? 'selected' : ''; ?>>Tăng cân</option>
                                        <option value="maintain" <?php echo ($plan['goal'] === 'maintain') ? 'selected' : ''; ?>>Giữ dáng</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Thể trạng</label>
                                    <select name="body_type" class="form-select" required>
                                        <option value="thin" <?php echo ($plan['body_type'] === 'thin') ? 'selected' : ''; ?>>Gầy</option>
                                        <option value="normal" <?php echo ($plan['body_type'] === 'normal') ? 'selected' : ''; ?>>Bình thường</option>
                                        <option value="overweight" <?php echo ($plan['body_type'] === 'overweight') ? 'selected' : ''; ?>>Thừa cân</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Số bữa / ngày</label>
                                    <select name="meals_per_day" class="form-select" required>
                                        <option value="3" <?php echo ((int)$plan['meals_per_day'] === 3) ? 'selected' : ''; ?>>3 bữa</option>
                                        <option value="4" <?php echo ((int)$plan['meals_per_day'] === 4) ? 'selected' : ''; ?>>4 bữa</option>
                                        <option value="5" <?php echo ((int)$plan['meals_per_day'] === 5) ? 'selected' : ''; ?>>5 bữa</option>
                                        <option value="6" <?php echo ((int)$plan['meals_per_day'] === 6) ? 'selected' : ''; ?>>6 bữa</option>
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
                                    <label class="form-label">Lưu ý sức khỏe / ăn uống</label>
                                    <textarea name="health_note" class="form-control" rows="3"><?php echo h($plan['health_note']); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Nội dung kế hoạch dinh dưỡng</label>
                                    <textarea name="ai_response" class="form-control" rows="16" required><?php echo h($plan['ai_response']); ?></textarea>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Lưu thay đổi
                                </button>

                                <a href="<?php echo $base_path; ?>meal-plans.php?member_id=<?php echo (int)$plan['member_id']; ?>" class="btn btn-outline-secondary">
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