<?php
$page_title = "Chi tiết hội viên";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
}

// Xử lý thêm ghi chú
$note_success = "";
$note_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_content = trim($_POST['note'] ?? '');

    if (empty($note_content)) {
        $note_error = "Vui lòng nhập nội dung ghi chú.";
    } else {
        $stmt_note = $conn->prepare("INSERT INTO member_notes (member_id, note, created_by_name) VALUES (?, ?, ?)");
        $created_by = "Admin"; // Có thể lấy từ session nếu có
        $stmt_note->bind_param("iss", $id, $note_content, $created_by);

        if ($stmt_note->execute()) {
            $note_success = "Đã thêm ghi chú thành công.";
            // Refresh để hiển thị ghi chú mới
            header("Location: " . $_SERVER['REQUEST_URI'] . "&note_success=1");
            exit();
        } else {
            $note_error = "Lỗi khi thêm ghi chú: " . $stmt_note->error;
        }
        $stmt_note->close();
    }
}

// Kiểm tra thông báo từ URL
if (isset($_GET['note_success']) && $_GET['note_success'] === '1') {
    $note_success = "Đã thêm ghi chú thành công.";
}

/* Lấy thông tin hội viên + gói tập hiện tại */
$stmt = $conn->prepare("
    SELECT 
        m.*,
        p.package_name,
        p.price AS package_price,
        p.duration_months
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: " . $base_path . "members.php");
    exit();
}

$member = $result->fetch_assoc();
$stmt->close();

/* Lấy lịch sử gói */
$history = [];
$stmt_history = $conn->prepare("
    SELECT
        h.*,
        p.package_name
    FROM member_package_history h
    LEFT JOIN packages p ON h.package_id = p.id
    WHERE h.member_id = ?
    ORDER BY h.id DESC
");
$stmt_history->bind_param("i", $id);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

if ($result_history && $result_history->num_rows > 0) {
    while ($row = $result_history->fetch_assoc()) {
        $history[] = $row;
    }
}
$stmt_history->close();
// Lấy ghi chú hội viên
$notes = [];
$stmt_notes = $conn->prepare("
    SELECT *
    FROM member_notes
    WHERE member_id = ?
    ORDER BY id DESC
");
$stmt_notes->bind_param("i", $id);
$stmt_notes->execute();
$result_notes = $stmt_notes->get_result();

if ($result_notes && $result_notes->num_rows > 0) {
    while ($row = $result_notes->fetch_assoc()) {
        $notes[] = $row;
    }
}
$stmt_notes->close();

function formatMemberStatus($status)
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Đang hoạt động</span>';
    }
    if ($status === 'expired') {
        return '<span class="badge bg-warning text-dark">Hết hạn</span>';
    }
    return '<span class="badge bg-secondary">Ngưng hoạt động</span>';
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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
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

<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <div class="main-content flex-grow-1">
            <?php include __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0">Chi tiết hội viên</h2>
                    <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Quay lại
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

                                <div class="mb-0">
                                    <div class="text-muted small">Ngày tạo</div>
                                    <div><?php echo htmlspecialchars($member['created_at'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form thêm ghi chú -->
                        <div class="card shadow-sm border-0 mt-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Thêm ghi chú</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($note_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($note_success); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($note_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($note_error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="add_note" value="1">
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
                                        <div class="fw-semibold"><?php echo htmlspecialchars($member['package_name'] ?: 'Chưa có gói'); ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Giá gói</div>
                                        <div>
                                            <?php echo isset($member['package_price']) ? number_format((float)$member['package_price'], 0, ',', '.') . ' VNĐ' : 'Chưa có'; ?>
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
                                        <div class="text-muted small">Thời hạn gói</div>
                                        <div>
                                            <?php echo isset($member['duration_months']) ? (int)$member['duration_months'] . ' tháng' : 'Chưa có'; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Thao tác nhanh</div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="<?php echo $base_path; ?>php/members/edit-member.php?id=<?php echo (int)$member['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="bi bi-pencil me-1"></i>Sửa hội viên
                                            </a>

                                            <a href="<?php echo $base_path; ?>php/members/renew-package.php?id=<?php echo (int)$member['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-arrow-repeat me-1"></i>Gia hạn gói
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
                                                        <td>#<?php echo (int)$item['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($item['package_name'] ?: 'Không xác định'); ?></td>
                                                        <td><?php echo htmlspecialchars($item['action_type']); ?></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($item['start_date']); ?>
                                                            <br>
                                                            <small class="text-muted">đến <?php echo htmlspecialchars($item['end_date']); ?></small>
                                                        </td>
                                                        <td>
                                                            <div>Tổng: <?php echo number_format((float)$item['price'], 0, ',', '.'); ?> VNĐ</div>
                                                            <small class="text-success d-block">Đã trả: <?php echo number_format((float)$item['paid_amount'], 0, ',', '.'); ?> VNĐ</small>
                                                            <small class="text-danger d-block">Còn nợ: <?php echo number_format((float)$item['remaining_amount'], 0, ',', '.'); ?> VNĐ</small>
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

                        <!-- Danh sách ghi chú -->
                        <div class="card shadow-sm border-0 mt-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Danh sách ghi chú</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($notes)): ?>
                                    <div class="notes-list">
                                        <?php foreach ($notes as $note): ?>
                                            <div class="note-item border-bottom pb-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="fw-semibold">
                                                        <?php echo htmlspecialchars($note['created_by_name'] ?: 'Admin'); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo !empty($note['created_at']) ? date('d/m/Y H:i', strtotime($note['created_at'])) : ''; ?>
                                                    </small>
                                                </div>
                                                <div class="note-content">
                                                    <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                                </div>
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