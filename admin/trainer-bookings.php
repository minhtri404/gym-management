<?php
$page_title = 'Quản lý lịch đặt HLV';
include __DIR__ . '/../includes/auth-check.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_badge($status)
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Chờ xác nhận</span>';
        case 'confirmed':
            return '<span class="badge bg-primary">Đã xác nhận</span>';
        case 'completed':
            return '<span class="badge bg-success">Hoàn thành</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">Không rõ</span>';
    }
}

$bookings = [];
$bookings_table_exists = false;

$tableCheck = $conn->query("SHOW TABLES LIKE 'trainer_bookings'");
$bookings_table_exists = $tableCheck && $tableCheck->num_rows > 0;

if ($bookings_table_exists) {
    $sql = "
        SELECT
            tb.id,
            tb.booking_date,
            tb.start_time,
            tb.end_time,
            tb.goal,
            tb.note,
            tb.status,
            tb.created_at,
            t.full_name AS trainer_name,
            t.specialty AS trainer_specialty,
            m.full_name AS member_name,
            m.phone AS member_phone,
            m.email AS member_email
        FROM trainer_bookings tb
        JOIN trainers t ON t.id = tb.trainer_id
        JOIN members m ON m.id = tb.member_id
        ORDER BY
            CASE tb.status
                WHEN 'pending' THEN 1
                WHEN 'confirmed' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'cancelled' THEN 4
                ELSE 5
            END,
            tb.booking_date DESC,
            tb.start_time DESC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
}

$total_bookings = count($bookings);
$pending_count = 0;
$confirmed_count = 0;
$completed_count = 0;
$cancelled_count = 0;

foreach ($bookings as $booking) {
    if ($booking['status'] === 'pending') {
        $pending_count++;
    } elseif ($booking['status'] === 'confirmed') {
        $confirmed_count++;
    } elseif ($booking['status'] === 'completed') {
        $completed_count++;
    } elseif ($booking['status'] === 'cancelled') {
        $cancelled_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý lịch đặt HLV</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-page">

<div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Quản lý lịch đặt HLV</h2>
                    <p class="text-muted mb-0">
                        Theo dõi lịch tư vấn cá nhân giữa hội viên và huấn luyện viên.
                    </p>
                </div>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                <div class="alert alert-success">
                    Cập nhật trạng thái lịch đặt thành công.
                </div>
            <?php endif; ?>

            <?php if (!$bookings_table_exists): ?>
                <div class="alert alert-warning">
                    Bảng <code>trainer_bookings</code> chưa tồn tại trong database. Hãy tạo bảng trước để quản lý lịch đặt HLV.
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Tổng lịch đặt</div>
                            <h3 class="fw-bold mb-0"><?php echo $total_bookings; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Chờ xác nhận</div>
                            <h3 class="fw-bold text-warning mb-0"><?php echo $pending_count; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Đã xác nhận</div>
                            <h3 class="fw-bold text-primary mb-0"><?php echo $confirmed_count; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">Hoàn thành</div>
                            <h3 class="fw-bold text-success mb-0"><?php echo $completed_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold">Danh sách lịch đặt</h5>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Hội viên</th>
                                <th>HLV</th>
                                <th>Ngày / Giờ</th>
                                <th>Mục tiêu</th>
                                <th>Ghi chú</th>
                                <th>Trạng thái</th>
                                <th>Ngày gửi</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (!empty($bookings)): ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($booking['member_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo h($booking['member_phone'] ?: $booking['member_email']); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?php echo h($booking['trainer_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo h($booking['trainer_specialty']); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?php echo !empty($booking['booking_date']) ? date('d/m/Y', strtotime($booking['booking_date'])) : ''; ?></strong>
                                            <div class="text-muted small">
                                                <?php echo !empty($booking['start_time']) ? date('H:i', strtotime($booking['start_time'])) : ''; ?>
                                                -
                                                <?php echo !empty($booking['end_time']) ? date('H:i', strtotime($booking['end_time'])) : ''; ?>
                                            </div>
                                        </td>

                                        <td><?php echo h($booking['goal']); ?></td>

                                        <td style="max-width: 260px;">
                                            <?php echo h($booking['note'] ?: '-'); ?>
                                        </td>

                                        <td><?php echo status_badge($booking['status']); ?></td>

                                        <td>
                                            <?php echo !empty($booking['created_at']) ? date('d/m/Y H:i', strtotime($booking['created_at'])) : ''; ?>
                                        </td>

                                        <td class="text-end">
                                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <a
                                                        href="../php/trainers/update-booking-status.php?id=<?php echo (int) $booking['id']; ?>&status=confirmed"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Xác nhận
                                                    </a>

                                                    <a
                                                        href="../php/trainers/update-booking-status.php?id=<?php echo (int) $booking['id']; ?>&status=cancelled"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bạn có chắc muốn hủy lịch này không?');"
                                                    >
                                                        Hủy
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <a
                                                        href="../php/trainers/update-booking-status.php?id=<?php echo (int) $booking['id']; ?>&status=completed"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        Hoàn thành
                                                    </a>

                                                    <a
                                                        href="../php/trainers/update-booking-status.php?id=<?php echo (int) $booking['id']; ?>&status=cancelled"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bạn có chắc muốn hủy lịch này không?');"
                                                    >
                                                        Hủy
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (in_array($booking['status'], ['completed', 'cancelled'], true)): ?>
                                                    <span class="text-muted small">Đã xử lý</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <?php echo $bookings_table_exists ? 'Chưa có lịch đặt HLV nào.' : 'Chưa có dữ liệu lịch đặt HLV.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="../js/main.js"></script>
</body>
</html>
