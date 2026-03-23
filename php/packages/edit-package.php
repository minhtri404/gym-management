<?php
$page_title = "Sửa gói tập";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$error = "";
$package = null;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: " . $base_path . "packages.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, package_name, duration_months, price, description, status FROM packages WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: " . $base_path . "packages.php");
    exit();
}

$package = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_name = trim($_POST['package_name'] ?? '');
    $duration_months = trim($_POST['duration_months'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($package_name === '' || $duration_months === '' || $price === '') {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc.";
    } else {
        $stmt = $conn->prepare("UPDATE packages SET package_name = ?, duration_months = ?, price = ?, description = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sidssi", $package_name, $duration_months, $price, $description, $status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: " . $base_path . "packages.php?edit=success");
            exit();
        }

        $error = "Cập nhật gói tập thất bại: " . $stmt->error;
        $stmt->close();
    }

    $package = [
        'id' => $id,
        'package_name' => $package_name,
        'duration_months' => $duration_months,
        'price' => $price,
        'description' => $description,
        'status' => $status,
    ];
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
          <h2 class="fw-bold">Sửa gói tập</h2>
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
                  <input type="text" name="package_name" class="form-control" required
                         value="<?php echo htmlspecialchars($package['package_name']); ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label">Thời hạn (tháng) <span class="text-danger">*</span></label>
                  <input type="number" name="duration_months" class="form-control" min="1" required
                         value="<?php echo htmlspecialchars($package['duration_months']); ?>">
                </div>

                <div class="col-md-3">
                  <label class="form-label">Giá <span class="text-danger">*</span></label>
                  <input type="number" name="price" class="form-control" min="0" step="0.01" required
                         value="<?php echo htmlspecialchars($package['price']); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label">Mô tả</label>
                  <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($package['description'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo ($package['status'] === 'active') ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="inactive" <?php echo ($package['status'] === 'inactive') ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Cập nhật gói tập
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
