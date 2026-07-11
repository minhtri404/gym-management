<?php
$page_title = 'Đăng ký gói';
include __DIR__ . '/../includes/auth-check.php';

$base_path = '';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function registration_status_badge(string $status): string
{
    return match ($status) {
        'new' => '<span class="badge bg-primary">Đã đăng ký</span>',
        'contacted' => '<span class="badge bg-warning text-dark">Đã liên hệ</span>',
        'closed' => '<span class="badge bg-success">Đã xử lý</span>',
        default => '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>',
    };
}

function payment_method_label(?int $paymentId): string
{
    return $paymentId > 0 ? 'Online / VNPAY' : 'Tại phòng';
}

function payment_status_badge(string $paymentStatus, ?int $paymentId): string
{
    if (($paymentId ?? 0) <= 0) {
        return '<span class="badge bg-secondary">Thanh toán tại phòng</span>';
    }

    return match ($paymentStatus) {
        'paid' => '<span class="badge bg-success">Đã thanh toán online</span>',
        'pending' => '<span class="badge bg-warning text-dark">Chờ thanh toán online</span>',
        'failed' => '<span class="badge bg-danger">Thanh toán online thất bại</span>',
        default => '<span class="badge bg-secondary">' . htmlspecialchars($paymentStatus) . '</span>',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die('CSRF token không hợp lệ.');
    }

    $action = trim($_POST['action'] ?? 'update');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare('DELETE FROM package_registrations WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        header('Location: package-registrations.php?delete=success');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $status = trim($_POST['status'] ?? 'new');
        $allowed_statuses = ['new', 'contacted', 'closed'];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'new';
        }

        $stmt = $conn->prepare('UPDATE package_registrations SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();

        header('Location: package-registrations.php?update=success');
        exit;
    }
}

$total_result = $conn->query('SELECT COUNT(*) AS total FROM package_registrations');
$total_registrations = $total_result ? (int) ($total_result->fetch_assoc()['total'] ?? 0) : 0;

$new_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'new'");
$new_registrations = $new_result ? (int) ($new_result->fetch_assoc()['total'] ?? 0) : 0;

$contacted_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'contacted'");
$contacted_registrations = $contacted_result ? (int) ($contacted_result->fetch_assoc()['total'] ?? 0) : 0;

$closed_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'closed'");
$closed_registrations = $closed_result ? (int) ($closed_result->fetch_assoc()['total'] ?? 0) : 0;

$paid_online_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE payment_id IS NOT NULL AND payment_id > 0 AND payment_status = 'paid'");
$paid_online_registrations = $paid_online_result ? (int) ($paid_online_result->fetch_assoc()['total'] ?? 0) : 0;

$filter_status = trim($_GET['status'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, ['new', 'contacted', 'closed'], true)) {
    $where_conditions[] = 'pr.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

if ($keyword !== '') {
    $where_conditions[] = '(pr.full_name LIKE ? OR pr.phone LIKE ? OR pr.email LIKE ? OR p.package_name LIKE ?)';
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
        pr.payment_status,
        pr.payment_id,
        pr.created_at,
        p.package_name,
        p.price,
        p.duration_months
    FROM package_registrations pr
    LEFT JOIN packages p ON pr.package_id = p.id
    $where_sql
    ORDER BY pr.id DESC
";

$registrations = [];
$list_error = '';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
        $list_error = $conn->error;
    }
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
    }
} elseif ($list_error === '') {
    $list_error = $conn->error;
}

if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}

if ($registrations === [] && $total_registrations > 0 && $keyword === '' && $filter_status === '') {
    $fallback_result = $conn->query("
        SELECT
            pr.id,
            pr.full_name,
            pr.phone,
            pr.email,
            pr.note,
            pr.status,
            pr.payment_status,
            pr.payment_id,
            pr.created_at,
            p.package_name,
            p.price,
            p.duration_months
        FROM package_registrations pr
        LEFT JOIN packages p ON pr.package_id = p.id
        ORDER BY pr.id DESC
    ");

    if ($fallback_result) {
        while ($row = $fallback_result->fetch_assoc()) {
            $registrations[] = $row;
        }
    } elseif ($list_error === '') {
        $list_error = $conn->error;
    }
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
    <link rel="stylesheet" href="../css/style.css" />
</head>
<body class="dashboard-page">
    <div class="d-flex dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
                    <div class="alert alert-success">Cập nhật đăng ký thành công.</div>
                <?php endif; ?>

                <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
                    <div class="alert alert-success">Xóa đăng ký thành công.</div>
                <?php endif; ?>

                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'success'): ?>
                    <div class="alert alert-success">Duyệt đăng ký và tạo hội viên thành công.</div>
                <?php endif; ?>

                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'error'): ?>
                    <div class="alert alert-danger">Duyệt đăng ký thất bại. Vui lòng kiểm tra lại dữ liệu.</div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">Quản lý đăng ký gói tập</h4>
                        <p class="text-muted mb-0">Theo dõi yêu cầu đăng ký từ website và kiểm tra trạng thái thanh toán online.</p>
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
                                <div class="text-muted mb-2">Đã đăng ký</div>
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
                                <div class="text-muted mb-2">Đã thanh toán online</div>
                                <h3 class="mb-0 text-success"><?php echo $paid_online_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="GET" class="row g-3 align-items-end mb-4">
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
                            <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>Đã đăng ký</option>
                            <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Đã xử lý</option>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Tìm / Lọc
                        </button>
                    </div>

                    <div class="col-md-auto">
                        <a href="package-registrations.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Đặt lại
                        </a>
                    </div>
                </form>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0">Danh sách đăng ký gói tập</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="text-muted small mb-3">
                            Hi&#7875;n th&#7883; <?php echo count($registrations); ?> / <?php echo $total_registrations; ?> &#273;&#259;ng k&yacute;
                        </div>

                        <?php if ($list_error !== ''): ?>
                            <div class="alert alert-danger">
                                Kh&ocirc;ng th&#7875; t&#7843;i danh s&aacute;ch &#273;&#259;ng k&yacute;: <?php echo htmlspecialchars($list_error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Khách hàng</th>
                                        <th>Gói đăng ký</th>
                                        <th>Ghi chú</th>
                                        <th>Trạng thái</th>
                                        <th>Thanh toán</th>
                                        <th>Ngày gửi</th>
                                        <th class="text-end">Xử lý</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($registrations)): ?>
                                        <?php foreach ($registrations as $row): ?>
                                            <tr>
                                                <td>#<?php echo str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="small text-muted">SĐT: <?php echo htmlspecialchars($row['phone']); ?></div>
                                                    <div class="small text-muted">Email: <?php echo htmlspecialchars($row['email'] ?: 'Chưa có'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['package_name'] ?: 'Không xác định'); ?></div>
                                                    <?php if (!empty($row['price'])): ?>
                                                        <div class="small text-muted"><?php echo number_format((float) $row['price'], 0, ',', '.'); ?> VNĐ</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['duration_months'])): ?>
                                                        <div class="small text-muted">Thời hạn: <?php echo (int) $row['duration_months']; ?> tháng</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="min-width: 220px;"><?php echo nl2br(htmlspecialchars($row['note'] ?? '')); ?></td>
                                                <td><?php echo registration_status_badge((string) ($row['status'] ?? '')); ?></td>
                                                <td>
                                                    <div class="fw-semibold small mb-1"><?php echo payment_method_label(isset($row['payment_id']) ? (int) $row['payment_id'] : 0); ?></div>
                                                    <?php echo payment_status_badge((string) ($row['payment_status'] ?? 'unpaid'), isset($row['payment_id']) ? (int) $row['payment_id'] : 0); ?>
                                                </td>
                                                <td><?php echo !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : ''; ?></td>
                                                <td class="text-end" style="min-width: 240px;">
                                                    <form method="POST" class="row g-2 justify-content-end">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                        <div class="col-12">
                                                            <select name="status" class="form-select form-select-sm">
                                                                <option value="new" <?php echo $row['status'] === 'new' ? 'selected' : ''; ?>>Đã đăng ký</option>
                                                                <option value="contacted" <?php echo $row['status'] === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                                                                <option value="closed" <?php echo $row['status'] === 'closed' ? 'selected' : ''; ?>>Đã xử lý</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-12">
                                                            <input type="hidden" name="action" value="update">
                                                            <button type="submit" class="btn btn-sm btn-warning w-100">
                                                                <i class="bi bi-save me-1"></i> Cập nhật trạng thái
                                                            </button>
                                                        </div>
                                                    </form>

                                                    <?php if (($row['status'] ?? '') !== 'closed'): ?>
                                                        <form method="POST" action="../php/package-registrations/approve.php" class="mt-2" onsubmit="return confirm('Duyệt đăng ký và tạo hội viên mới?');">
                                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                                <i class="bi bi-check-circle me-1"></i> Duyệt & tạo hội viên
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form method="POST" class="mt-2" onsubmit="return confirm('Xóa đăng ký này?');">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                            <i class="bi bi-trash me-1"></i> Xóa
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Chưa có đăng ký gói nào.</td>
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
