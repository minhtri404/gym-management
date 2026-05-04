<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$base_path = '../../';
$user_id = (int) $_SESSION['user_id'];

$user = null;
$member = null;
$package = null;
$benefits_lines = [];
$days_left = null;

/* Lấy thông tin user */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone, avatar
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

/* Tìm hội viên tương ứng bằng phone/email */
if ($user) {
    $phone = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email, package_id, start_date, end_date, status
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param("ss", $phone, $email);
    $stmt_member->execute();
    $result_member = $stmt_member->get_result();
    $member = $result_member->fetch_assoc();
    $stmt_member->close();

    if ($member && !empty($member['package_id'])) {
        $package_id = (int) $member['package_id'];

        $stmt_package = $conn->prepare("
            SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, status
            FROM packages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt_package->bind_param("i", $package_id);
        $stmt_package->execute();
        $result_package = $stmt_package->get_result();
        $package = $result_package->fetch_assoc();
        $stmt_package->close();

        if ($package && !empty($package['benefits'])) {
            $benefits_lines = preg_split('/\r\n|\r|\n/', $package['benefits']);
        }
    }
}

/* Tính số ngày còn lại */
if ($member && !empty($member['end_date'])) {
    try {
        $today = new DateTime(date('Y-m-d'));
        $end_date = new DateTime($member['end_date']);
        $days_left = (int) $today->diff($end_date)->format('%r%a');
    } catch (Throwable $e) {
        $days_left = null;
    }
}

function memberStatusBadge(?string $status): string
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'active':
            return '<span class="badge bg-success">Đang hoạt động</span>';
        case 'inactive':
            return '<span class="badge bg-secondary">Ngưng hoạt động</span>';
        case 'expired':
            return '<span class="badge bg-danger">Đã hết hạn</span>';
        default:
            return '<span class="badge bg-dark">' . htmlspecialchars($status !== '' ? $status : 'Không rõ') . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gói của bạn</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css">

    <style>
        .my-package-hero {
            background: linear-gradient(135deg, #f8f9fa 0%, #eef3ff 100%);
        }

        .my-package-card {
            border: 1px solid #e9ecef;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.05);
            color: #0f172a;
        }

        .my-package-price {
            color: #0d6efd;
            font-weight: 700;
            font-size: 2rem;
        }

        .my-package-meta {
            border: 1px solid #eef1f4;
            border-radius: 18px;
            padding: 1rem;
            background: #fafbfc;
            height: 100%;
            color: #0f172a;
        }

        .my-package-stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
        }

        .my-package-benefits li {
            margin-bottom: 0.6rem;
            color: #0f172a;
        }
        /* Ensure any .text-muted inside these cards is visible */
        .my-package-card .text-muted,
        .my-package-meta .text-muted,
        .my-package-card p,
        .my-package-card h2,
        .my-package-card h4,
        .my-package-card h5,
        .my-package-card strong {
            color: #0f172a !important;
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="my-package-hero py-5 border-bottom">
    <div class="container" style="margin-top: 80px;">
        <div class="text-center">
            <h1 class="fw-bold mb-3">Gói của bạn</h1>
            <p class="text-muted mb-0">
                Theo dõi gói tập hiện tại, thời hạn sử dụng và thông tin quan trọng liên quan đến hội viên của bạn.
            </p>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">

        <?php if ($member && $package): ?>
            <div class="my-package-card p-4 p-lg-5 mb-4">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <span class="badge bg-dark mb-3">GÓI ĐANG SỬ DỤNG</span>
                        <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($package['package_name'] ?? ''); ?></h2>
                        <p class="text-muted mb-4">
                            <?php echo htmlspecialchars($package['short_description'] ?: ($package['description'] ?: 'Gói tập hiện tại của bạn.')); ?>
                        </p>

                        <div class="my-package-price mb-3">
                            <?php echo number_format((float) ($package['price'] ?? 0), 0, ',', '.'); ?>đ
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo $base_path; ?>user/package/detail.php?id=<?php echo (int) $package['id']; ?>" class="btn btn-primary px-4">
                                <i class="bi bi-eye me-2"></i>Xem chi tiết gói
                            </a>
                            <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-outline-dark px-4">
                                <i class="bi bi-headset me-2"></i>Liên hệ hỗ trợ
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="my-package-card p-4 h-100">
                            <h5 class="fw-bold mb-3">Thông tin nhanh</h5>
                            <p class="mb-2"><strong>Trạng thái:</strong> <?php echo memberStatusBadge($member['status'] ?? ''); ?></p>
                            <p class="mb-2"><strong>Ngày bắt đầu:</strong> <?php echo htmlspecialchars($member['start_date'] ?? ''); ?></p>
                            <p class="mb-2"><strong>Ngày kết thúc:</strong> <?php echo htmlspecialchars($member['end_date'] ?? ''); ?></p>
                            <p class="mb-0">
                                <strong>Còn lại:</strong>
                                <?php
                                if ($days_left === null) {
                                    echo 'Không xác định';
                                } elseif ($days_left < 0) {
                                    echo '<span class="text-danger">Đã hết hạn</span>';
                                } else {
                                    echo '<span class="text-success fw-semibold">' . $days_left . ' ngày</span>';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="my-package-meta">
                        <div class="text-muted mb-2">Thời hạn gói</div>
                        <div class="my-package-stat-number">
                            <?php echo (int) ($package['duration_months'] ?? 0); ?> tháng
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="my-package-meta">
                        <div class="text-muted mb-2">Hội viên</div>
                        <div class="my-package-stat-number">
                            <?php echo htmlspecialchars($member['full_name'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="my-package-meta">
                        <div class="text-muted mb-2">Phù hợp cho</div>
                        <div class="my-package-stat-number" style="font-size:1rem;">
                            <?php echo htmlspecialchars($package['suitable_for'] ?: 'Nhiều mục tiêu luyện tập khác nhau'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="my-package-card p-4 h-100">
                        <h4 class="fw-bold mb-3">Mô tả gói tập</h4>
                        <div class="text-muted">
                            <?php echo nl2br(htmlspecialchars($package['detail_content'] ?: ($package['description'] ?: 'Hiện chưa có mô tả chi tiết.'))); ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="my-package-card p-4 h-100">
                        <h4 class="fw-bold mb-3">Quyền lợi của bạn</h4>

                        <?php if (!empty($benefits_lines)): ?>
                            <ul class="my-package-benefits text-muted mb-0">
                                <?php foreach ($benefits_lines as $item): ?>
                                    <?php if (trim($item) !== ''): ?>
                                        <li><?php echo htmlspecialchars(trim(ltrim($item, "- \t"))); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Hiện chưa có thông tin quyền lợi chi tiết cho gói này.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="my-package-card p-5 text-center">
                <div class="mb-3" style="font-size: 3rem; color:#0d6efd;">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h3 class="fw-bold mb-3">Bạn chưa có gói tập đang sử dụng</h3>
                <p class="text-muted mb-4">
                    Hiện hệ thống chưa tìm thấy hội viên hoặc gói tập đang liên kết với tài khoản của bạn.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?php echo $base_path; ?>user/packages/index.php" class="btn btn-primary px-4">
                        Xem các gói tập
                    </a>
                    <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-outline-dark px-4">
                        Liên hệ tư vấn
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>