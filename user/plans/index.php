<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/plan-functions.php';
if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$base_path = '../../';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user = null;
$member = null;
$workout_plans = [];
$meal_plans = [];
$error = '';
$show_plans = isset($_GET['show']) && $_GET['show'] === '1';

/**
 * Format AI response đẹp hơn:
 * - xuống dòng theo đoạn
 * - đổi dòng bắt đầu bằng "-" hoặc "*" thành bullet nhẹ
 */


/**
 * Lấy user đang đăng nhập
 */
$user = getUserById($conn, $user_id);

if (!$user) {
    $error = 'Không tìm thấy tài khoản người dùng.';
} else {
    /**
     * Dò sang bảng members bằng phone hoặc email
     */
    $email = trim((string) ($user['email'] ?? ''));
    $phone = trim((string) ($user['phone'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email, package_id, start_date, end_date, status
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
$member = findMemberByUserContact($conn, $phone, $email);

    if ($show_plans && $member) {
        $member_id = (int) $member['id'];

  $workout_plans = getWorkoutPlansByMemberId($conn, $member_id);

$meal_plans = getMealPlansByMemberId($conn, $member_id);
}
}
$has_workout_plan = !empty($workout_plans);
$has_meal_plan = !empty($meal_plans);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kế hoạch của bạn</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <style>
        .plans-hero {
            background: linear-gradient(135deg, #f8f9fa 0%, #eef3ff 100%);
        }

        .plans-summary-card,
        .plan-card,
        .empty-plan-card {
            border: 1px solid #e9ecef;
            border-radius: 20px;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.05);
        }

        .plan-card .plan-meta {
            font-size: 0.95rem;
            color: #6c757d;
        }

        .plan-response-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            padding: 1rem;
            line-height: 1.7;
        }

        .health-note-banner {
            background: #fff8e1;
            border: 1px solid #ffe08a;
            color: #5f4300;
            border-radius: 14px;
            padding: 0.9rem 1rem;
        }

        .ai-plan-paragraph {
            margin-bottom: 0.8rem;
            color: #0f172a;
        }

        .ai-plan-list {
            padding-left: 1.2rem;
            margin-bottom: 0.8rem;
        }

        .ai-plan-list li {
            margin-bottom: 0.45rem;
            color: #0f172a;
        }

        /* Make hero and summary text darker for readability */
        .plans-hero h1 {
            color: #0f172a;
        }

        .plans-hero p {
            color: #475569;
        }

        .plans-summary-card,
        .plan-card,
        .empty-plan-card,
        .plan-response-box {
            color: #0f172a;
        }

        .plans-summary-card h3,
        .plans-summary-card h4,
        .plan-card h4,
        .plan-card h5 {
            color: #0f172a;
        }

        .plan-meta {
            color: #475569;
        }
.workout-day-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
}

.workout-day-card {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 0.4rem 0.9rem rgba(0, 0, 0, 0.04);
}

.workout-day-title {
    background: linear-gradient(135deg, #0d6efd 0%, #3d8bfd 100%);
    color: #fff;
    font-weight: 700;
    padding: 0.9rem 1rem;
    font-size: 1rem;
}

.workout-day-content {
    padding: 1rem;
    background: #fff;
}

.workout-day-content .ai-plan-paragraph:last-child,
.workout-day-content .ai-plan-list:last-child {
    margin-bottom: 0;
}

@media (min-width: 768px) {
    .workout-day-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
        @media print {
            body * {
                visibility: hidden;
            }

            #printWorkoutArea,
            #printWorkoutArea * {
                visibility: visible;
            }

            #printWorkoutArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #fff;
                padding: 20px;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="plans-hero py-5 border-bottom">
    <div class="container" style="margin-top: 80px;">
        <div class="text-center">
            <h1 class="fw-bold mb-3">Lịch tập & dinh dưỡng của bạn</h1>
            <p class="text-muted mb-0">
                Xem nhanh kế hoạch AI đã được tạo cho tài khoản của bạn.
            </p>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="plans-summary-card bg-white p-4 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-8">
                        <h3 class="fw-bold mb-3">Lịch của bạn</h3>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div><strong>Họ tên:</strong> <?php echo htmlspecialchars($user['full_name'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($user['phone'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Hội viên:</strong>
                                    <?php echo $member ? '<span class="text-success">Đã liên kết</span>' : '<span class="text-danger">Chưa liên kết</span>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <?php if ($member): ?>
                            <a href="?show=1" class="btn btn-primary px-4">
                                <i class="bi bi-eye me-1"></i> Hiển thị
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary px-4" disabled>
                                Không có dữ liệu hội viên
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_plans && $member): ?>
            <div class="plans-summary-card bg-white p-4 mb-4">
                <h4 class="fw-bold mb-3">Thông tin hội viên</h4>
                <div class="row g-3">
                    <div class="col-md-6"><strong>Họ tên:</strong> <?php echo htmlspecialchars($member['full_name'] ?? ''); ?></div>
                    <div class="col-md-6"><strong>Điện thoại:</strong> <?php echo htmlspecialchars($member['phone'] ?? ''); ?></div>
                    <div class="col-md-6"><strong>Email:</strong> <?php echo htmlspecialchars($member['email'] ?? ''); ?></div>
                    <div class="col-md-6"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($member['status'] ?? ''); ?></div>
                    <div class="col-md-6"><strong>Ngày bắt đầu:</strong> <?php echo htmlspecialchars($member['start_date'] ?? ''); ?></div>
                    <div class="col-md-6"><strong>Ngày kết thúc:</strong> <?php echo htmlspecialchars($member['end_date'] ?? ''); ?></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4 no-print">
                <?php if ($has_workout_plan): ?>
                    <button type="button" class="btn btn-dark" onclick="window.print();">
                        <i class="bi bi-printer me-1"></i> In lịch tập
                    </button>
                <?php endif; ?>

                <?php if (!$has_workout_plan): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Không có lịch tập để in
                    </button>
                <?php endif; ?>
            </div>

            <ul class="nav nav-tabs mb-4 no-print" id="planTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="workout-tab" data-bs-toggle="tab" data-bs-target="#workout-pane" type="button" role="tab">
                        Lịch tập của bạn
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="meal-tab" data-bs-toggle="tab" data-bs-target="#meal-pane" type="button" role="tab">
                        Dinh dưỡng của bạn
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="planTabsContent">
                <div class="tab-pane fade show active" id="workout-pane" role="tabpanel">
                    <div id="printWorkoutArea">
                        <div class="d-none d-print-block mb-4">
                            <h2 class="fw-bold">Lịch tập của bạn</h2>
                            <p>Hội viên: <?php echo htmlspecialchars($member['full_name'] ?? ''); ?></p>
                        </div>

                        <?php if ($has_workout_plan): ?>
                            <?php foreach ($workout_plans as $index => $plan): ?>
                                <div class="plan-card bg-white p-4 mb-4">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                        <div>
                                            <h4 class="fw-bold mb-1">Kế hoạch tập #<?php echo $index + 1; ?></h4>
                                            <div class="plan-meta">
                                                Ngày tạo: <?php echo htmlspecialchars($plan['created_at'] ?? ''); ?>
                                            </div>
                                        </div>
                                        <div><?php echo planStatusBadge((string) ($plan['status'] ?? '')); ?></div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <strong>Mục tiêu:</strong>
                                            <?php echo htmlspecialchars($plan['goal'] ?? ''); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Cấp độ:</strong>
                                            <?php echo htmlspecialchars($plan['level'] ?? ''); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Số ngày / tuần:</strong>
                                            <?php echo (int) ($plan['days_per_week'] ?? 0); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Ghi chú sức khỏe:</strong>
                                            <?php echo htmlspecialchars($plan['health_note'] ?: 'Không có'); ?>
                                        </div>
                                    </div>

                                    <div class="plan-response-box">
                                        <h5 class="fw-bold mb-3">Nội dung lịch tập</h5>
                                        <?php echo renderWorkoutPlanByDay((string) ($plan['ai_response'] ?? '')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-plan-card bg-white p-4">
                                <div class="alert alert-warning mb-0">
                                    Gói của bạn hiện chưa có kế hoạch tập để hiển thị hoặc in.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="meal-pane" role="tabpanel">
                    <?php if ($has_meal_plan): ?>
                        <?php foreach ($meal_plans as $index => $plan): ?>
                            <div class="plan-card bg-white p-4 mb-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <h4 class="fw-bold mb-1">Kế hoạch dinh dưỡng #<?php echo $index + 1; ?></h4>
                                        <div class="plan-meta">
                                            Ngày tạo: <?php echo htmlspecialchars($plan['created_at'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div><?php echo planStatusBadge((string) ($plan['status'] ?? '')); ?></div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <strong>Mục tiêu:</strong>
                                        <?php echo htmlspecialchars($plan['goal'] ?? ''); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Dáng người:</strong>
                                        <?php echo htmlspecialchars($plan['body_type'] ?: 'Không có'); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Số bữa / ngày:</strong>
                                        <?php echo (int) ($plan['meals_per_day'] ?? 0); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Ghi chú sức khỏe:</strong>
                                        <?php echo htmlspecialchars($plan['health_note'] ?: 'Không có'); ?>
                                    </div>
                                </div>

                                <div class="plan-response-box">
                                    <h5 class="fw-bold mb-3">Nội dung kế hoạch dinh dưỡng</h5>
                                    <?php echo formatAiResponse((string) ($plan['ai_response'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-plan-card bg-white p-4">
                            <div class="alert alert-secondary mb-0">
                                Bạn chưa có kế hoạch dinh dưỡng AI.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
