<?php
include 'includes/auth-check.php';
$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Gym Management</title>

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
        <div class="row g-4 mb-4">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <p class="text-muted mb-1">Tổng hội viên</p>
                    <h3 class="mb-0">120</h3>
                  </div>
                  <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-people"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <p class="text-muted mb-1">Gói đang hoạt động</p>
                    <h3 class="mb-0">85</h3>
                  </div>
                  <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-box-seam"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <p class="text-muted mb-1">Check-in hôm nay</p>
                    <h3 class="mb-0">47</h3>
                  </div>
                  <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-person-check"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <p class="text-muted mb-1">Lịch tập AI</p>
                    <h3 class="mb-0">15</h3>
                  </div>
                  <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-clipboard2-pulse"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0">Day 3: Dashboard đã dùng include PHP</h5>
          </div>
          <div class="card-body px-4 pb-4">
            <p class="mb-0">
              Sidebar và navbar hiện đã tách riêng để tái sử dụng cho toàn bộ hệ thống.
            </p>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
</body>
</html>