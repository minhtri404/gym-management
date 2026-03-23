<?php
$page_title = "AI gợi ý lịch tập";
include 'includes/auth-check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AI Workout - Gym Management</title>

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
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Nhập thông tin hội viên</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <form id="aiWorkoutForm">
                  <div class="mb-3">
                    <label class="form-label">Họ tên hội viên</label>
                    <input type="text" class="form-control" placeholder="Ví dụ: Nguyễn Văn A">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Mục tiêu</label>
                    <select class="form-select" id="goal">
                      <option value="">-- Chọn mục tiêu --</option>
                      <option value="weight-loss">Giảm cân</option>
                      <option value="muscle-gain">Tăng cơ</option>
                      <option value="maintain">Giữ dáng</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Số buổi / tuần</label>
                    <select class="form-select" id="daysPerWeek">
                      <option value="">-- Chọn số buổi --</option>
                      <option value="3">3 buổi</option>
                      <option value="4">4 buổi</option>
                      <option value="5">5 buổi</option>
                      <option value="6">6 buổi</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Kinh nghiệm tập</label>
                    <select class="form-select" id="level">
                      <option value="">-- Chọn trình độ --</option>
                      <option value="beginner">Mới bắt đầu</option>
                      <option value="intermediate">Trung bình</option>
                      <option value="advanced">Nâng cao</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" rows="4" placeholder="Ví dụ: đau gối nhẹ, muốn ưu tiên cardio..."></textarea>
                  </div>

                  <button type="button" class="btn btn-primary w-100" onclick="generateWorkoutPlan()">
                    <i class="bi bi-magic me-2"></i>Gợi ý lịch tập
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Kết quả gợi ý</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <div id="workoutResult" class="ai-result-box">
                  <div class="text-center text-muted py-5">
                    <i class="bi bi-clipboard2-pulse fs-1 d-block mb-3"></i>
                    Chưa có lịch tập. Hãy nhập thông tin và bấm “Gợi ý lịch tập”.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
  <script src="js/ai.js"></script>
</body>
</html>
