<?php
include __DIR__ . '/../../includes/config.php';
$base_path = '../../';

$package_id = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
$package = null;

if ($package_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $raw = $result->fetch_assoc();
    $stmt->close();

    if ($raw) {
        $package = [];
        $package['id'] = $raw['id'] ?? $raw['package_id'] ?? $package_id;
        $package['name'] = $raw['name'] ?? $raw['package_name'] ?? $raw['title'] ?? ($raw['package_title'] ?? 'Gói tập');
        $package['price'] = $raw['price'] ?? $raw['package_price'] ?? $raw['cost'] ?? 0;
        $package['description'] = $raw['description'] ?? $raw['note'] ?? $raw['details'] ?? '';
        $package['duration'] = $raw['duration'] ?? $raw['duration_months'] ?? $raw['period_months'] ?? null;
    }
}

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký gói tập - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">
</head>
<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <section class="section-dark package-register-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-5">
                        <span class="section-badge">PACKAGE REGISTRATION</span>
                        <h1 class="packages-title">Đăng ký <span class="accent">Gói tập</span></h1>
                        <p class="packages-subtitle">Điền thông tin để phòng gym liên hệ tư vấn và xác nhận gói tập phù hợp cho bạn.</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">Đăng ký gói tập thành công. Phòng gym sẽ liên hệ với bạn sớm.</div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="package-card package-card-modern mb-4">
                        <h3 class="package-name mb-3">Thông tin gói tập</h3>

                        <?php if ($package): ?>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-box"></i><span><strong>Gói:</strong> <?php echo htmlspecialchars($package['name']); ?></span></div></div>
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-cash-stack"></i><span><strong>Giá:</strong> <?php echo number_format((float) $package['price'], 0, ',', '.'); ?>đ</span></div></div>
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-calendar-range"></i><span><strong>Thời hạn:</strong> <?php echo htmlspecialchars($package['duration'] ?? 'Linh hoạt'); ?></span></div></div>
                                <div class="col-12"><p class="package-description mb-0"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p></div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Không tìm thấy gói tập. Vui lòng quay lại trang Packages và chọn lại.</div>
                        <?php endif; ?>
                    </div>

                    <div class="package-card package-card-modern">
                        <h3 class="package-name mb-4">Thông tin đăng ký</h3>
                        <form method="POST" action="../../php/package-registrations/submit-registration.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="package_id" value="<?php echo (int) $package_id; ?>">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Họ và tên</label><input type="text" name="full_name" class="form-control" required maxlength="100" placeholder="Nhập họ và tên"></div>
                                <div class="col-md-6"><label class="form-label">Số điện thoại</label><input type="text" name="phone" class="form-control" required maxlength="20" placeholder="Nhập số điện thoại"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="120" placeholder="Nhập email (nếu có)"></div>
                                <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" name="date_of_birth" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label">Địa chỉ</label><input type="text" name="address" class="form-control" maxlength="190" placeholder="Nhập địa chỉ"></div>
                                <div class="col-md-6"><label class="form-label">Mã gói</label><input type="text" class="form-control" value="<?php echo $package ? ('#' . $package['id'] . ' - ' . $package['name']) : ''; ?>" readonly></div>
                                <div class="col-12"><label class="form-label text-white">Ghi chú</label><textarea name="note" class="form-control" rows="4" placeholder="Ví dụ: muốn tập tăng cơ, giảm mỡ, cần tư vấn PT..."></textarea></div>
                                <div class="col-12 d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-hero-primary px-4"><i class="bi bi-send me-2"></i>Gửi đăng ký</button>
                                    <a href="<?php echo $base_path; ?>user/package/index.php" class="btn btn-user-outline px-4">Quay lại gói tập</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
