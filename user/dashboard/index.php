<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$base_path = '../../';
$user_id = (int) $_SESSION['user_id'];

$user = null;
$member = null;
$package = null;

/* Lấy user */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone, avatar, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

/* Tìm member tương ứng qua phone/email */
if ($user) {
    $phone = trim((string)($user['phone'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email, package_id, start_date, end_date, status
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param("ss", $phone, $email);
    $stmt_member->execute();
    $result_member = $stmt_member->get_result();
    $member = $result_member->fetch_assoc();
    $stmt_member->close();

    if ($member && !empty($member['package_id'])) {
        $package_id = (int) $member['package_id'];

        $stmt_package = $conn->prepare("
            SELECT id, package_name, duration_months, price, short_description
            FROM packages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt_package->bind_param("i", $package_id);
        $stmt_package->execute();
        $result_package = $stmt_package->get_result();
        $package = $result_package->fetch_assoc();
        $stmt_package->close();
    }
}

$avatar_path = !empty($user['avatar'])
    ? $base_path . 'uploads/avatars/' . $user['avatar']
    : 'https://via.placeholder.com/100x100.png?text=U';

$days_left = null;
if ($member && !empty($member['end_date'])) {
    try {
        $today = new DateTime(date('Y-m-d'));
        $end_date = new DateTime($member['end_date']);
        $interval = $today->diff($end_date);
        $days_left = (int) $interval->format('%r%a');
    } catch (Throwable $e) {
        $days_left = null;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard người dùng</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">
    <style>
        .user-dashboard-hero {
            background: linear-gradient(180deg, #fbf8f2 0%, #f7f4eb 60%);
        }

        .dashboard-card {
            border: 1px solid rgba(15,23,42,0.04);
            border-radius: 20px;
            box-shadow: 0 6px 24px rgba(15,23,42,0.06);
            background: #fffaf2;
            height: 100%;
            color: #0f172a; /* make card text dark */
        }

        .dashboard-card h4,
        .dashboard-card h1,
        .dashboard-card strong,
        .dashboard-card p,
        .dashboard-card a,
        .dashboard-card .mb-2,
        .dashboard-card .mb-3 {
            color: #0f172a; /* ensure headings and paragraphs are dark */
        }

        .user-dashboard-hero h1 {
            color: #0f172a; /* darker hero title */
        }

        .user-dashboard-hero p {
            color: #475569; /* darker subtitle */
        }

        .dashboard-avatar {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255,209,153,0.4);
        }

        .dashboard-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(13,110,253,0.06);
            color: #0d6efd;
            font-size: 1.2rem;
        }

        .dashboard-link-btn {
            border-radius: 14px;
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="user-dashboard-hero py-5 border-bottom">
    <div class="container" style="margin-top: 80px;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" class="dashboard-avatar">
                    <div>
                        <h1 class="fw-bold mb-2">
                            Xin chào, <?php echo htmlspecialchars($user['full_name'] ?? 'Người dùng'); ?>
                        </h1>
                        <p class="text-muted mb-0">
                            Đây là khu vực tổng quan tài khoản của bạn.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 text-lg-end">
                <a href="<?php echo $base_path; ?>user/profile.php" class="btn btn-primary px-4 dashboard-link-btn">
                    <i class="bi bi-person-circle me-2"></i>Hồ sơ cá nhân
                </a>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">

            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card p-4">
                    <div class="dashboard-icon-box mb-3">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Gói của bạn</h4>

                    <?php if ($member && $package): ?>
                        <p class="mb-2"><strong>Tên gói:</strong> <?php echo htmlspecialchars($package['package_name'] ?? ''); ?></p>
                        <p class="mb-2"><strong>Ngày bắt đầu:</strong> <?php echo htmlspecialchars($member['start_date'] ?? ''); ?></p>
                        <p class="mb-2"><strong>Ngày kết thúc:</strong> <?php echo htmlspecialchars($member['end_date'] ?? ''); ?></p>
                        <p class="mb-3">
                            <strong>Còn lại:</strong>
                            <?php
                            if ($days_left === null) {
                                echo 'Không xác định';
                            } elseif ($days_left < 0) {
                                echo 'Đã hết hạn';
                            } else {
                                echo $days_left . ' ngày';
                            }
                            ?>
                        </p>
                        <a href="<?php echo $base_path; ?>user/package/index.php" class="btn btn-outline-primary dashboard-link-btn">
                            Xem gói tập
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Hiện chưa có dữ liệu gói tập đang dùng.</p>
                        <a href="<?php echo $base_path; ?>user/packages/index.php" class="btn btn-outline-primary dashboard-link-btn">
                            Xem các gói tập
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card p-4">
                    <div class="dashboard-icon-box mb-3">
                        <i class="bi bi-calendar2-week"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Kế hoạch của bạn</h4>
                    <p class="text-muted mb-3">
                        Xem kế hoạch tập luyện và dinh dưỡng AI đã được tạo cho bạn.
                    </p>
                    <a href="<?php echo $base_path; ?>user/plans/index.php" class="btn btn-outline-primary dashboard-link-btn">
                        Xem kế hoạch
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card p-4">
                    <div class="dashboard-icon-box mb-3">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Lịch sử đăng ký</h4>
                    <p class="text-muted mb-3">
                        Theo dõi trạng thái các lần đăng ký gói tập của bạn.
                    </p>
                    <a href="<?php echo $base_path; ?>user/registrations/index.php" class="btn btn-outline-primary dashboard-link-btn">
                        Xem đăng ký
                    </a>
                </div>
            </div>


            <div class="col-md-6 col-xl-4">
    <div class="dashboard-card p-4">
        <div class="dashboard-icon-box mb-3">
            <i class="bi bi-calendar-check"></i>
        </div>
        <h4 class="fw-bold mb-3">Lịch sử check-in</h4>
        <p class="text-muted mb-3">
            Xem lại các lần bạn đã đến phòng gym, giờ vào, giờ ra và trạng thái check-in.
        </p>
        <a href="<?php echo $base_path; ?>user/checkins/index.php" class="btn btn-outline-primary dashboard-link-btn">
            Xem check-in
        </a>
    </div>
</div>

            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card p-4">
                    <div class="dashboard-icon-box mb-3">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Thông tin tài khoản</h4>
                    <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <p class="mb-2"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
                    <p class="mb-3"><strong>Vai trò:</strong> <?php echo htmlspecialchars($user['role'] ?? 'user'); ?></p>
                    <a href="<?php echo $base_path; ?>user/profile.php" class="btn btn-outline-primary dashboard-link-btn">
                        Chỉnh hồ sơ
                    </a>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card p-4">
                    <div class="dashboard-icon-box mb-3">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Hỗ trợ nhanh</h4>
                    <p class="text-muted mb-3">
                        Cần tư vấn thêm về gói tập, lịch tập hoặc dịch vụ? Liên hệ ngay với phòng gym.
                    </p>
                    <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-outline-primary dashboard-link-btn">
                        Liên hệ hỗ trợ
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>