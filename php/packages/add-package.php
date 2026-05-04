<?php
$page_title = "Thêm gói t?p";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $package_name = trim($_POST['package_name'] ?? '');
  $duration_months = trim($_POST['duration_months'] ?? '');
  $price = trim($_POST['price'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $short_description = trim($_POST['short_description'] ?? '');
  $detail_content = trim($_POST['detail_content'] ?? '');
  $benefits = trim($_POST['benefits'] ?? '');
  $suitable_for = trim($_POST['suitable_for'] ?? '');
  $status = trim($_POST['status'] ?? 'active');

  if ($package_name === '' || $duration_months === '' || $price === '') {
    $error = "Vui lòng nh?p d?y d? các tru?ng b?t bu?c.";
  } else {
    $stmt = $conn->prepare("INSERT INTO packages (package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sidssssss", $package_name, $duration_months, $price, $description, $short_description, $detail_content, $benefits, $suitable_for, $status);

    if ($stmt->execute()) {
      $stmt->close();
      header("Location: " . $base_path . "packages.php?add=success");
      exit();
    } else {
      $error = "Thêm gói t?p th?t b?i: " . $stmt->error;
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
          <h2 class="fw-bold">Thêm gói t?p</h2>
          <a href="<?php echo $base_path; ?>packages.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay l?i
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
                  <label class="form-label">Tên gói t?p <span class="text-danger">*</span></label>
                  <input type="text" name="package_name" class="form-control" required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Th?i h?n (tháng) <span class="text-danger">*</span></label>
                  <input type="number" name="duration_months" class="form-control" min="1" required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Giá <span class="text-danger">*</span></label>
                  <input type="number" name="price" class="form-control" min="0" step="0.01" required>
                </div>

                <div class="col-12">
                  <label class="form-label">Mô t?</label>
                  <textarea name="description" class="form-control" rows="4"></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Mô t? ng?n</label>
                  <input type="text" name="short_description" class="form-control" maxlength="255" placeholder="Ví d?: Gói phù h?p cho ngu?i m?i b?t d?u t?p gym">
                </div>

                <div class="col-12">
                  <label class="form-label">N?i dung chi ti?t</label>
                  <textarea name="detail_content" class="form-control" rows="5" placeholder="Nh?p n?i dung mô t? chi ti?t v? gói t?p..."></textarea>
                </div>

                <div class="col-12">
                  <label class="form-label">Quy?n l?i</label>
                  <textarea name="benefits" class="form-control" rows="4" placeholder="- T?p không gi?i h?n&#10;- H? tr? HLV co b?n&#10;- Theo dõi ti?n d?"></textarea>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Phù h?p cho</label>
                  <input type="text" name="suitable_for" class="form-control" placeholder="Ví d?: Ngu?i m?i t?p, ngu?i mu?n gi?m m?, dân van phòng">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Tr?ng thái</label>
                  <select name="status" class="form-select">
                    <option value="active">Ðang ho?t d?ng</option>
                    <option value="inactive">Ngung ho?t d?ng</option>
                  </select>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Luu gói t?p
                  </button>
                  <a href="<?php echo $base_path; ?>packages.php" class="btn btn-outline-secondary ms-2">H?y</a>
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


