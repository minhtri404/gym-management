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

    return date('d-m-Y', strtotime($date));
}

function formatTimeVN($time)
{
    if (empty($time)) {
        return '-';
    }

    return date('H:i', strtotime($time));
}

function formatMoneyVN($amount)
{
    return number_format((float)$amount, 0, ',', '.') . 'đ';
}

$user = null;
$member = null;
$package = null;
$recent_checkins = [];
$total_checkins = 0;
$days_left = null;

/* Lấy user */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone, avatar
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

/* Tìm member theo phone/email */
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

/* Lấy gói tập hiện tại */
if ($member) {
    $member_id = (int)$member['id'];

    $stmt_active = $conn->prepare("
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
    $stmt_active->bind_param("i", $member_id);
    $stmt_active->execute();
    $package = $stmt_active->get_result()->fetch_assoc();
    $stmt_active->close();

    /* Nếu chưa có trong member_packages thì lấy từ members.package_id */
    if (!$package && !empty($member['package_id'])) {
        $package_id = (int)$member['package_id'];

        $stmt_package = $conn->prepare("
            SELECT id AS package_id, package_name, duration_months, price, short_description
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
            $package['status'] = $member['status'] ?? 'active';
        }
    }

    /* Tổng số check-in */
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

    /* Lịch sử check-in gần đây */
    $stmt_recent = $conn->prepare("
        SELECT id, checkin_date, checkin_time, checkout_time, status
        FROM checkins
        WHERE member_id = ?
        ORDER BY checkin_time DESC
        LIMIT 6
    ");
    $stmt_recent->bind_param("i", $member_id);
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();

    while ($row = $result_recent->fetch_assoc()) {
        $recent_checkins[] = $row;
    }

    $stmt_recent->close();
}

/* Tính ngày còn lại */
if ($package && !empty($package['end_date'])) {
    $today = new DateTime(date('Y-m-d'));
    $end_date = new DateTime($package['end_date']);
    $days_left = (int)$today->diff($end_date)->format('%r%a');
}

$avatar_path = resolve_user_avatar_url(
    $user['avatar'] ?? '',
    $base_path,
    $user['full_name'] ?? 'User',
    '2563eb',
    'ffffff'
);

$has_package = $member && $package;
$package_name = $package['package_name'] ?? 'Chưa có gói';
$package_price = $package['price'] ?? 0;
$start_date = $package['start_date'] ?? null;
$end_date = $package['end_date'] ?? null;
$status = strtolower((string)($package['status'] ?? ''));
$is_active = $has_package && ($status === 'active' || $status === 'đang hoạt động' || $status === '1');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Gói của tôi - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css">
    <link rel="stylesheet" href="../includes/assets/css/membership.css">
</head>

<body class="membership-page">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="membership-wrapper">
    <div class="container">

        <div class="membership-shell">

            <div class="membership-topbar">
                <div class="membership-tabs">
                    <button class="membership-tab">Schedule</button>
                    <button class="membership-tab active">Membership status</button>
                </div>

                <div class="membership-search">
                    <input type="text" placeholder="Search">
                    <i class="bi bi-bell"></i>
                    <img src="<?php echo h($avatar_path); ?>" alt="Avatar" class="membership-avatar">
                </div>
            </div>

            <h1 class="membership-title">MEMBERSHIP STATUS</h1>
            <p class="membership-subtitle">Check your membership status.</p>

            <?php if (isset($_GET['payment_success'])): ?>
                <div class="membership-alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Thanh toán thành công!</strong><br>
                        Gói của bạn đã được thanh toán và đang chờ admin xác nhận kích hoạt.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($has_package): ?>

                <div class="membership-alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Gói tập đang hoạt động!</strong><br>
                        Bạn đang sử dụng gói <?php echo h($package_name); ?>.
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="membership-plan-card">
                            <div class="membership-plan-header">
                                <h3>
                                    <i class="bi bi-gem me-2"></i>
                                    <?php echo h($package_name); ?>
                                </h3>
                                <div class="membership-badges">
                                    <span class="membership-badge">Monthly</span>
                                    <span class="membership-badge active">
                                        <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="membership-plan-body">
                                <div class="membership-price">
                                    <?php echo formatMoneyVN($package_price); ?>
                                    <span>/ gói</span>
                                </div>

                                <div class="membership-meta">
                                    Ngày bắt đầu: <strong><?php echo formatDateVN($start_date); ?></strong>
                                    <br>
                                    Ngày hết hạn: <strong><?php echo formatDateVN($end_date); ?></strong>
                                </div>

                                <a href="<?php echo $base_path; ?>user/package/index.php" class="membership-upgrade-btn">
                                    Upgrade plan
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2">
                        <div class="membership-stat-card">
                            <div class="membership-stat-label">Number of check-ins</div>
                            <div class="membership-stat-number">
                                <?php echo $total_checkins; ?>
                            </div>
                            <div class="membership-small">
                                Tổng số buổi bạn đã đến phòng tập.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="membership-stat-card">
                            <div class="membership-stat-label">Expiry</div>
                            <div class="membership-stat-number">
                                <?php echo formatDateVN($end_date); ?>
                            </div>
                            <div class="membership-small">
                                <?php
                                if ($days_left === null) {
                                    echo 'Không xác định ngày hết hạn.';
                                } elseif ($days_left < 0) {
                                    echo 'Gói tập đã hết hạn.';
                                } else {
                                    echo 'Còn lại ' . $days_left . ' ngày.';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="membership-section-title">Lịch sử check-in gần đây</h2>

                <div class="membership-table-card">
                    <table class="membership-table">
                        <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Giờ vào</th>
                            <th>Giờ ra</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($recent_checkins)): ?>
                            <?php foreach ($recent_checkins as $checkin): ?>
                                <tr>
                                    <td>
                                        <div class="membership-time">
                                            <?php echo formatDateVN($checkin['checkin_date']); ?>
                                        </div>
                                        <div class="membership-small">Check-in</div>
                                    </td>
                                    <td><?php echo formatTimeVN($checkin['checkin_time']); ?></td>
                                    <td><?php echo formatTimeVN($checkin['checkout_time']); ?></td>
                                    <td><?php echo h($checkin['status'] ?? '-'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo $base_path; ?>user/checkins/index.php" class="membership-upgrade-btn">
                                            Xem chi tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Chưa có lịch sử check-in.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="membership-alert warning">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <div>
                        <strong>Bạn chưa đăng ký gói tập.</strong><br>
                        Hãy chọn một gói phù hợp để bắt đầu tập luyện.
                    </div>
                </div>

                <div class="membership-empty">
                    <div class="membership-empty-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>

                    <h3>Bạn chưa đăng ký gói</h3>

                    <p>
                        Hiện tại tài khoản của bạn chưa có gói tập nào đang hoạt động.
                        Sau khi đăng ký và thanh toán thành công, thông tin gói sẽ hiển thị tại đây.
                    </p>

                    <a href="<?php echo $base_path; ?>user/package/index.php" class="membership-primary-btn">
                        <i class="bi bi-plus-circle me-1"></i>
                        Xem và đăng ký gói tập
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
