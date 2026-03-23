<?php
include 'includes/auth-check.php';
$page_title = "Tổng quan";

$total_members = 0;
$active_packages = 0;
$today_members = 0;
$ai_pending = 15;
$member_active = 0;
$member_expired = 0;
$member_inactive = 0;
$checkin_labels = [];
$checkin_values = [];

if (isset($conn)) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM members");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_members = (int) ($row['total'] ?? 0);
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM packages WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $active_packages = (int) ($row['total'] ?? 0);
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM members WHERE DATE(start_date) = CURDATE()");
    if ($result) {
        $row = $result->fetch_assoc();
        $today_members = (int) ($row['total'] ?? 0);
    }

    $result = $conn->query("SELECT status, COUNT(*) AS total FROM members GROUP BY status");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['status'] === 'active') {
                $member_active = (int) $row['total'];
            } elseif ($row['status'] === 'expired') {
                $member_expired = (int) $row['total'];
            } elseif ($row['status'] === 'inactive') {
                $member_inactive = (int) $row['total'];
            }
        }
    }

    $result = $conn->query("SELECT DATE(start_date) AS day, COUNT(*) AS total FROM members WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(start_date) ORDER BY day");
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row['day']] = (int) $row['total'];
        }
    }

    $date = new DateTime();
    $date->setTime(0, 0, 0);
    $date->modify('-6 days');
    for ($i = 0; $i < 7; $i++) {
        $day_key = $date->format('Y-m-d');
        $checkin_labels[] = $date->format('d/m');
        $checkin_values[] = $map[$day_key] ?? 0;
        $date->modify('+1 day');
    }
}

$dashboard_data = [
    'checkinLabels' => $checkin_labels,
    'checkinValues' => $checkin_values,
    'memberStatus' => [$member_active, $member_expired, $member_inactive]
];
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
  <link rel="stylesheet" href="css/dashboard.css" />
</head>
<body class="dashboard-page">

  <div class="d-flex dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include 'includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <section class="dashboard-hero mb-4">
          <div class="hero-text">
            <p class="text-uppercase small mb-2">Tổng quan vận hành</p>
            <h1 class="h3 mb-2">Xin chào, Admin</h1>
            <p class="mb-0 text-muted">Theo dõi sức khỏe hệ thống và nhịp độ phòng gym theo thời gian thực.</p>
          </div>
          <div class="hero-actions">
            <button class="btn btn-light btn-sm">
              <i class="bi bi-download me-1"></i> Xuất báo cáo
            </button>
            <button class="btn btn-primary btn-sm">
              <i class="bi bi-plus-circle me-1"></i> Tạo thông báo
            </button>
          </div>
        </section>

        <div class="row g-4 mb-4">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <p class="text-muted mb-1">Tổng hội viên</p>
                    <h3 class="mb-1"><?php echo $total_members; ?></h3>
                    <span class="badge text-bg-success">Đang hoạt động: <?php echo $member_active; ?></span>
                  </div>
                  <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-people"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <p class="text-muted mb-1">Gói đang hoạt động</p>
                    <h3 class="mb-1"><?php echo $active_packages; ?></h3>
                    <span class="badge text-bg-light">Đang bán</span>
                  </div>
                  <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-box-seam"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <p class="text-muted mb-1">Hội viên mới hôm nay</p>
                    <h3 class="mb-1"><?php echo $today_members; ?></h3>
                    <span class="badge text-bg-warning">7 ngày gần nhất</span>
                  </div>
                  <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="bi bi-person-check"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <p class="text-muted mb-1">Lịch tập AI</p>
                    <h3 class="mb-1"><?php echo $ai_pending; ?></h3>
                    <span class="badge text-bg-secondary">Chờ duyệt</span>
                  </div>
                  <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="bi bi-clipboard2-pulse"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Hội viên mới theo tuần</h5>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary active" type="button">7 ngày</button>
                  <button class="btn btn-outline-secondary" type="button">30 ngày</button>
                </div>
              </div>
              <div class="card-body px-4 pb-4">
                <canvas id="checkinChart" height="140"></canvas>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Hội viên theo trạng thái</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <div class="chart-wrap">
                  <canvas id="packageChart" height="180"></canvas>
                </div>
                <div class="chart-legend">
                  <span><span class="legend-dot dot-success"></span>Đang hoạt động</span>
                  <span><span class="legend-dot dot-warning"></span>Hết hạn</span>
                  <span><span class="legend-dot dot-secondary"></span>Ngưng hoạt động</span>
                </div>
              </div>
            </div>

            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0">Công việc hôm nay</h5>
              </div>
              <div class="card-body px-4 pb-4">
                <ul class="list-unstyled mb-0">
                  <li class="task-item">
                    <div>
                      <div class="fw-semibold">Gia hạn gói tập</div>
                      <small class="text-muted">8 hội viên</small>
                    </div>
                    <span class="badge text-bg-warning">Ưu tiên</span>
                  </li>
                  <li class="task-item">
                    <div>
                      <div class="fw-semibold">Duyệt lịch tập AI</div>
                      <small class="text-muted">15 yêu cầu</small>
                    </div>
                    <span class="badge text-bg-primary">Mới</span>
                  </li>
                  <li class="task-item">
                    <div>
                      <div class="fw-semibold">Theo dõi vắng mặt</div>
                      <small class="text-muted">5 hội viên</small>
                    </div>
                    <span class="badge text-bg-secondary">Bình thường</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0">Hoạt động gần đây</h5>
          </div>
          <div class="card-body px-4 pb-4">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Thời gian</th>
                    <th>Hội viên</th>
                    <th>Hoạt động</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>08:45</td>
                    <td>Nguyễn Văn A</td>
                    <td>Check-in khuôn mặt</td>
                    <td><span class="badge text-bg-success">Thành công</span></td>
                  </tr>
                  <tr>
                    <td>09:10</td>
                    <td>Trần Thị B</td>
                    <td>Gia hạn gói 3 tháng</td>
                    <td><span class="badge text-bg-primary">Đã xử lý</span></td>
                  </tr>
                  <tr>
                    <td>10:05</td>
                    <td>Lê Minh C</td>
                    <td>Yêu cầu lịch tập AI</td>
                    <td><span class="badge text-bg-warning">Chờ duyệt</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    window.dashboardData = <?php echo json_encode($dashboard_data, JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="js/dashboard.js"></script>
</body>
</html>
