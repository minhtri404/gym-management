<?php
$page_title = "Quản lý hội viên";
include 'includes/auth-check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Members - Gym Management</title>

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
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0">Danh sách hội viên</h5>
          </div>
          <div class="card-body px-4 pb-4">
            <p class="mb-3">Day 3: trang này đã chuyển sang PHP.</p>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Số điện thoại</th>
                    <th>Gói tập</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>#001</td>
                    <td>Nguyễn Văn A</td>
                    <td>0901234567</td>
                    <td>3 tháng</td>
                    <td><span class="badge bg-success">Đang hoạt động</span></td>
                  </tr>
                  <tr>
                    <td>#002</td>
                    <td>Trần Thị B</td>
                    <td>0912345678</td>
                    <td>1 tháng</td>
                    <td><span class="badge bg-warning text-dark">Sắp hết hạn</span></td>
                  </tr>
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