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
$checkins = [];
$total_checkins = 0;
$last_checkin = null;

/* Lấy user */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

/* Tìm member theo phone/email */
if ($user) {
    $phone = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email, status
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

    if ($member) {
        $member_id = (int) $member['id'];

        $stmt_total = $conn->prepare("
            SELECT COUNT(*) AS total_checkins
            FROM checkins
            WHERE member_id = ?
        ");
        $stmt_total->bind_param("i", $member_id);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $row_total = $result_total->fetch_assoc();
        $total_checkins = (int) ($row_total['total_checkins'] ?? 0);
        $stmt_total->close();

        $stmt_last = $conn->prepare("
            SELECT checkin_time
            FROM checkins
            WHERE member_id = ?
            ORDER BY checkin_time DESC
            LIMIT 1
        ");
        $stmt_last->bind_param("i", $member_id);
        $stmt_last->execute();
        $result_last = $stmt_last->get_result();
        $row_last = $result_last->fetch_assoc();
        $last_checkin = $row_last['checkin_time'] ?? null;
        $stmt_last->close();

        $stmt_checkins = $conn->prepare("
            SELECT id, checkin_date, checkin_time, checkout_time, status, checkin_method, note
            FROM checkins
            WHERE member_id = ?
            ORDER BY checkin_time DESC
        ");
        $stmt_checkins->bind_param("i", $member_id);
        $stmt_checkins->execute();
        $result_checkins = $stmt_checkins->get_result();

        while ($row = $result_checkins->fetch_assoc()) {
            $checkins[] = $row;
        }

        $stmt_checkins->close();
    }
}

function userCheckinStatusBadge(?string $status): string
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'checked_out':
            return '<span class="badge bg-secondary">Đã check-out</span>';
        case 'checked_in':
            return '<span class="badge bg-success">Đã check-in</span>';
        default:
            return '<span class="badge bg-dark">' . htmlspecialchars($status !== '' ? $status : 'Không rõ') . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử check-in của bạn</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">

    <style>
        .user-checkin-hero {
            background: linear-gradient(135deg, #f8f9fa 0%, #eef3ff 100%);
        }

        .user-checkin-card {
            border: 1px solid #e9ecef;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.05);
        }

        .user-checkin-stat {
            border: 1px solid #eef1f4;
            border-radius: 18px;
            padding: 1rem;
            background: #fafbfc;
            height: 100%;
        }

        .user-checkin-stat-number {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0d6efd;
        }

        .user-checkin-table thead th {
            background: #f8f9fa;
            white-space: nowrap;
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="user-checkin-hero py-5 border-bottom">
    <div class="container" style="margin-top: 80px;">
        <div class="text-center">
            <h1 class="fw-bold mb-3">Lịch sử check-in của bạn</h1>
            <p class="text-muted mb-0">
                Theo dõi các lần đến phòng gym, giờ vào, giờ ra và trạng thái check-in của bạn.
            </p>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">

        <?php if ($member): ?>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="user-checkin-stat">
                        <div class="text-muted mb-2">Tổng số buổi đã check-in</div>
                        <div class="user-checkin-stat-number"><?php echo $total_checkins; ?></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="user-checkin-stat">
                        <div class="text-muted mb-2">Lần check-in gần nhất</div>
                        <div class="user-checkin-stat-number" style="font-size:1rem;">
                            <?php echo htmlspecialchars($last_checkin ?: 'Chưa có'); ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="user-checkin-stat">
                        <div class="text-muted mb-2">Trạng thái hội viên</div>
                        <div class="user-checkin-stat-number" style="font-size:1rem;">
                            <?php echo htmlspecialchars($member['status'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="user-checkin-card p-4">
                <h3 class="fw-bold mb-4">Danh sách check-in</h3>

                <?php if (!empty($checkins)): ?>
                    <div class="table-responsive">
                        <table class="table align-middle user-checkin-table">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Giờ vào</th>
                                    <th>Giờ ra</th>
                                    <th>Trạng thái</th>
                                    <th>Phương thức</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checkins as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['checkin_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['checkin_time'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['checkout_time'] ?? 'Chưa check-out'); ?></td>
                                        <td><?php echo userCheckinStatusBadge($item['status'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['checkin_method'] ?? 'manual'); ?></td>
                                        <td><?php echo htmlspecialchars($item['note'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Bạn chưa có lịch sử check-in nào.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="user-checkin-card p-5 text-center">
                <div class="mb-3" style="font-size: 3rem; color:#0d6efd;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3 class="fw-bold mb-3">Chưa có dữ liệu hội viên</h3>
                <p class="text-muted mb-4">
                    Hệ thống chưa tìm thấy hội viên liên kết với tài khoản của bạn nên chưa thể hiển thị lịch sử check-in.
                </p>
                <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-primary px-4">
                    Liên hệ hỗ trợ
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>