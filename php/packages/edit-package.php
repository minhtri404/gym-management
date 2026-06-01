<?php
$page_title = 'Sửa gói tập';
include __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions/package-image-helper.php';
$base_path = '../../admin/';
$root_base_path = '../../';

$error = '';
$package = null;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ' . $base_path . 'packages.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_name = trim($_POST['package_name'] ?? '');
    $duration_months = (int) ($_POST['duration_months'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $detail_content = trim($_POST['detail_content'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $suitable_for = trim($_POST['suitable_for'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    $upload_dir = __DIR__ . '/../../uploads/packages/';

    if ($package_name === '' || $duration_months <= 0 || $price < 0) {
        $error = 'Vui lòng nhập đầy đủ và đúng định dạng các trường bắt buộc.';
    } else {
        $current_image = $package['image'] ?? '';
        $next_image = $current_image;

        if ($remove_image) {
            $next_image = '';
        }

        if (!empty($_FILES['image']['name'] ?? '')) {
            $upload = upload_package_image_file($_FILES['image'], $upload_dir, $id);

            if (empty($upload['success'])) {
                $error = $upload['message'] ?? 'Không thể tải ảnh gói tập.';
            } else {
                $next_image = $upload['file_name'] ?? '';
            }
        }
    }

    if ($error === '') {
        $stmt = $conn->prepare('UPDATE packages SET package_name = ?, duration_months = ?, price = ?, description = ?, short_description = ?, detail_content = ?, benefits = ?, suitable_for = ?, image = ?, status = ? WHERE id = ?');
        $stmt->bind_param('sidsssssssi', $package_name, $duration_months, $price, $description, $short_description, $detail_content, $benefits, $suitable_for, $next_image, $status, $id);

        if ($stmt->execute()) {
            $stmt->close();

            if (($remove_image || $next_image !== ($package['image'] ?? '')) && is_local_package_image($package['image'] ?? '')) {
                $old_image_path = $upload_dir . ($package['image'] ?? '');
                if (is_file($old_image_path)) {
                    @unlink($old_image_path);
                }
            }

            header('Location: ' . $base_path . 'packages.php?edit=success');
            exit();
        }

        if (($next_image ?? '') !== '' && $next_image !== ($package['image'] ?? '') && is_file($upload_dir . $next_image)) {
            @unlink($upload_dir . $next_image);
        }

        $error = 'Cập nhật gói tập thất bại: ' . $stmt->error;
        $stmt->close();
    }
}

$stmt = $conn->prepare('SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, image, status FROM packages WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header('Location: ' . $base_path . 'packages.php');
    exit();
}

$package = $result->fetch_assoc();
$stmt->close();
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
</head>
<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Sửa gói tập</h2>
          <a href="<?php echo $base_path; ?>packages.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
          </a>
        </div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <form method="POST" action="" enctype="multipart/form-data">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Tên gói tập <span class="text-danger">*</span></label>
                  <input type="text" name="package_name" class="form-control" required value="<?php echo htmlspecialchars($package['package_name'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label">Thời hạn (tháng) <span class="text-danger">*</span></label>
                  <input type="number" name="duration_months" class="form-control" min="1" required value="<?php echo htmlspecialchars((string) ($package['duration_months'] ?? '')); ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label">Giá <span class="text-danger">*</span></label>
                  <input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?php echo htmlspecialchars((string) ($package['price'] ?? '')); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label">Mô tả</label>
                  <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($package['description'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Mô tả ngắn</label>
                  <input type="text" name="short_description" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($package['short_description'] ?? ''); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label">Nội dung chi tiết</label>
                  <textarea name="detail_content" class="form-control" rows="5"><?php echo htmlspecialchars($package['detail_content'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Quyền lợi</label>
                  <textarea name="benefits" class="form-control" rows="5" placeholder="- Sử dụng toàn bộ thiết bị tập&#10;- Tham gia lớp group cơ bản&#10;- Tủ gửi đồ cá nhân&#10;- Nước uống miễn phí"><?php echo htmlspecialchars($package['benefits'] ?? ''); ?></textarea>
                  <div class="form-text">
                    Mỗi dòng nhập 1 quyền lợi. Trang user sẽ hiển thị đúng từng dòng bạn nhập ở đây.
                  </div>
                  <div class="small text-muted mt-2">
                    Ví dụ: <code>- Sử dụng toàn bộ thiết bị tập</code>, <code>- Tham gia lớp group</code>, <code>- Khăn tập và nước uống miễn phí</code>
                  </div>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Phù hợp cho</label>
                  <input type="text" name="suitable_for" class="form-control" value="<?php echo htmlspecialchars($package['suitable_for'] ?? ''); ?>">
                </div>

                <div class="col-md-8">
                  <label class="form-label">Ảnh gói tập</label>
                  <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                  <div class="form-text">Hỗ trợ JPG, PNG, WEBP. Kích thước tối đa 3MB.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ảnh hiện tại</label>
                  <div class="border rounded p-2 h-100 d-flex align-items-center justify-content-center bg-light">
                    <?php if (!empty($package['image'])): ?>
                      <img src="<?php echo htmlspecialchars(resolve_package_image_url($package['image'], $root_base_path, $root_base_path . 'assets/images/ambitious-studio-rick-barrett-1RNQ11ZODJM-unsplash.jpg')); ?>" alt="Ảnh gói tập" style="max-width: 100%; max-height: 120px; object-fit: cover; border-radius: 10px;">
                    <?php else: ?>
                      <span class="text-muted small">Chưa có ảnh</span>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (!empty($package['image'])): ?>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                      <label class="form-check-label" for="removeImage">
                        Xóa ảnh hiện tại nếu không muốn dùng nữa
                      </label>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo (($package['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="inactive" <?php echo (($package['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Cập nhật gói tập
                  </button>
                  <a href="<?php echo $base_path; ?>packages.php" class="btn btn-outline-secondary ms-2">Hủy</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
