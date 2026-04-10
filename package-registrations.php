<?php
$page_title = "Đăng ký gói";
include 'includes/auth-check.php';

$base_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF token không hợp lệ.');
    }

    $action = trim($_POST['action'] ?? 'update');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'delete') {
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM package_registrations WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: package-registrations.php?delete=success");
        exit;
    }

    if ($action === 'approve' && $id > 0) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, full_name, phone, email, note, package_id, date_of_birth, address FROM package_registrations WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $registration = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$registration) {
                throw new Exception('Không tìm thấy đăng ký.');
            }

            $stmt = $conn->prepare("SELECT id FROM members WHERE phone = ? LIMIT 1");
            $stmt->bind_param("s", $registration['phone']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                throw new Exception('Số điện thoại đã tồn tại trong hội viên.');
            }

            $stmt = $conn->prepare("SELECT id, duration_months, price FROM packages WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $registration['package_id']);
            $stmt->execute();
            $package = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$package) {
                throw new Exception('Gói tập không tồn tại.');
            }

            $start_date = date('Y-m-d');
            $end_date = $start_date;
            if (!empty($package['duration_months'])) {
                $start = new DateTime($start_date);
                $end = clone $start;
                $end->modify("+" . (int) $package['duration_months'] . " months");
                $end_date = $end->format('Y-m-d');
            }

            $stmt = $conn->prepare("
                INSERT INTO members (full_name, gender, phone, email, date_of_birth, address, package_id, start_date, end_date, status)
                VALUES (?, 'Nam', ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param(
                "sssssiss",
                $registration['full_name'],
                $registration['phone'],
                $registration['email'],
                $registration['date_of_birth'],
                $registration['address'],
                $registration['package_id'],
                $start_date,
                $end_date
            );
            $stmt->execute();
            $member_id = $conn->insert_id;
            $stmt->close();

            $price = (float) ($package['price'] ?? 0);
            $paid_amount = 0.0;
            $remaining_amount = $price;
            $history_note = 'Đăng ký từ website';

            $stmt = $conn->prepare("
                INSERT INTO member_package_history (
                    member_id,
                    package_id,
                    action_type,
                    start_date,
                    end_date,
                    price,
                    paid_amount,
                    remaining_amount,
                    status,
                    note
                ) VALUES (?, ?, 'new', ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->bind_param(
                "iissddds",
                $member_id,
                $registration['package_id'],
                $start_date,
                $end_date,
                $price,
                $paid_amount,
                $remaining_amount,
                $history_note
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE package_registrations SET status = 'closed' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: package-registrations.php?approve=success");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: package-registrations.php?approve=error");
            exit;
        }
    }

    $status = trim($_POST['status'] ?? 'new');
    $allowed_statuses = ['new', 'contacted', 'closed'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'new';
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE package_registrations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: package-registrations.php?update=success");
        exit;
    }
}

$total_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations");
$total_registrations = $total_result ? (int) $total_result->fetch_assoc()['total'] : 0;

$new_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'new'");
$new_registrations = $new_result ? (int) $new_result->fetch_assoc()['total'] : 0;

$contacted_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'contacted'");
$contacted_registrations = $contacted_result ? (int) $contacted_result->fetch_assoc()['total'] : 0;

$closed_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'closed'");
$closed_registrations = $closed_result ? (int) $closed_result->fetch_assoc()['total'] : 0;

$filter_status = trim($_GET['status'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, ['new', 'contacted', 'closed'], true)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($keyword !== '') {
    $where_conditions[] = "(pr.full_name LIKE ? OR pr.phone LIKE ? OR pr.email LIKE ? OR p.package_name LIKE ?)";
    $keyword_like = '%' . $keyword . '%';
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $types .= 'ssss';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "
    SELECT 
        pr.id,
        pr.full_name,
        pr.phone,
        pr.email,
        pr.note,
        pr.status,
        pr.created_at,
        p.package_name,
        p.price,
        p.duration_months
    FROM package_registrations pr
    LEFT JOIN packages p ON pr.package_id = p.id
    $where_sql
    ORDER BY pr.id DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Đăng ký gói - Gym Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>

<body class="dashboard-page">

    <div class="d-flex dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
            <?php include 'includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
                    <div class="alert alert-success">Cập nhật đăng ký thành công.</div>
                <?php endif; ?>

                <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
                    <div class="alert alert-success">Xóa đăng ký thành công.</div>
                <?php endif; ?>
                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'success'): ?>
                    <div class="alert alert-success">Đã duyệt và tạo hội viên thành công.</div>
                <?php endif; ?>
                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'error'): ?>
                    <div class="alert alert-danger">Duyệt thất bại. Kiểm tra lại dữ liệu (trùng SĐT hoặc gói không tồn tại).</div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">Quản lý đăng ký gói tập</h4>
                        <p class="text-muted mb-0">Theo dõi yêu cầu đăng ký gói từ website và xử lý nhanh.</p>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Tổng đăng ký</div>
                                <h3 class="mb-0"><?php echo $total_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Mới</div>
                                <h3 class="mb-0 text-primary"><?php echo $new_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Đã liên hệ</div>
                                <h3 class="mb-0 text-warning"><?php echo $contacted_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Đã đóng</div>
                                <h3 class="mb-0 text-success"><?php echo $closed_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Tìm kiếm</label>
                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="Tên / SĐT / Email / Gói"
                            value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Lọc theo trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>Mới</option>
                            <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Tìm / Lọc
                        </button>
                    </div>

                    <div class="col-md-auto">
                        <a href="package-registrations.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0">Danh sách đăng ký gói tập</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Khách hàng</th>
                                        <th>Gói đăng ký</th>
                                        <th>Ghi chú</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                        <th class="text-end">Xử lý</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="small text-muted">SĐT: <?php echo htmlspecialchars($row['phone']); ?></div>
                                                    <div class="small text-muted">Email: <?php echo htmlspecialchars($row['email'] ?: 'Chưa có'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['package_name'] ?: 'Không xác định'); ?></div>
                                                    <?php if (!empty($row['price'])): ?>
                                                        <div class="small text-muted"><?php echo number_format((float)$row['price'], 0, ',', '.'); ?> VNĐ</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['duration_months'])): ?>
                                                        <div class="small text-muted">Thời hạn: <?php echo (int)$row['duration_months']; ?> tháng</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="min-width: 200px;"><?php echo nl2br(htmlspecialchars($row['note'] ?? '')); ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'new'): ?>
                                                        <span class="badge bg-primary">Mới</span>
                                                    <?php elseif ($row['status'] === 'contacted'): ?>
                                                        <span class="badge bg-warning text-dark">Đã liên hệ</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Đã đóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : ''; ?></td>
                                                <td class="text-end" style="min-width: 220px;">
                                                    <form method="POST" class="row g-2 justify-content-end">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                                        <div class="col-12">
                                                            <select name="status" class="form-select form-select-sm">
                                                                <option value="new" <?php echo $row['status'] === 'new' ? 'selected' : ''; ?>>Mới</option>
                                                                <option value="contacted" <?php echo $row['status'] === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                                                                <option value="closed" <?php echo $row['status'] === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-12">
                                                            <input type="hidden" name="action" value="update">
                                                            <button type="submit" class="btn btn-sm btn-warning w-100">
                                                                <i class="bi bi-save me-1"></i> Cập nhật
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <?php if ($row['status'] !== 'closed'): ?>
                                                        <form method="POST" class="mt-2" onsubmit="return confirm('Duyệt và tạo hội viên mới?');">
                                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                                <i class="bi bi-check-circle me-1"></i> Duyệt & tạo hội viên
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" class="mt-2" onsubmit="return confirm('Xóa đăng ký này?');">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                            <i class="bi bi-trash me-1"></i> Xóa
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Chưa có đăng ký gói nào.</td>
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

    <script src="js/main.js"></script>
</body>

</html>
