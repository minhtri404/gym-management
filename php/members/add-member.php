<?php
$page_title = "Thêm hội viên";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$success = "";
$error = "";

$packages = [];
$sql_packages = "SELECT id, package_name FROM packages WHERE status = 'active' ORDER BY id DESC";
$result_packages = $conn->query($sql_packages);

if ($result_packages && $result_packages->num_rows > 0) {
    while ($row = $result_packages->fetch_assoc()) {
        $packages[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Nam');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $package_id = trim($_POST['package_id'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($full_name === '' || $phone === '' || $package_id === '' || $start_date === '' || $end_date === '') {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc.";
    } else {
        $stmt = $conn->prepare("INSERT INTO members (full_name, gender, phone, email, date_of_birth, address, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssisss",
            $full_name,
            $gender,
            $phone,
            $email,
            $date_of_birth,
            $address,
            $package_id,
            $start_date,
            $end_date,
            $status
        );

        if ($stmt->execute()) {
            header("Location: " . $base_path . "members.php?add=success");
            exit();
        } else {
            $error = "Thêm hội viên thất bại: " . $stmt->error;
        }

        $stmt->close();
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
          <h2 class="fw-bold">Thêm hội viên</h2>
          <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
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
                  <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Giới tính</label>
                  <select name="gender" class="form-select">
                    <option value="Nam">Nam</option>
                    <option value="Nữ">Nữ</option>
                    <option value="Khác">Khác</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                  <input type="text" name="phone" class="form-control" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Ngày sinh</label>
                  <input type="date" name="date_of_birth" class="form-control">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Gói tập <span class="text-danger">*</span></label>
                  <select name="package_id" class="form-select" required>
                    <option value="">-- Chọn gói tập --</option>
                    <?php foreach ($packages as $package): ?>
                      <option value="<?php echo $package['id']; ?>">
                        <?php echo htmlspecialchars($package['package_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Địa chỉ</label>
                  <input type="text" name="address" class="form-control">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" class="form-control" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                  <input type="date" name="end_date" class="form-control" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active">Đang hoạt động</option>
                    <option value="expired">Hết hạn</option>
                    <option value="inactive">Ngưng hoạt động</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Lưu hội viên
                  </button>
                  <a href="<?php echo $base_path; ?>members.php" class="btn btn-outline-secondary ms-2">Hủy</a>
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
