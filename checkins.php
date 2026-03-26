<?php
$page_title = "Check-in";
include __DIR__ . '/includes/auth-check.php';
include __DIR__ . '/includes/config.php';

$base_path = '';
// Lấy danh sách hội viên để hiển thị trong dropdown
$members = [];
$sqlMembers = "SELECT id, full_name, phone FROM members WHERE status = 'active' ORDER BY full_name ASC";
$resultMembers = $conn->query($sqlMembers);
if ($resultMembers && $resultMembers->num_rows > 0) {
    while ($row = $resultMembers->fetch_assoc()) {
        $members[] = $row;
    }
}
// Lấy thống kê check-in trong ngày hôm nay
$total_today = 0;
$in_gym_today = 0;
$checked_out_today = 0;

$sqlStats = "
    SELECT
        COUNT(*) AS total_today,
        SUM(CASE WHEN checkout_time IS NULL THEN 1 ELSE 0 END) AS in_gym_today,
        SUM(CASE WHEN checkout_time IS NOT NULL THEN 1 ELSE 0 END) AS checked_out_today
    FROM checkins
    WHERE DATE(checkin_time) = CURDATE()
";

$resultStats = $conn->query($sqlStats);
if ($resultStats && $rowStats = $resultStats->fetch_assoc()) {
    $total_today = (int)($rowStats['total_today'] ?? 0);
    $in_gym_today = (int)($rowStats['in_gym_today'] ?? 0);
    $checked_out_today = (int)($rowStats['checked_out_today'] ?? 0);
}
$filter_date = isset($_GET['filter_date']) && $_GET['filter_date'] !== ''
    ? $_GET['filter_date']
    : date('Y-m-d');
// Lấy danh sách check-in cho ngày đã chọn                                                                                          
$filter_date = isset($_GET['filter_date']) && $_GET['filter_date'] !== ''
    ? $_GET['filter_date']
    : date('Y-m-d');

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$checkins = [];

$sqlCheckins = "
    SELECT c.id, c.checkin_time, c.checkout_time, c.note, m.full_name, m.phone
    FROM checkins c
    INNER JOIN members m ON c.member_id = m.id
    WHERE DATE(c.checkin_time) = ?
";
if ($keyword !== '') {
    $sqlCheckins .= " AND (m.full_name LIKE ? OR m.phone LIKE ?)";
}

$sqlCheckins .= " ORDER BY c.id DESC";

$stmtCheckins = $conn->prepare($sqlCheckins);

if ($keyword !== '') {
    $search = '%' . $keyword . '%';
    $stmtCheckins->bind_param("sss", $filter_date, $search, $search);
} else {
    $stmtCheckins->bind_param("s", $filter_date);
}

$stmtCheckins->execute();
$resultCheckins = $stmtCheckins->get_result();

if ($resultCheckins && $resultCheckins->num_rows > 0) {
    while ($row = $resultCheckins->fetch_assoc()) {
        $checkins[] = $row;
    }
}

// Đóng statement sau khi sử dụng xong
$stmtCheckins->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in - Gym Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>

<body class="dashboard-page">
    <div class="d-flex dashboard-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
            <?php include __DIR__ . '/includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <div class="row g-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">Tổng check-in hôm nay</div>
                                    <h3 class="mb-0"><?php echo $total_today; ?></h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">Đang ở phòng gym</div>
                                    <h3 class="mb-0 text-warning"><?php echo $in_gym_today; ?></h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small mb-1">Đã check-out hôm nay</div>
                                    <h3 class="mb-0 text-success"><?php echo $checked_out_today; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Check-in hội viên</h5>
                            </div>
                            <?php if (isset($_GET['checkin_success'])): ?>
                                <div class="alert alert-success">Check-in nhanh thành công.</div>
                            <?php endif; ?>
                            <div class="card-body">
                                <?php if (isset($_GET['success'])): ?>
                                    <div class="alert alert-success">Check-in thành công.</div>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                    <div class="alert alert-danger">Check-in thất bại.</div>
                                <?php endif; ?>

                                <?php if (isset($_GET['duplicate'])): ?>
                                    <div class="alert alert-warning">Hội viên này đã check-in hôm nay rồi.</div>
                                <?php endif; ?>

                                <?php if (isset($_GET['checkout'])): ?>
                                    <div class="alert alert-info">Check-out thành công.</div>
                                <?php endif; ?>

                                <form action="<?php echo $base_path; ?>php/checkins/add-checkin.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Chọn hội viên</label>
                                        <select name="member_id" class="form-select" required>
                                            <option value="">-- Chọn hội viên --</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo (int)$member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['full_name']); ?> - <?php echo htmlspecialchars($member['phone']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Ghi chú</label>
                                        <textarea name="note" class="form-control" rows="3" placeholder="Ghi chú nếu có"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Check-in</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Lịch sử check-in</h5>
                            </div>
                            <form method="GET" class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <input type="date" name="filter_date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="keyword" class="form-control" placeholder="Nhập tên hoặc SĐT" value="<?php echo htmlspecialchars($keyword); ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                                </div>
                                <div class="col-md-2">
                                    <a href="checkins.php" class="btn btn-outline-secondary w-100">Reset</a>
                                </div>
                            </form>

                            <p class="text-muted mb-3">
                                Đang xem lịch sử check-in ngày: <strong><?php echo htmlspecialchars($filter_date); ?></strong>
                                <?php if ($keyword !== ''): ?>
                                    | Từ khóa: <strong><?php echo htmlspecialchars($keyword); ?></strong>
                                <?php endif; ?>
                            </p>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Hội viên</th>
                                                <th>SĐT</th>
                                                <th>Giờ vào</th>
                                                <th>Giờ ra</th>
                                                <th>Trạng thái</th>
                                                <th>Ghi chú</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($checkins)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">
                                                        Chưa có check-in nào.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($checkins as $index => $checkin): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($checkin['full_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($checkin['phone']); ?></td>
                                                        <td><?php echo htmlspecialchars($checkin['checkin_time']); ?></td>
                                                        <td><?php echo htmlspecialchars($checkin['checkout_time'] ?? ''); ?></td>
                                                        <td>
                                                            <?php if (empty($checkin['checkout_time'])): ?>
                                                                <span class="badge bg-warning text-dark">Đang ở phòng gym</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Đã check-out</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($checkin['note'] ?? ''); ?></td>
                                                        <td>
                                                            <?php if (empty($checkin['checkout_time'])): ?>
                                                                <a href="<?php echo $base_path; ?>php/checkins/checkout.php?id=<?php echo (int)$checkin['id']; ?>"
                                                                    class="btn btn-sm btn-outline-danger"
                                                                    onclick="return confirm('Xác nhận check-out hội viên này?');">
                                                                    Check-out
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">Đã xử lý</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/checkins.js"></script>
</body>

</html>