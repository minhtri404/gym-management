<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/avatar-helper.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$base_path = '../../';
$user_id = (int) $_SESSION['user_id'];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateVN($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        return date('d/m/Y', strtotime($date));
    } catch (Throwable $e) {
        return '-';
    }
}

function formatTimeVN($dateTime)
{
    if (empty($dateTime)) {
        return '-';
    }

    try {
        return date('H:i', strtotime($dateTime));
    } catch (Throwable $e) {
        return '-';
    }
}

function formatMoneyVN($amount)
{
    return number_format((float)$amount, 0, ',', '.') . ' VND';
}

$user = null;
$member = null;
$package = null;
$active_member_package = null;
$recent_checkins = [];
$total_checkins = 0;
$today_checkin = null;
$streak_days = 0;

/* =========================
   LẤY USER
========================= */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone, avatar, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

/* =========================
   TÌM MEMBER THEO PHONE/EMAIL
========================= */
if ($user) {
    $phone = trim((string)($user['phone'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email, package_id, start_date, end_date, status, created_at
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param("ss", $phone, $email);
    $stmt_member->execute();
    $member = $stmt_member->get_result()->fetch_assoc();
    $stmt_member->close();
}

/* =========================
   LẤY GÓI ACTIVE TỪ member_packages
   Nếu chưa có thì fallback về members.package_id
========================= */
if ($member) {
    $member_id = (int)$member['id'];

    $stmt_active_package = $conn->prepare("
        SELECT 
            mp.id AS member_package_id,
            mp.member_id,
            mp.package_id,
            mp.start_date,
            mp.end_date,
            mp.status,
            p.package_name,
            p.duration_months,
            p.price,
            p.short_description
        FROM member_packages mp
        JOIN packages p ON p.id = mp.package_id
        WHERE mp.member_id = ?
          AND mp.status = 'active'
        ORDER BY mp.end_date DESC
        LIMIT 1
    ");
    $stmt_active_package->bind_param("i", $member_id);
    $stmt_active_package->execute();
    $active_member_package = $stmt_active_package->get_result()->fetch_assoc();
    $stmt_active_package->close();

    if ($active_member_package) {
        $package = $active_member_package;
    } elseif (!empty($member['package_id'])) {
        $package_id = (int)$member['package_id'];

        $stmt_package = $conn->prepare("
            SELECT id, package_name, duration_months, price, short_description
            FROM packages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt_package->bind_param("i", $package_id);
        $stmt_package->execute();
        $package = $stmt_package->get_result()->fetch_assoc();
        $stmt_package->close();

        if ($package) {
            $package['start_date'] = $member['start_date'] ?? null;
            $package['end_date'] = $member['end_date'] ?? null;
            $package['status'] = $member['status'] ?? null;
        }
    }

    /* Tổng số buổi check-in */
    $stmt_total = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM checkins
        WHERE member_id = ?
    ");
    $stmt_total->bind_param("i", $member_id);
    $stmt_total->execute();
    $total_row = $stmt_total->get_result()->fetch_assoc();
    $total_checkins = (int)($total_row['total'] ?? 0);
    $stmt_total->close();

    /* Check-in hôm nay */
    $stmt_today = $conn->prepare("
        SELECT id, checkin_date, checkin_time, checkout_time, status
        FROM checkins
        WHERE member_id = ?
          AND checkin_date = CURDATE()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt_today->bind_param("i", $member_id);
    $stmt_today->execute();
    $today_checkin = $stmt_today->get_result()->fetch_assoc();
    $stmt_today->close();

    /* Lịch sử check-in gần đây */
    $stmt_recent = $conn->prepare("
        SELECT id, checkin_date, checkin_time, checkout_time, status
        FROM checkins
        WHERE member_id = ?
        ORDER BY checkin_time DESC
        LIMIT 5
    ");
    $stmt_recent->bind_param("i", $member_id);
    $stmt_recent->execute();
    $recent_result = $stmt_recent->get_result();
    while ($row = $recent_result->fetch_assoc()) {
        $recent_checkins[] = $row;
    }
    $stmt_recent->close();

    /* Tính chuỗi tập liên tục theo ngày check-in */
    $stmt_dates = $conn->prepare("
        SELECT DISTINCT checkin_date
        FROM checkins
        WHERE member_id = ?
        ORDER BY checkin_date DESC
        LIMIT 30
    ");
    $stmt_dates->bind_param("i", $member_id);
    $stmt_dates->execute();
    $dates_result = $stmt_dates->get_result();

    $dates = [];
    while ($row = $dates_result->fetch_assoc()) {
        $dates[] = $row['checkin_date'];
    }
    $stmt_dates->close();

    if (!empty($dates)) {
        $current = new DateTime(date('Y-m-d'));

        if ($dates[0] !== date('Y-m-d')) {
            $current->modify('-1 day');
        }

        foreach ($dates as $date) {
            if ($date === $current->format('Y-m-d')) {
                $streak_days++;
                $current->modify('-1 day');
            } else {
                break;
            }
        }
    }
}

/* =========================
   TÍNH NGÀY CÒN LẠI + PROGRESS
========================= */
$days_left = null;
$total_days = null;
$progress_percent = 0;

$start_date_value = $package['start_date'] ?? $member['start_date'] ?? null;
$end_date_value = $package['end_date'] ?? $member['end_date'] ?? null;

if (!empty($end_date_value)) {
    try {
        $today = new DateTime(date('Y-m-d'));
        $start_date = !empty($start_date_value) ? new DateTime($start_date_value) : null;
        $end_date = new DateTime($end_date_value);

        $days_left = (int)$today->diff($end_date)->format('%r%a');

        if ($start_date) {
            $total_days = max(1, (int)$start_date->diff($end_date)->format('%a'));
            $used_days = max(0, (int)$start_date->diff($today)->format('%a'));
            $progress_percent = min(100, max(0, round(($used_days / $total_days) * 100)));
        }
    } catch (Throwable $e) {
        $days_left = null;
    }
}

$avatar_path = resolve_user_avatar_url(
    $user['avatar'] ?? '',
    $base_path,
    $user['full_name'] ?? 'User',
    '111827',
    'ffffff'
);

$display_name = $user['full_name'] ?? 'Người dùng';
$member_name = $member['full_name'] ?? $display_name;
$package_name = $package['package_name'] ?? 'Chưa có gói';
$package_price = $package['price'] ?? 0;

$today_text = $today_checkin
    ? formatTimeVN($today_checkin['checkin_time'])
    : 'Chưa check-in';

$package_status = ($package && ($package['status'] ?? '') === 'active') ? 'Đang hoạt động' : 'Chưa kích hoạt';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Dashboard hội viên</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css">
    <link rel="stylesheet" href="../includes/assets/css/dashboard.css">
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="dashboard-sidebar">
        <div class="dashboard-logo">
            <div class="dashboard-logo-icon">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="dashboard-logo-text">
                <h3>GYM FIT</h3>
                <span>Stronger everyday</span>
            </div>
        </div>

        <ul class="dashboard-menu">
            <li>
                <a href="<?php echo $base_path; ?>user/dashboard/index.php" class="active">
                    <i class="bi bi-grid-1x2-fill"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>user/my-package/index.php">
                    <i class="bi bi-box-seam"></i>
                    Gói tập của tôi
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>user/checkins/index.php">
                    <i class="bi bi-calendar-check"></i>
                    Lịch sử check-in
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>user/checkins/index.php">
                    <i class="bi bi-qr-code-scan"></i>
                    Check-in ngay
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>user/plans/index.php">
                    <i class="bi bi-clipboard2-pulse"></i>
                    Lịch tập cá nhân
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>user/profile.php">
                    <i class="bi bi-person"></i>
                    Thông tin cá nhân
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>change-password.php">
                    <i class="bi bi-lock"></i>
                    Đổi mật khẩu
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Đăng xuất
                </a>
            </li>
        </ul>

        <div class="sidebar-mini-card">
            <h4>Giữ lửa tập luyện 🔥</h4>
            <p>Bạn đã tập luyện</p>
            <div class="big-number"><?php echo $total_checkins; ?></div>
            <p>buổi. Cố gắng lên!</p>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="dashboard-main">

        <!-- TOPBAR -->
        <div class="dashboard-topbar">
            <h2>Dashboard</h2>

            <div class="dashboard-profile">
                <img src="<?php echo h($avatar_path); ?>" alt="Avatar" class="dashboard-avatar">
                <div>
                    <div class="dashboard-profile-name"><?php echo h($display_name); ?></div>
                    <div class="dashboard-profile-role">Hội viên</div>
                </div>
            </div>
        </div>

        <!-- WELCOME -->
        <div class="dashboard-welcome">
            <h1>Xin chào, <span><?php echo h($member_name); ?></span> 💪</h1>
            <p>Chúc bạn có một buổi tập luyện hiệu quả và tràn đầy năng lượng!</p>
        </div>

        <!-- STATS -->
        <section class="dashboard-stats-grid">

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Gói tập hiện tại</h3>
                    <div class="dashboard-card-icon icon-purple">
                        <i class="bi bi-gem"></i>
                    </div>
                </div>

                <div class="dashboard-number">
                    <?php echo h($package_name); ?>
                </div>

                <?php if ($package): ?>
                    <span class="badge-active"><?php echo h($package_status); ?></span>
                    <p class="dashboard-muted mt-3 mb-0">
                        Còn lại
                        <strong class="<?php echo ($days_left !== null && $days_left <= 7) ? 'dashboard-warning' : 'dashboard-success'; ?>">
                            <?php
                            if ($days_left === null) {
                                echo '-';
                            } elseif ($days_left < 0) {
                                echo 'Đã hết hạn';
                            } else {
                                echo $days_left . ' ngày';
                            }
                            ?>
                        </strong>
                    </p>
                    <div class="dashboard-progress">
                        <span style="width: <?php echo (int)$progress_percent; ?>%;"></span>
                    </div>
                <?php else: ?>
                    <p class="dashboard-muted mb-0">Bạn chưa có gói tập đang hoạt động.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Check-in hôm nay</h3>
                    <div class="dashboard-card-icon icon-green">
                        <i class="bi bi-calendar2-check"></i>
                    </div>
                </div>

                <div class="dashboard-number">
                    <?php echo h($today_text); ?>
                </div>

                <?php if ($today_checkin): ?>
                    <p class="dashboard-success mb-0">
                        <i class="bi bi-check-circle-fill"></i>
                        Bạn đã check-in hôm nay
                    </p>
                <?php else: ?>
                    <p class="dashboard-muted mb-0">
                        Hôm nay bạn chưa check-in
                    </p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Tổng số buổi tập</h3>
                    <div class="dashboard-card-icon icon-blue">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>
                </div>

                <div class="dashboard-number">
                    <?php echo $total_checkins; ?> <span>buổi</span>
                </div>
                <p class="dashboard-muted mb-0">Kiên trì là chìa khóa!</p>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Chuỗi tập liên tục</h3>
                    <div class="dashboard-card-icon icon-orange">
                        <i class="bi bi-fire"></i>
                    </div>
                </div>

                <div class="dashboard-number">
                    <?php echo $streak_days; ?> <span>ngày</span>
                </div>
                <p class="dashboard-muted mb-0">Không bỏ cuộc!</p>
            </div>

        </section>

        <!-- MAIN CONTENT -->
        <section class="dashboard-content-grid">

            <!-- PACKAGE INFO -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Thông tin gói tập</h3>
                    <div class="dashboard-card-icon icon-purple">
                        <i class="bi bi-card-list"></i>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-row">
                        <span>Tên gói</span>
                        <strong><?php echo h($package_name); ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Ngày bắt đầu</span>
                        <strong><?php echo formatDateVN($start_date_value); ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Ngày hết hạn</span>
                        <strong><?php echo formatDateVN($end_date_value); ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Số ngày còn lại</span>
                        <strong class="<?php echo ($days_left !== null && $days_left <= 7) ? 'dashboard-warning' : 'dashboard-success'; ?>">
                            <?php
                            if ($days_left === null) {
                                echo '-';
                            } elseif ($days_left < 0) {
                                echo 'Đã hết hạn';
                            } else {
                                echo $days_left . ' ngày';
                            }
                            ?>
                        </strong>
                    </div>

                    <div class="info-row">
                        <span>Trạng thái</span>
                        <strong>
                            <span class="badge-active"><?php echo h($package_status); ?></span>
                        </strong>
                    </div>

                    <div class="info-row">
                        <span>Giá gói</span>
                        <strong><?php echo formatMoneyVN($package_price); ?></strong>
                    </div>
                </div>

                <a href="<?php echo $base_path; ?>user/package/index.php" class="dashboard-btn dashboard-btn-purple">
                    <i class="bi bi-crown me-1"></i>
                    Nâng cấp gói tập
                </a>
            </div>

            <!-- CHECKIN -->
            <div class="dashboard-card checkin-box">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Check-in ngay</h3>
                    <div class="dashboard-card-icon icon-orange">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                </div>

                <div class="qr-circle">
                    <i class="bi bi-qr-code"></i>
                </div>

                <h4 class="mb-2">Sẵn sàng check-in</h4>
                <p class="dashboard-muted">
                    Quét mã QR tại quầy lễ tân hoặc nhấn nút bên dưới.
                </p>

                <a href="<?php echo $base_path; ?>user/checkins/index.php" class="dashboard-btn">
                    <i class="bi bi-qr-code-scan me-1"></i>
                    Check-in ngay
                </a>
            </div>

            <!-- RECENT CHECKINS -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Lịch sử check-in gần đây</h3>
                    <a href="<?php echo $base_path; ?>user/checkins/index.php" class="dashboard-muted text-decoration-none">
                        Xem tất cả
                    </a>
                </div>

                <table class="dashboard-table">
                    <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Giờ vào</th>
                        <th>Giờ ra</th>
                        <th>Thời gian</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recent_checkins)): ?>
                        <?php foreach ($recent_checkins as $checkin): ?>
                            <?php
                            $duration_text = '-';

                            if (!empty($checkin['checkout_time']) && !empty($checkin['checkin_time'])) {
                                try {
                                    $in = new DateTime($checkin['checkin_time']);
                                    $out = new DateTime($checkin['checkout_time']);
                                    $diff = $in->diff($out);

                                    $hours = (int)$diff->format('%h');
                                    $minutes = (int)$diff->format('%i');

                                    if ($hours > 0) {
                                        $duration_text = $hours . 'h ' . $minutes . 'm';
                                    } else {
                                        $duration_text = $minutes . 'm';
                                    }
                                } catch (Throwable $e) {
                                    $duration_text = '-';
                                }
                            }
                            ?>
                            <tr>
                                <td class="success">
                                    <i class="bi bi-clock-history me-1"></i>
                                    <?php echo formatDateVN($checkin['checkin_date']); ?>
                                </td>
                                <td><?php echo formatTimeVN($checkin['checkin_time']); ?></td>
                                <td><?php echo formatTimeVN($checkin['checkout_time']); ?></td>
                                <td><?php echo h($duration_text); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center dashboard-muted">
                                Chưa có lịch sử check-in.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

        <!-- BOTTOM -->
        <section class="dashboard-bottom-grid">

            <!-- PERSONAL INFO -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Thông tin cá nhân</h3>
                    <div class="dashboard-card-icon icon-blue">
                        <i class="bi bi-person"></i>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-row">
                        <span>Họ và tên</span>
                        <strong><?php echo h($member_name); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Số điện thoại</span>
                        <strong><?php echo h($member['phone'] ?? $user['phone'] ?? '-'); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Email</span>
                        <strong><?php echo h($member['email'] ?? $user['email'] ?? '-'); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Ngày tham gia</span>
                        <strong><?php echo formatDateVN($member['created_at'] ?? null); ?></strong>
                    </div>
                </div>

                <a href="<?php echo $base_path; ?>user/profile.php" class="dashboard-btn dashboard-btn-purple">
                    <i class="bi bi-pencil me-1"></i>
                    Cập nhật thông tin
                </a>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3 class="dashboard-card-title">Thông báo</h3>
                    <a href="#" class="dashboard-muted text-decoration-none">Xem tất cả</a>
                </div>

                <div class="notification-item">
                    <div class="notification-icon icon-orange">
                        <i class="bi bi-gift-fill"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Ưu đãi đặc biệt tháng này</h4>
                        <p>Giảm giá khi gia hạn gói tập dài hạn.</p>
                    </div>
                </div>

                <div class="notification-item">
                    <div class="notification-icon icon-blue">
                        <i class="bi bi-megaphone-fill"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Lịch bảo trì hệ thống</h4>
                        <p>Hệ thống có thể bảo trì vào khung giờ thấp điểm.</p>
                    </div>
                </div>

                <div class="notification-item">
                    <div class="notification-icon icon-purple">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div class="notification-content">
                        <h4>Sự kiện workshop dinh dưỡng</h4>
                        <p>Theo dõi thông báo để tham gia các sự kiện mới.</p>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
