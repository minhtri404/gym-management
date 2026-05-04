<?php
$page_title = "K? ho?ch dinh du?ng";
include __DIR__ . '/../includes/auth-check.php';

$base_path = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

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
    $success_message = 'Ðã t?o k? ho?ch dinh du?ng thành công.';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'missing_fields') {
        $error_message = 'Vui lòng nh?p d?y d? thông tin.';
    } elseif ($_GET['error'] === 'member_not_found') {
        $error_message = 'Không tìm th?y h?i viên.';
    } elseif ($_GET['error'] === 'gemini_key_missing') {
        $error_message = 'Chua c?u hình GEMINI_API_KEY.';
    } elseif ($_GET['error'] === 'save_failed') {
        $error_message = 'T?o du?c k? ho?ch nhung luu database th?t b?i.';
    } else {
        $error_message = 'Ðã x?y ra l?i khi t?o k? ho?ch.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K? ho?ch dinh du?ng - Gym Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
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
                            Ðang xem k? ho?ch dinh du?ng c?a:
                            <strong><?php echo h($selected_member['full_name']); ?></strong>
                            - <?php echo h($selected_member['phone']); ?>
                        </div>
                        <a href="meal-plans.php" class="btn btn-sm btn-outline-secondary">Xem t?t c?</a>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">T?o k? ho?ch dinh du?ng</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <form method="POST" action="<?php echo $base_path; ?>php/ai/meal-plans.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">H?i viên</label>
                                        <select name="member_id" class="form-select" required>
                                            <option value="">-- Ch?n h?i viên --</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo (int)$member['id']; ?>" <?php echo ($selected_member_id === (int)$member['id']) ? 'selected' : ''; ?>>
                                                    <?php echo h($member['full_name']); ?> - <?php echo h($member['phone']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">M?c tiêu</label>
                                        <select name="goal" class="form-select" required>
                                            <option value="">-- Ch?n m?c tiêu --</option>
                                            <option value="weight-loss">Gi?m cân</option>
                                            <option value="muscle-gain">Tang co</option>
                                            <option value="weight-gain">Tang cân</option>
                                            <option value="maintain">Gi? dáng</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Th? tr?ng</label>
                                        <select name="body_type" class="form-select" required>
                                            <option value="">-- Ch?n th? tr?ng --</option>
                                            <option value="thin">G?y</option>
                                            <option value="normal">Bình thu?ng</option>
                                            <option value="overweight">Th?a cân</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">S? b?a/ngày</label>
                                        <select name="meals_per_day" class="form-select" required>
                                            <option value="">-- Ch?n s? b?a --</option>
                                            <option value="3">3 b?a</option>
                                            <option value="4">4 b?a</option>
                                            <option value="5">5 b?a</option>
                                            <option value="6">6 b?a</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Luu ý s?c kh?e / an u?ng</label>
                                        <textarea name="health_note" class="form-control" rows="3" placeholder="Ví d?: d? ?ng h?i s?n, dau d? dày..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-stars me-1"></i>T?o k? ho?ch
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">K? ho?ch dinh du?ng g?n dây</h5>
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
                                                            <?php echo (int)$plan['meals_per_day']; ?> b?a/ngày
                                                        </div>
                                                    </div>
                                                    <span class="badge text-bg-light"><?php echo h($plan['created_at']); ?></span>
                                                </div>
                                                <div class="small"><?php echo nl2br(h($plan['ai_response'])); ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div class="small text-muted">
                                                        T?o lúc: <?php echo h($plan['created_at']); ?>
                                                    </div>
                                                    <a href="<?php echo $base_path; ?>php/ai/edit-meal-plan.php?id=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="bi bi-pencil-square me-1"></i>Ch?nh s?a
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Chua có k? ho?ch dinh du?ng nào.</div>
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

