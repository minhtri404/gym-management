<?php
$page_title = "Quản lý gói tập";
include 'includes/auth-check.php';

$sql = "SELECT id, package_name, duration_months, price, status 
        FROM packages
        ORDER BY id DESC";

$result = $conn->query($sql);
$card_sql = "SELECT id, package_name, duration_months, price, status, description
             FROM packages
             ORDER BY id DESC
             LIMIT 5";
$card_result = $conn->query($card_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Packages - Gym Management</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="dashboard-page">

  <div class="d-flex dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include 'includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <?php if (isset($_GET['add']) && $_GET['add'] === 'success'): ?>
          <div class="alert alert-success">Thêm gói tập thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['edit']) && $_GET['edit'] === 'success'): ?>
          <div class="alert alert-success">Cập nhật gói tập thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
          <div class="alert alert-success">Xóa gói tập thành công.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="mb-0">Danh sách gói tập</h4>
          <a href="php/packages/add-package.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Thêm gói tập
          </a>
        </div>

        <div class="row g-4">
          <?php if ($card_result && $card_result->num_rows > 0): ?>
            <?php while ($card = $card_result->fetch_assoc()): ?>
              <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 package-card">
                  <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h5 class="mb-0"><?php echo htmlspecialchars($card['package_name']); ?></h5>
                      <?php if ($card['status'] === 'active'): ?>
                        <span class="badge bg-success">Đang hoạt động</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Ngưng hoạt động</span>
                      <?php endif; ?>
                    </div>
                    <h3 class="fw-bold mb-3">
                      <?php echo number_format($card['price'], 0, ',', '.'); ?>đ
                    </h3>
                    <ul class="list-unstyled package-features mb-4">
                      <li>
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Thời hạn: <?php echo (int) $card['duration_months']; ?> tháng
                      </li>
                      <li>
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <?php echo htmlspecialchars($card['description'] ?: 'Chưa có mô tả'); ?>
                      </li>
                    </ul>
                    <div class="d-flex gap-2">
                      <a class="btn btn-warning w-100" href="php/packages/edit-package.php?id=<?php echo (int) $card['id']; ?>">
                        <i class="bi bi-pencil me-1"></i>Sửa
                      </a>
                      <form class="w-100" method="POST" action="php/packages/delete-package.php" onsubmit="return confirm('Bạn có chắc muốn xóa gói tập này không?');">
                        <input type="hidden" name="id" value="<?php echo (int) $card['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn btn-danger w-100">
                          <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-center text-muted">Chưa có gói tập nào.</div>
            </div>
          <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mt-4">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0">Bảng tóm tắt gói tập</h5>
          </div>
          <div class="card-body px-4 pb-4">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Tên gói</th>
                    <th>Thời hạn</th>
                    <th>Giá</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td><?php echo (int) $row['duration_months']; ?> tháng</td>
                        <td><?php echo number_format($row['price'], 0, ',', '.'); ?> VNĐ</td>
                        <td>
                          <?php if ($row['status'] === 'active'): ?>
                            <span class="badge bg-success">Đang hoạt động</span>
                          <?php else: ?>
                            <span class="badge bg-secondary">Ngưng hoạt động</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <a href="php/packages/edit-package.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil-square"></i> Sửa
                          </a>
                          <form class="d-inline-block ms-1" method="POST" action="php/packages/delete-package.php" onsubmit="return confirm('Bạn có chắc muốn xóa gói tập này không?');">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                              <i class="bi bi-trash"></i> Xóa
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted">Chưa có gói tập nào.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
</body>
</html>
