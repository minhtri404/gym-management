<?php
$page_title = 'Thêm gói tập';
include __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions/package-image-helper.php';
$base_path = '../../admin/';
$root_base_path = '../../';

$error = '';
$uploaded_image = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfToken = (string) ($_POST['csrf_token'] ?? '');
  if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(403);
    $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
  }

  $package_name = trim($_POST['package_name'] ?? '');
  $duration_months = trim($_POST['duration_months'] ?? '');
  $price = trim($_POST['price'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $short_description = trim($_POST['short_description'] ?? '');
  $detail_content = trim($_POST['detail_content'] ?? '');
  $benefits = trim($_POST['benefits'] ?? '');
  $suitable_for = trim($_POST['suitable_for'] ?? '');
  $status = trim($_POST['status'] ?? 'active');
  $upload_dir = __DIR__ . '/../../uploads/packages/';

  if ($error === '' && ($package_name === '' || $duration_months === '' || $price === '')) {
    $error = 'Vui lòng nhập đầy đủ các trường bắt buộc.';
  } elseif ($error === '' && !empty($_FILES['image']['name'] ?? '')) {
    $upload = upload_package_image_file($_FILES['image'], $upload_dir);

    if (empty($upload['success'])) {
      $error = $upload['message'] ?? 'Không thể tải ảnh gói tập.';
    } else {
      $uploaded_image = $upload['file_name'] ?? '';
    }
  }

  if ($error === '') {
    $stmt = $conn->prepare('INSERT INTO packages (package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sidsssssss', $package_name, $duration_months, $price, $description, $short_description, $detail_content, $benefits, $suitable_for, $uploaded_image, $status);

    if ($stmt->execute()) {
      $stmt->close();
      header('Location: ' . $base_path . 'packages.php?add=success');
      exit();
    }

    if ($uploaded_image !== '' && is_file($upload_dir . $uploaded_image)) {
      @unlink($upload_dir . $uploaded_image);
    }

    $error = 'Thêm gói tập thất bại: ' . $stmt->error;
    $stmt->close();
  } else {
    if ($uploaded_image !== '' && is_file($upload_dir . $uploaded_image)) {
      @unlink($upload_dir . $uploaded_image);
    }
  }
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
</head>
<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Thêm gói tập</h2>
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
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Tên gói tập <span class="text-danger">*</span></label>
                  <input type="text" name="package_name" class="form-control" required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Thời hạn (tháng) <span class="text-danger">*</span></label>
                  <input type="number" name="duration_months" class="form-control" min="1" required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Giá <span class="text-danger">*</span></label>
                  <input type="number" name="price" class="form-control" min="0" step="0.01" required>
                </div>

                <div class="col-12">
                  <label class="form-label">Mô tả</label>
                  <textarea name="description" class="form-control" rows="4"></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Mô tả ngắn</label>
                  <input type="text" name="short_description" class="form-control" maxlength="255" placeholder="Ví dụ: Gói phù hợp cho người mới bắt đầu tập gym">
                </div>

                <div class="col-12">
                  <label class="form-label">Nội dung chi tiết</label>
                  <textarea name="detail_content" class="form-control" rows="5" placeholder="Nhập nội dung mô tả chi tiết về gói tập..."></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Quyền lợi</label>
                  <textarea name="benefits" class="form-control" rows="5" placeholder="- Sử dụng toàn bộ thiết bị tập&#10;- Tham gia lớp group cơ bản&#10;- Tủ gửi đồ cá nhân&#10;- Nước uống miễn phí"></textarea>
                  <div class="form-text">
                    Mỗi dòng nhập 1 quyền lợi. Trang user sẽ hiển thị đúng từng dòng bạn nhập ở đây.
                  </div>
                  <div class="small text-muted mt-2">
                    Ví dụ: <code>- Sử dụng toàn bộ thiết bị tập</code>, <code>- Tham gia lớp group</code>, <code>- Khăn tập và nước uống miễn phí</code>
                  </div>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Phù hợp cho</label>
                  <input type="text" name="suitable_for" class="form-control" placeholder="Ví dụ: Người mới tập, người muốn giảm mỡ, dân văn phòng">
                </div>

                <div class="col-md-8">
                  <label class="form-label">Ảnh gói tập</label>
                  <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                  <div class="form-text">Hỗ trợ JPG, PNG, WEBP. Kích thước tối đa 3MB.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Ngừng hoạt động</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Lưu gói tập
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
