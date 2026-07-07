<?php
include __DIR__ . '/../../includes/config.php';
$base_path = '../../';
include __DIR__ . '/../../includes/functions/package-functions.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . $base_path . 'user/package/index');
    exit;
}

$package = getPackageById($conn, $id);

if (!$package || ($package['status'] ?? '') !== 'active') {
    header('Location: ' . $base_path . 'user/package/index');
    exit;
}

$benefits_lines = [];
if (!empty($package['benefits'])) {
    $benefits_lines = preg_split('/\r\n|\r|\n/', $package['benefits']);
}

$package_image_url = getPackageImageUrl($package, $base_path, max(0, ((int) ($package['id'] ?? 1)) - 1));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết gói tập - <?php echo h($package['package_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="py-5 border-bottom package-detail-hero">
    <div class="container" style="margin-top: 80px;">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <span class="badge mb-3 package-detail-badge">CHI TIẾT GÓI TẬP</span>
                <h1 class="fw-bold mb-3 package-detail-title"><?php echo h($package['package_name']); ?></h1>
                <p class="mb-0 package-detail-subtitle"><?php echo h($package['short_description'] ?: ($package['description'] ?: 'Gói tập phù hợp cho nhiều mục tiêu luyện tập khác nhau.')); ?></p>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-3 package-detail-summary">
                    <img src="<?php echo h($package_image_url); ?>" alt="<?php echo h($package['package_name']); ?>" style="width: 100%; height: 250px; object-fit: cover;">
                </div>
                <div class="card shadow-sm border-0 rounded-4 package-detail-summary">
                    <div class="card-body p-4">
                        <div class="fs-3 fw-bold mb-2 package-detail-price"><?php echo number_format((float) $package['price'], 0, ',', '.'); ?>đ</div>
                        <div class="mb-3 package-detail-meta">Thời hạn: <?php echo (int) $package['duration_months']; ?> tháng</div>
                        <a href="<?php echo $base_path; ?>user/package/register?package_id=<?php echo (int) $package['id']; ?>" class="btn btn-primary w-100 mb-2">Đăng ký gói này</a>
                        <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-outline-dark w-100">Liên hệ tư vấn</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Mô tả chi tiết</h3>
                        <div class="text-muted"><?php echo nl2br(h($package['detail_content'] ?: ($package['description'] ?: 'Hiện chưa có mô tả chi tiết cho gói tập này.'))); ?></div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Quyền lợi khi đăng ký</h3>
                        <?php if (!empty($benefits_lines)): ?>
                            <ul class="mb-0 text-muted">
                                <?php foreach ($benefits_lines as $item): ?>
                                    <?php if (trim($item) !== ''): ?>
                                        <li class="mb-2"><?php echo h(trim(ltrim($item, "- \t"))); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Hiện chưa có thông tin quyền lợi chi tiết.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3">Thông tin nhanh</h4>
                        <p class="mb-2"><strong>Tên gói:</strong> <?php echo h($package['package_name']); ?></p>
                        <p class="mb-2"><strong>Thời hạn:</strong> <?php echo (int) $package['duration_months']; ?> tháng</p>
                        <p class="mb-0"><strong>Giá:</strong> <?php echo number_format((float) $package['price'], 0, ',', '.'); ?>đ</p>
                    </div>
                </div>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3">Phù hợp cho</h4>
                        <p class="text-muted mb-0"><?php echo h($package['suitable_for'] ?: 'Người muốn cải thiện sức khỏe, vóc dáng và duy trì thói quen luyện tập.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
