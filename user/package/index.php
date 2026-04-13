<?php
include __DIR__ . '/../../includes/config.php';
$base_path = '../../';

$sql = "SELECT * FROM packages ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gói tập - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">
</head>
<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <section class="packages-hero">
        <div class="container">
            <div class="packages-hero-content text-center">
                <span class="section-badge">FLEXZONE PACKAGES</span>
                <h1 class="packages-title">Choose Your <span class="accent">Best Plan</span></h1>
                <p class="packages-subtitle">
                    Chọn gói tập phù hợp với mục tiêu, ngân sách và thời gian của bạn.
                    Linh hoạt cho người mới bắt đầu, người muốn nâng cao thể lực và hội viên tập chuyên sâu.
                </p>
            </div>
        </div>
    </section>

    <section class="section-dark packages-section">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($pkg = $result->fetch_assoc()): ?>
                        <?php
                        $package_name = $pkg['name'] ?? $pkg['package_name'] ?? 'Gói tập';
                        $price = $pkg['price'] ?? $pkg['package_price'] ?? 0;
                        $duration = $pkg['duration_months'] ?? $pkg['duration'] ?? $pkg['duration_in_months'] ?? null;
                        $description = $pkg['description'] ?? $pkg['note'] ?? $pkg['details'] ?? 'Gói tập phù hợp cho hội viên muốn cải thiện sức khỏe và vóc dáng.';
                        $status = $pkg['status'] ?? 'active';

                        $is_active = true;
                        if (is_string($status)) {
                            $is_active = strtolower($status) !== 'inactive';
                        } elseif (is_numeric($status)) {
                            $is_active = (int) $status === 1;
                        }
                        ?>

                        <?php if ($is_active): ?>
                            <div class="col-md-6 col-lg-4 d-flex">
                                <div class="package-card package-card-modern reveal-up w-100">
                                    <div class="package-top">
                                        <span class="package-badge">Available</span>
                                        <h3 class="package-name"><?php echo htmlspecialchars($package_name); ?></h3>
                                        <div class="package-price-modern">
                                            <?php echo number_format((float) $price, 0, ',', '.'); ?>đ
                                        </div>
                                    </div>

                                    <div class="package-meta">
                                        <?php if (!empty($duration)): ?>
                                            <div class="package-meta-item">
                                                <i class="bi bi-calendar-range"></i>
                                                <span>Thời hạn: <?php echo htmlspecialchars($duration); ?> tháng</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="package-meta-item">
                                                <i class="bi bi-calendar-range"></i>
                                                <span>Thời hạn linh hoạt</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <p class="package-description">
                                        <?php echo nl2br(htmlspecialchars($description)); ?>
                                    </p>

                                    <div class="package-actions mt-auto">
                                        <a href="<?php echo $base_path; ?>user/package/detail.php?id=<?php echo (int) $pkg['id']; ?>" class="btn btn-user-outline w-100 mb-2">Chi tiết gói</a>
                                        <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-hero-primary w-100 mb-2">Đăng ký tư vấn</a>
                                        <a href="<?php echo $base_path; ?>user/package/register.php?package_id=<?php echo (int) $pkg['id']; ?>" class="btn btn-hero-primary w-100 mb-2">Đăng ký gói này</a>
                                        <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-user-outline w-100">Liên hệ ngay</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-package-box text-center reveal-up">
                            <div class="empty-package-icon mb-3">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h4 class="mb-3">Chưa có gói tập</h4>
                            <p class="text-soft-custom mb-0">Hiện chưa có dữ liệu gói tập để hiển thị. Vui lòng thêm gói tập trong trang quản trị.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
