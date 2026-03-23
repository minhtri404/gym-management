<?php
$page_title = "Thêm gói tập";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_name = trim($_POST['package_name'] ?? '');
    $duration_months = trim($_POST['duration_months'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($package_name === '' || $duration_months === '' || $price === '') {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc.";
    } else {
        $stmt = $conn->prepare("INSERT INTO packages (package_name, duration_months, price, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sidss", $package_name, $duration_months, $price, $description, $status);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: " . $base_path . "packages.php?add=success");
            exit();
        } else {
            $error = "Thêm gói tập thất bại: " . $stmt->error;
            $stmt->close();
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
  <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>
<body>
  <div class="d-flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Thêm gói tập</h2>
          <a href="<?php echo $base_path; ?>packages.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
          </a>
        </div>

        <?php if ($error !== ""): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <form method="POST" action="">
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

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Ngưng hoạt động</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Lưu gói tập
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
