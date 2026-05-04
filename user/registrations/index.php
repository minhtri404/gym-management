<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/registration-functions.php';

$base_path = '../../';

$keyword = trim($_GET['keyword'] ?? '');
$results = [];

if ($keyword !== '') {
    $results = findRegistrationsByKeyword($conn, $keyword);
}

function registrationStatusLabel($status): string
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Chờ xử lý</span>';
        case 'approved':
            return '<span class="badge bg-success">Đã duyệt</span>';
        case 'closed':
            return '<span class="badge bg-secondary">Đã đóng</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Từ chối</span>';
        default:
            return '<span class="badge bg-dark">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tra cứu đăng ký gói</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="py-5 bg-light border-bottom">
    <div class="container" style="margin-top: 80px;">
        <div class="text-center">
            <h1 class="fw-bold mb-3">Tra cứu trạng thái đăng ký gói</h1>
            <p class="text-muted mb-0">
                Nhập số điện thoại hoặc email để kiểm tra trạng thái đăng ký của bạn.
            </p>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label fw-semibold">Số điện thoại hoặc email</label>
                        <input 
                            type="text" 
                            name="keyword" 
                            class="form-control" 
                            placeholder="Ví dụ: 0909xxxxxx hoặc email@gmail.com"
                            value="<?php echo htmlspecialchars($keyword); ?>"
                            required
                        >
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i> Tra cứu
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($keyword !== ''): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h3 class="fw-bold mb-4">Kết quả tra cứu</h3>

                    <?php if (!empty($results)): ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Họ tên</th>
                                        <th>Gói tập</th>
                                        <th>Điện thoại</th>
                                        <th>Email</th>
                                        <th>Ngày đăng ký</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['full_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($item['package_name'] ?? 'Chưa có'); ?></td>
                                            <td><?php echo htmlspecialchars($item['phone'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($item['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($item['created_at'] ?? ''); ?></td>
                                            <td><?php echo registrationStatusLabel($item['status'] ?? ''); ?></td>
                                        </tr>
                                        <?php if (!empty($item['note'])): ?>
                                            <tr>
                                                <td colspan="6">
                                                    <small class="text-muted">
                                                        <strong>Ghi chú:</strong>
                                                        <?php echo htmlspecialchars($item['note']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            Không tìm thấy đăng ký nào phù hợp với thông tin bạn nhập.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>