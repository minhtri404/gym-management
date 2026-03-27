<?php
$page_title = "Chi tiết hội viên";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
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

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>