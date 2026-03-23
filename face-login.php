<?php
$page_title = "Nhận diện khuôn mặt";
include 'includes/auth-check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Face Login - Gym Management</title>

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
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Camera nhận diện</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <div class="face-camera-box d-flex flex-column justify-content-center align-items-center">
                  <i class="bi bi-camera-video fs-1 mb-3"></i>
                  <p class="mb-2">Khu vực camera</p>
                  <small class="text-muted">Day 2 chỉ dựng giao diện, chưa kết nối face-api.js</small>
                </div>

                <div class="d-flex gap-2 mt-3">
                  <button class="btn btn-primary">
                    <i class="bi bi-camera-video me-2"></i>Mở camera
                  </button>
                  <button class="btn btn-success">
                    <i class="bi bi-person-bounding-box me-2"></i>Quét khuôn mặt
                  </button>
                  <button class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Kết quả nhận diện</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <div class="alert alert-info mb-3">
                  Chưa có dữ liệu nhận diện.
                </div>

                <ul class="list-group">
                  <li class="list-group-item d-flex justify-content-between">
                    <span>Họ tên:</span>
                    <strong>---</strong>
                  </li>
                  <li class="list-group-item d-flex justify-content-between">
                    <span>Mã hội viên:</span>
                    <strong>---</strong>
                  </li>
                  <li class="list-group-item d-flex justify-content-between">
                    <span>Trạng thái:</span>
                    <strong>---</strong>
                  </li>
                  <li class="list-group-item d-flex justify-content-between">
                    <span>Độ khớp:</span>
                    <strong>---</strong>
                  </li>
                </ul>
              </div>
            </div>

            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Lưu ý</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <ul class="mb-0 ps-3">
                  <li>Cho phép trình duyệt truy cập camera</li>
                  <li>Ánh sáng đủ để nhận diện tốt hơn</li>
                  <li>Day sau sẽ tích hợp face-api.js</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
</body>
</html>
