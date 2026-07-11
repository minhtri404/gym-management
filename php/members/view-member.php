<?php
$page_title = 'Chi tiết hội viên';
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';
$root_base_path = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ' . $base_path . 'members.php');
    exit();
}

// Xử lý thêm/sửa/xóa ghi chú
$note_success = '';
$note_error = '';
$edit_note_id = isset($_GET['edit_note_id']) ? (int) $_GET['edit_note_id'] : 0;
$filter_note_date = isset($_GET['note_date']) ? trim($_GET['note_date']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        http_response_code(403);
        $note_error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $note_error === '' && isset($_POST['add_note'])) {
    $note_content = trim($_POST['note'] ?? '');

    if ($note_content === '') {
        $note_error = 'Vui lòng nhập nội dung ghi chú.';
    } else {
        $stmt_note = $conn->prepare('INSERT INTO member_notes (member_id, note, created_by_name) VALUES (?, ?, ?)');
        $created_by = 'Admin';
        $stmt_note->bind_param('iss', $id, $note_content, $created_by);

        if ($stmt_note->execute()) {
            header('Location: ' . $_SERVER['REQUEST_URI'] . '&note_success=1');
            exit();
        }

        $note_error = 'Lỗi khi thêm ghi chú: ' . $stmt_note->error;
        $stmt_note->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $note_error === '' && isset($_POST['update_note'])) {
    $note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
    $note_content = trim($_POST['note'] ?? '');

    if ($note_id <= 0 || $note_content === '') {
        $note_error = 'Vui lòng nhập nội dung ghi chú.';
    } else {
        $stmt_update = $conn->prepare('UPDATE member_notes SET note = ? WHERE id = ? AND member_id = ?');
        $stmt_update->bind_param('sii', $note_content, $note_id, $id);
        if ($stmt_update->execute()) {
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?id=' . $id . '&note_updated=1');
            exit();
        }

        $note_error = 'Lỗi khi cập nhật ghi chú: ' . $stmt_update->error;
        $stmt_update->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $note_error === '' && isset($_POST['delete_note'])) {
    $note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
    if ($note_id > 0) {
        $stmt_delete = $conn->prepare('DELETE FROM member_notes WHERE id = ? AND member_id = ?');
        $stmt_delete->bind_param('ii', $note_id, $id);
        if ($stmt_delete->execute()) {
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?id=' . $id . '&note_deleted=1');
            exit();
        }

        $note_error = 'Lỗi khi xóa ghi chú: ' . $stmt_delete->error;
        $stmt_delete->close();
    }
}

if (isset($_GET['note_success']) && $_GET['note_success'] === '1') {
    $note_success = 'Đã thêm ghi chú thành công.';
}
if (isset($_GET['note_updated']) && $_GET['note_updated'] === '1') {
    $note_success = 'Đã cập nhật ghi chú thành công.';
}
if (isset($_GET['note_deleted']) && $_GET['note_deleted'] === '1') {
    $note_success = 'Đã xóa ghi chú thành công.';
}

$stmt = $conn->prepare('
    SELECT 
        m.*, 
        p.package_name,
        p.price AS package_price,
        p.duration_months,
        p.duration_days,
        p.package_type,
        p.trial_once_per_user
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header('Location: ' . $base_path . 'members.php');
    exit();
}

$member = $result->fetch_assoc();
$stmt->close();

$history = [];
$stmt_history = $conn->prepare('
    SELECT
        h.*,
        p.package_name,
        p.package_type,
        p.duration_months,
        p.duration_days
    FROM member_package_history h
    LEFT JOIN packages p ON h.package_id = p.id
    WHERE h.member_id = ?
    ORDER BY h.id DESC
');
$stmt_history->bind_param('i', $id);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

if ($result_history && $result_history->num_rows > 0) {
    while ($row = $result_history->fetch_assoc()) {
        $history[] = $row;
    }
}
$stmt_history->close();

$notes = [];
$sql_notes = '
    SELECT *
    FROM member_notes
    WHERE member_id = ?
';
if ($filter_note_date !== '') {
    $sql_notes .= ' AND DATE(created_at) = ?';
}
$sql_notes .= ' ORDER BY id DESC';

$stmt_notes = $conn->prepare($sql_notes);
if ($filter_note_date !== '') {
    $stmt_notes->bind_param('is', $id, $filter_note_date);
} else {
    $stmt_notes->bind_param('i', $id);
}
$stmt_notes->execute();
$result_notes = $stmt_notes->get_result();

if ($result_notes && $result_notes->num_rows > 0) {
    while ($row = $result_notes->fetch_assoc()) {
        $notes[] = $row;
    }
}
$stmt_notes->close();

$total_debt = 0.0;
$stmt_debt = $conn->prepare('
    SELECT COALESCE(SUM(remaining_amount), 0) AS total_debt
    FROM member_package_history
    WHERE member_id = ? AND remaining_amount > 0
');
$stmt_debt->bind_param('i', $id);
$stmt_debt->execute();
$result_debt = $stmt_debt->get_result();
if ($result_debt && $row_debt = $result_debt->fetch_assoc()) {
    $total_debt = (float) ($row_debt['total_debt'] ?? 0);
}
$stmt_debt->close();
$current_history_package = null;

foreach ($history as $history_item) {
    if (($history_item['status'] ?? '') === 'active') {
        $current_history_package = $history_item;
        break;
    }
}

$current_remaining_amount = 0.0;

if ($current_history_package) {
    $current_remaining_amount = (float)($current_history_package['remaining_amount'] ?? 0);
}
function formatMemberStatus($status)
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Đang hoạt động</span>';
    }
    if ($status === 'expired') {
        return '<span class="badge bg-warning text-dark">Hết hạn</span>';
    }
    return '<span class="badge bg-secondary">Ngừng hoạt động</span>';
}

function formatHistoryStatus($status)
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Đang áp dụng</span>';
    }
    if ($status === 'expired') {
        return '<span class="badge bg-warning text-dark">Hết hạn</span>';
    }
    return '<span class="badge bg-secondary">Đã hủy</span>';
}
function isFreeTrialPackage($packageType)
{
    return $packageType === 'free_trial';
}

function formatPackageType($packageType)
{
    if ($packageType === 'free_trial') {
        return '<span class="badge bg-info text-dark">Dùng thử</span>';
    }

    if ($packageType === 'paid') {
        return '<span class="badge bg-primary">Trả phí</span>';
    }

    return '<span class="badge bg-secondary">Không xác định</span>';
}

function formatPackagePriceText($price, $packageType)
{
    if (isFreeTrialPackage($packageType)) {
        return 'Miễn phí';
    }

    if ($price === null || $price === '') {
        return 'Chưa có';
    }

    return number_format((float) $price, 0, ',', '.') . ' VNĐ';
}

function formatPackageDurationText($package)
{
    $packageType = $package['package_type'] ?? 'paid';

    if (isFreeTrialPackage($packageType)) {
        $days = (int)($package['duration_days'] ?? 7);

        if ($days <= 0) {
            $days = 7;
        }

        return $days . ' ngày dùng thử';
    }

    $months = (int)($package['duration_months'] ?? 0);

    if ($months <= 0) {
        return 'Linh hoạt';
    }

    return $months . ' tháng';
}

function getRemainingDaysText($endDate)
{
    if (empty($endDate)) {
        return 'Chưa có';
    }

    $today = new DateTime(date('Y-m-d'));
    $end = DateTime::createFromFormat('Y-m-d', $endDate);

    if (!$end) {
        return 'Không xác định';
    }

    if ($end < $today) {
        return 'Đã hết hạn';
    }

    $diff = $today->diff($end);
    return $diff->days . ' ngày';
}

function formatPaymentStatusText($price, $remainingAmount, $packageType)
{
    if (isFreeTrialPackage($packageType)) {
        return '<span class="badge bg-success">Không cần thanh toán</span>';
    }

    $price = (float)$price;
    $remainingAmount = (float)$remainingAmount;

    if ($price <= 0) {
        return '<span class="badge bg-success">Miễn phí</span>';
    }

    if ($remainingAmount <= 0) {
        return '<span class="badge bg-success">Đã thanh toán</span>';
    }

    if ($remainingAmount < $price) {
        return '<span class="badge bg-warning text-dark">Thanh toán một phần</span>';
    }

    return '<span class="badge bg-danger">Còn nợ</span>';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $root_base_path; ?>css/style.css">
    <style>
        .notes-list .note-item:last-child {
            border-bottom: none !important;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        .note-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>

<body class="dashboard-page">
    <div class="d-flex dashboard-wrapper">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <div class="main-content flex-grow-1">
            <?php include __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0">Chi tiết hội viên</h2>
                    <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại
                    </a>
                </div>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Thông tin hội viên</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="mb-3">
                                    <div class="text-muted small">Họ và tên</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Giới tính</div>
                                    <div><?php echo htmlspecialchars($member['gender'] ?? ''); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Số điện thoại</div>
                                    <div><?php echo htmlspecialchars($member['phone']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Email</div>
                                    <div><?php echo htmlspecialchars($member['email'] ?: 'Chưa có'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Ngày sinh</div>
                                    <div><?php echo !empty($member['date_of_birth']) ? htmlspecialchars($member['date_of_birth']) : 'Chưa có'; ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Địa chỉ</div>
                                    <div><?php echo htmlspecialchars($member['address'] ?: 'Chưa có'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Trạng thái</div>
                                    <div><?php echo formatMemberStatus($member['status']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Tổng nợ</div>
                                    <div class="fw-semibold text-danger"><?php echo number_format((float) $total_debt, 0, ',', '.'); ?> VNĐ</div>
                                </div>

                                <div class="mb-0">
                                    <div class="text-muted small">Ngày tạo</div>
                                    <div><?php echo htmlspecialchars($member['created_at'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 mt-4" id="member-notes">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Thêm ghi chú</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if ($note_success !== ''): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($note_success); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($note_error !== ''): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($note_error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="add_note" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Nội dung ghi chú</label>
                                        <textarea name="note" class="form-control" rows="3" placeholder="Nhập ghi chú về hội viên..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Thêm ghi chú
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Gói hiện tại</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Tên gói</div>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($member['package_name'] ?: 'Chưa có gói'); ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Loại gói</div>
                                        <div>
                                            <?php echo formatPackageType($member['package_type'] ?? ''); ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Giá gói</div>
                                        <div>
                                            <?php echo htmlspecialchars(formatPackagePriceText($member['package_price'] ?? null, $member['package_type'] ?? 'paid')); ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Thời hạn gói</div>
                                        <div>
                                            <?php echo htmlspecialchars(formatPackageDurationText($member)); ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Ngày bắt đầu</div>
                                        <div><?php echo !empty($member['start_date']) ? htmlspecialchars($member['start_date']) : 'Chưa có'; ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Ngày kết thúc</div>
                                        <div><?php echo !empty($member['end_date']) ? htmlspecialchars($member['end_date']) : 'Chưa có'; ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Còn lại</div>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars(getRemainingDaysText($member['end_date'] ?? '')); ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Thanh toán</div>
                                        <div>
                                            <?php echo formatPaymentStatusText($member['package_price'] ?? 0, $current_remaining_amount, $member['package_type'] ?? 'paid'); ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="text-muted small mb-2">Thao tác nhanh</div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="<?php echo $root_base_path; ?>php/members/edit-member.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="bi bi-pencil me-1"></i>Sửa hội viên
                                            </a>

                                            <?php if (($member['package_type'] ?? '') === 'free_trial'): ?>
                                                <a href="<?php echo $root_base_path; ?>php/members/renew-package.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-arrow-up-circle me-1"></i>Nâng cấp gói
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo $root_base_path; ?>php/members/renew-package.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Gia hạn gói
                                                </a>
                                            <?php endif; ?>

                                            <?php if (($member['package_type'] ?? '') !== 'free_trial' && $current_remaining_amount > 0): ?>
                                                <a href="<?php echo $root_base_path; ?>php/members/payment.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-cash-coin me-1"></i>Thu tiền
                                                </a>
                                            <?php endif; ?>

                                            <a href="<?php echo $base_path; ?>workout-plans.php?member_id=<?php echo (int) $member['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="bi bi-clipboard2-pulse me-1"></i>Kế hoạch tập luyện
                                            </a>

                                            <a href="<?php echo $base_path; ?>meal-plans.php?member_id=<?php echo (int) $member['id']; ?>" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-egg-fried me-1"></i>Kế hoạch dinh dưỡng
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Lịch sử gói tập</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Gói tập</th>
                                                <th>Loại</th>
                                                <th>Thời gian</th>
                                                <th>Thanh toán</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($history)): ?>
                                                <?php foreach ($history as $item): ?>
                                                    <tr>
                                                        <td>#<?php echo (int) $item['id']; ?></td>
                                                        <td>
                                                            <div class="fw-semibold">
                                                                <?php echo htmlspecialchars($item['package_name'] ?: 'Không xác định'); ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars(formatPackageDurationText($item)); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php echo formatPackageType($item['package_type'] ?? ''); ?>
                                                            <div class="small text-muted mt-1">
                                                                <?php echo htmlspecialchars($item['action_type']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($item['start_date']); ?>
                                                            <br>
                                                            <small class="text-muted">đến <?php echo htmlspecialchars($item['end_date']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if (($item['package_type'] ?? '') === 'free_trial'): ?>
                                                                <div>Tổng: Miễn phí</div>
                                                                <small class="text-success d-block">Không cần thanh toán</small>
                                                                <small class="text-muted d-block">Còn nợ: 0 VNĐ</small>
                                                            <?php else: ?>
                                                                <div>Tổng: <?php echo number_format((float) $item['price'], 0, ',', '.'); ?> VNĐ</div>
                                                                <small class="text-success d-block">Đã trả: <?php echo number_format((float) $item['paid_amount'], 0, ',', '.'); ?> VNĐ</small>
                                                                <small class="text-danger d-block">Còn nợ: <?php echo number_format((float) $item['remaining_amount'], 0, ',', '.'); ?> VNĐ</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo formatHistoryStatus($item['status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">Chưa có lịch sử gói tập.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 mt-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="mb-0">Danh sách ghi chú</h5>
                                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>">
                                        <input type="date" name="note_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_note_date); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Lọc</button>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo $root_base_path; ?>php/members/view-member.php?id=<?php echo (int) $member['id']; ?>#member-notes">&#272;&#7863;t l&#7841;i</a>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($notes)): ?>
                                    <div class="notes-list">
                                        <?php foreach ($notes as $note): ?>
                                            <div class="note-item border-bottom pb-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($note['created_by_name'] ?: 'Admin'); ?></div>
                                                    <div class="text-end">
                                                        <small class="text-muted d-block"><?php echo !empty($note['created_at']) ? date('d/m/Y H:i', strtotime($note['created_at'])) : ''; ?></small>
                                                        <div class="mt-2 d-flex gap-2 justify-content-end">
                                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo $root_base_path; ?>php/members/view-member.php?id=<?php echo (int) $member['id']; ?>&edit_note_id=<?php echo (int) $note['id']; ?>#member-notes">
                                                                <i class="bi bi-pencil-square me-1"></i>Sửa
                                                            </a>
                                                            <form method="POST" action="" onsubmit="return confirm('Xóa ghi chú này?');">
                                                                <input type="hidden" name="delete_note" value="1">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                    <i class="bi bi-trash me-1"></i>Xóa
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($edit_note_id === (int) $note['id']): ?>
                                                    <form method="POST" action="" class="mb-3">
                                                        <input type="hidden" name="update_note" value="1">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
                                                        <div class="mb-2">
                                                            <textarea name="note" class="form-control" rows="3" required><?php echo htmlspecialchars($note['note']); ?></textarea>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <i class="bi bi-check-circle me-1"></i>Lưu
                                                            </button>
                                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo $root_base_path; ?>php/members/view-member.php?id=<?php echo (int) $member['id']; ?>#member-notes">Hủy</a>
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-sticky-note fs-1 mb-2"></i>
                                        <div>Chưa có ghi chú nào.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
