<?php
$page_title = 'Quản lý gói tập nâng cao';
include __DIR__ . '/../includes/auth-check.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_vn($amount)
{
    return number_format((float)$amount, 0, ',', '.') . 'đ';
}

function package_duration_text($months)
{
    $months = (int)$months;

    if ($months <= 0) {
        return 'Không xác định';
    }

    if ($months === 1) {
        return '1 tháng';
    }

    return $months . ' tháng';
}

function package_card_class($index)
{
    $classes = ['basic', 'standard', 'premium', 'vip'];
    return $classes[$index % count($classes)];
}

function package_label($index)
{
    $labels = ['GÓI CƠ BẢN', 'GÓI TIÊU CHUẨN', 'GÓI CAO CẤP', 'GÓI VIP'];
    return $labels[$index % count($labels)];
}

/*
|--------------------------------------------------------------------------
| Lấy danh sách gói
|--------------------------------------------------------------------------
*/
$packages = [];

$sql = "
    SELECT id, package_name, duration_months, price, status, description
    FROM packages
    ORDER BY price ASC, id ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Thống kê cơ bản
|--------------------------------------------------------------------------
*/
$total_packages = count($packages);
$active_packages = 0;
$hidden_packages = 0;
$total_price = 0;

foreach ($packages as $pkg) {
    if (($pkg['status'] ?? '') === 'active') {
        $active_packages++;
    } else {
        $hidden_packages++;
    }

    $total_price += (float)$pkg['price'];
}

$avg_price = $total_packages > 0 ? $total_price / $total_packages : 0;

/*
|--------------------------------------------------------------------------
| Đếm số hội viên đang dùng từng gói
| Dùng member_packages nếu có
|--------------------------------------------------------------------------
*/
$package_member_counts = [];

$count_sql = "
    SELECT package_id, COUNT(*) AS total_members
    FROM member_packages
    WHERE status = 'active'
    GROUP BY package_id
";

$count_result = $conn->query($count_sql);

if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $package_member_counts[(int)$row['package_id']] = (int)$row['total_members'];
    }
}

/*
|--------------------------------------------------------------------------
| Tính doanh thu đơn giản theo member_packages
|--------------------------------------------------------------------------
*/
$package_revenue = [];

$revenue_sql = "
    SELECT 
        mp.package_id,
        SUM(p.price) AS total_revenue
    FROM member_packages mp
    JOIN packages p ON p.id = mp.package_id
    GROUP BY mp.package_id
";

$revenue_result = $conn->query($revenue_sql);

if ($revenue_result) {
    while ($row = $revenue_result->fetch_assoc()) {
        $package_revenue[(int)$row['package_id']] = (float)$row['total_revenue'];
    }
}

$total_revenue = array_sum($package_revenue);

/*
|--------------------------------------------------------------------------
| Gói bán chạy nhất
|--------------------------------------------------------------------------
*/
$best_package_name = 'Chưa có dữ liệu';
$best_package_count = 0;

foreach ($packages as $pkg) {
    $pkg_id = (int)$pkg['id'];
    $count = $package_member_counts[$pkg_id] ?? 0;

    if ($count > $best_package_count) {
        $best_package_count = $count;
        $best_package_name = $pkg['package_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Quản lý gói tập - Gym Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>

    <link rel="stylesheet" href="../css/style.css"/>
    <link rel="stylesheet" href="assets/css/packages-ui.css"/>
</head>

<body class="dashboard-page admin-packages-page">

<div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="packages-content">

            <?php if (isset($_GET['add']) && $_GET['add'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Thêm gói tập thành công.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['edit']) && $_GET['edit'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Cập nhật gói tập thành công.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Xóa gói tập thành công.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['toggle']) && $_GET['toggle'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Cập nhật trạng thái gói tập thành công.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['toggle']) && $_GET['toggle'] === 'failed'): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Đổi trạng thái gói tập thất bại.
                </div>
            <?php endif; ?>

            <!-- HEADER -->
            <div class="packages-header">
                <div>
                    <h1>Quản lý gói tập nâng cao</h1>
                    <p>Tạo, chỉnh sửa và quản lý các gói tập của phòng gym.</p>
                </div>

                <a href="../php/packages/add-package.php" class="btn-add-package">
                    <i class="bi bi-plus-lg me-1"></i>
                    Thêm gói tập
                </a>
            </div>

            <!-- STATS -->
            <div class="package-stats-grid">

                <div class="package-stat-card">
                    <div class="package-stat-icon blue">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <small>Tổng số gói</small>
                        <h3><?php echo $total_packages; ?></h3>
                        <span>Gói tập hiện có</span>
                    </div>
                </div>

                <div class="package-stat-card">
                    <div class="package-stat-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <small>Đang hoạt động</small>
                        <h3><?php echo $active_packages; ?></h3>
                        <span>Gói đang hiển thị</span>
                    </div>
                </div>

                <div class="package-stat-card">
                    <div class="package-stat-icon orange">
                        <i class="bi bi-eye-slash"></i>
                    </div>
                    <div>
                        <small>Tạm ẩn</small>
                        <h3><?php echo $hidden_packages; ?></h3>
                        <span>Gói đang ẩn</span>
                    </div>
                </div>

                <div class="package-stat-card">
                    <div class="package-stat-icon purple">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <small>Doanh thu từ gói</small>
                        <h3><?php echo money_vn($total_revenue); ?></h3>
                        <span>Tổng doanh thu</span>
                    </div>
                </div>

                <div class="package-stat-card">
                    <div class="package-stat-icon blue">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <div>
                        <small>Gói bán chạy nhất</small>
                        <h3 style="font-size: 18px;"><?php echo h($best_package_name); ?></h3>
                        <span><?php echo $best_package_count; ?> hội viên đang dùng</span>
                    </div>
                </div>

            </div>

            <div class="package-info-banner">
                <i class="bi bi-info-circle-fill me-2"></i>
                Gói đã có hội viên mua nên ưu tiên <strong>ẩn gói</strong> thay vì xóa để không mất lịch sử đăng ký.
            </div>

            <!-- PACKAGE CARDS -->
            <div class="package-card-grid">

                <?php if (!empty($packages)): ?>
                    <?php foreach ($packages as $index => $pkg): ?>
                        <?php
                        $pkg_id = (int)$pkg['id'];
                        $members_using = $package_member_counts[$pkg_id] ?? 0;
                        $revenue = $package_revenue[$pkg_id] ?? 0;

                        $is_active = ($pkg['status'] ?? '') === 'active';
                        $card_class = package_card_class($index);
                        $label = package_label($index);

                        $can_delete = $members_using === 0;
                        ?>

                        <div class="package-card">
                            <div class="package-card-image <?php echo h($card_class); ?>">
                                <span><?php echo h($label); ?></span>
                            </div>

                            <div class="package-card-body">

                                <div class="package-card-top">
                                    <h3><?php echo h($pkg['package_name']); ?></h3>

                                    <?php if ($is_active): ?>
                                        <span class="package-status active">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="package-status hidden">Tạm ẩn</span>
                                    <?php endif; ?>
                                </div>

                                <div class="package-price">
                                    <?php echo money_vn($pkg['price']); ?>
                                    <span>/ <?php echo package_duration_text($pkg['duration_months']); ?></span>
                                </div>

                                <ul class="package-benefits">
                                    <li>
                                        <i class="bi bi-check-circle"></i>
                                        Thời hạn: <?php echo package_duration_text($pkg['duration_months']); ?>
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle"></i>
                                        Check-in theo gói đang hoạt động
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle"></i>
                                        <?php echo h($pkg['description'] ?: 'Chưa có mô tả gói tập'); ?>
                                    </li>
                                </ul>

                                <div class="package-member-count">
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo $members_using; ?> hội viên đang dùng
                                    <br>
                                    <i class="bi bi-cash-coin me-1"></i>
                                    Doanh thu: <?php echo money_vn($revenue); ?>
                                </div>

                                <div class="package-actions">
                                    <a class="pkg-btn view"
                                       href="../user/package/detail.php?id=<?php echo $pkg_id; ?>"
                                       target="_blank">
                                        <i class="bi bi-eye"></i>
                                        Xem
                                    </a>

                                    <a class="pkg-btn edit"
                                       href="../php/packages/edit-package.php?id=<?php echo $pkg_id; ?>">
                                        <i class="bi bi-pencil"></i>
                                        Sửa
                                    </a>

                                    <form method="POST" action="../php/packages/toggle-status.php">
                                        <input type="hidden" name="id" value="<?php echo $pkg_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                        <?php if ($is_active): ?>
                                            <button type="submit" class="pkg-btn hide w-100">
                                                <i class="bi bi-eye-slash"></i>
                                                Ẩn
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="pkg-btn show w-100">
                                                <i class="bi bi-eye"></i>
                                                Hiện
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <?php if ($can_delete): ?>
                                        <form method="POST"
                                              action="../php/packages/delete-package.php"
                                              onsubmit="return confirm('Bạn có chắc muốn xóa gói tập này không?');">
                                            <input type="hidden" name="id" value="<?php echo $pkg_id; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="pkg-btn delete w-100">
                                                <i class="bi bi-trash"></i>
                                                Xóa
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="pkg-btn disabled w-100" disabled title="Gói đã có hội viên dùng, không nên xóa">
                                            <i class="bi bi-trash"></i>
                                            Xóa
                                        </button>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="package-table-card p-4">
                        <div class="text-center text-muted">
                            Chưa có gói tập nào.
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- TABLE -->
            <div class="package-table-card">
                <div class="package-table-header">
                    <h2>Bảng tóm tắt gói tập</h2>
                </div>

                <div class="table-responsive">
                    <table class="package-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên gói</th>
                            <th>Thời hạn</th>
                            <th>Giá</th>
                            <th>Hội viên đang dùng</th>
                            <th>Doanh thu</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($packages)): ?>
                            <?php foreach ($packages as $pkg): ?>
                                <?php
                                $pkg_id = (int)$pkg['id'];
                                $members_using = $package_member_counts[$pkg_id] ?? 0;
                                $revenue = $package_revenue[$pkg_id] ?? 0;
                                $is_active = ($pkg['status'] ?? '') === 'active';
                                $can_delete = $members_using === 0;
                                ?>

                                <tr>
                                    <td>#<?php echo str_pad((string)$pkg_id, 3, '0', STR_PAD_LEFT); ?></td>

                                    <td>
                                        <strong><?php echo h($pkg['package_name']); ?></strong>
                                    </td>

                                    <td><?php echo package_duration_text($pkg['duration_months']); ?></td>

                                    <td class="price-blue">
                                        <?php echo money_vn($pkg['price']); ?>
                                    </td>

                                    <td>
                                        <?php echo $members_using; ?>
                                        <i class="bi bi-people ms-1 text-muted"></i>
                                    </td>

                                    <td>
                                        <?php echo money_vn($revenue); ?>
                                    </td>

                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="package-status active">Đang hoạt động</span>
                                        <?php else: ?>
                                            <span class="package-status hidden">Tạm ẩn</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="table-actions">
                                            <a class="table-action-btn view"
                                               href="../user/package/detail.php?id=<?php echo $pkg_id; ?>"
                                               target="_blank"
                                               title="Xem chi tiết">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <a class="table-action-btn edit"
                                               href="../php/packages/edit-package.php?id=<?php echo $pkg_id; ?>"
                                               title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <form method="POST" action="../php/packages/toggle-status.php" class="m-0">
                                                <input type="hidden" name="id" value="<?php echo $pkg_id; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                <button type="submit"
                                                        class="table-action-btn hide"
                                                        title="<?php echo $is_active ? 'Ẩn gói' : 'Hiện lại'; ?>">
                                                    <?php if ($is_active): ?>
                                                        <i class="bi bi-eye-slash"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-eye"></i>
                                                    <?php endif; ?>
                                                </button>
                                            </form>

                                            <?php if ($can_delete): ?>
                                                <form method="POST"
                                                      action="../php/packages/delete-package.php"
                                                      onsubmit="return confirm('Bạn có chắc muốn xóa gói tập này không?');">
                                                    <input type="hidden" name="id" value="<?php echo $pkg_id; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                    <button type="submit" class="table-action-btn delete" title="Xóa">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="table-action-btn disabled" disabled title="Gói đã có hội viên dùng">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Chưa có gói tập nào.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div class="text-muted small">
                        Hiển thị 1 đến <?php echo $total_packages; ?> trên <?php echo $total_packages; ?> gói tập
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" style="width: 110px;">
                            <option>10 / trang</option>
                        </select>

                        <button class="btn btn-sm btn-light" disabled>
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button class="btn btn-sm btn-primary">
                            1
                        </button>

                        <button class="btn btn-sm btn-light" disabled>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="../js/main.js"></script>
</body>
</html>
