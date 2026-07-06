<?php
$page_title = 'Quản lý huấn luyện viên';
include __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions/trainer-image-helper.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_trainer_status_badge(string $status): string
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Đang hoạt động</span>';
    }

    return '<span class="badge bg-secondary">Tạm ngưng</span>';
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($filter_status, ['', 'active', 'inactive'], true)) {
    $filter_status = '';
}

$bookingTableExists = ($conn->query("SHOW TABLES LIKE 'trainer_bookings'")?->num_rows ?? 0) > 0;
$reviewTableExists = ($conn->query("SHOW TABLES LIKE 'trainer_reviews'")?->num_rows ?? 0) > 0;

$where = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $where[] = '(t.full_name LIKE ? OR t.specialty LIKE ? OR t.phone LIKE ? OR t.email LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($filter_status !== '') {
    $where[] = 't.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$bookingSelect = $bookingTableExists
    ? ', COUNT(DISTINCT tb.id) AS booking_count, SUM(CASE WHEN tb.status = "pending" THEN 1 ELSE 0 END) AS pending_booking_count'
    : ', 0 AS booking_count, 0 AS pending_booking_count';
$bookingJoin = $bookingTableExists ? 'LEFT JOIN trainer_bookings tb ON tb.trainer_id = t.id' : '';
$reviewSelect = $reviewTableExists
    ? ', COUNT(DISTINCT tr.id) AS review_count, COALESCE(AVG(CASE WHEN tr.status = "show" THEN tr.rating END), t.rating) AS live_rating'
    : ', 0 AS review_count, t.rating AS live_rating';
$reviewJoin = $reviewTableExists ? 'LEFT JOIN trainer_reviews tr ON tr.trainer_id = t.id' : '';

$sql = "
    SELECT
        t.id,
        t.full_name,
        t.avatar,
        t.specialty,
        t.experience_years,
        t.phone,
        t.email,
        t.bio,
        t.rating,
        t.total_members,
        t.status,
        t.created_at
        $bookingSelect
        $reviewSelect
    FROM trainers t
    $bookingJoin
    $reviewJoin
    $whereSql
    GROUP BY t.id
    ORDER BY
        CASE t.status WHEN 'active' THEN 1 ELSE 2 END,
        t.rating DESC,
        t.id DESC
";

$stmt = $conn->prepare($sql);
if ($stmt && $params) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

$trainers = [];
while ($result && $row = $result->fetch_assoc()) {
    $trainers[] = $row;
}
if ($stmt) {
    $stmt->close();
}

$totalTrainers = count($trainers);
$activeCount = 0;
$inactiveCount = 0;
$pendingBookings = 0;
$reviewCount = 0;
foreach ($trainers as $trainer) {
    if (($trainer['status'] ?? '') === 'active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    $pendingBookings += (int) ($trainer['pending_booking_count'] ?? 0);
    $reviewCount += (int) ($trainer['review_count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý huấn luyện viên</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .trainer-admin-avatar {
      width: 68px;
      height: 78px;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid rgba(15, 23, 42, 0.08);
      background: #e5edf5;
    }

    .trainer-admin-bio {
      max-width: 360px;
      color: #64748b;
      font-size: 13px;
      line-height: 1.5;
    }

    .trainer-stat-card {
      border: 0;
      border-radius: 14px;
      box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    }
  </style>
</head>
<body class="dashboard-page">
<div class="d-flex dashboard-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="main-content flex-grow-1">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
        <div>
          <h2 class="fw-bold mb-1">Quản lý huấn luyện viên</h2>
          <p class="text-muted mb-0">Thêm mới, chỉnh sửa hồ sơ, cập nhật ảnh, chuyên môn và trạng thái hoạt động của HLV.</p>
        </div>
        <a href="../php/trainers/add-trainer.php" class="btn btn-primary">
          <i class="bi bi-person-plus me-1"></i>Thêm HLV
        </a>
      </div>

      <?php if (isset($_GET['add']) && $_GET['add'] === 'success'): ?>
        <div class="alert alert-success">Thêm HLV thành công.</div>
      <?php endif; ?>
      <?php if (isset($_GET['edit']) && $_GET['edit'] === 'success'): ?>
        <div class="alert alert-success">Cập nhật HLV thành công.</div>
      <?php endif; ?>
      <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
        <div class="alert alert-success">Xóa HLV thành công.</div>
      <?php endif; ?>
      <?php if (isset($_GET['delete']) && $_GET['delete'] === 'soft'): ?>
        <div class="alert alert-warning">HLV đã có lịch/đánh giá nên hệ thống chuyển sang trạng thái tạm ngưng thay vì xóa dữ liệu.</div>
      <?php endif; ?>
      <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
        <div class="alert alert-success">Cập nhật trạng thái HLV thành công.</div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card trainer-stat-card"><div class="card-body">
            <div class="text-muted small">Tổng HLV</div>
            <h3 class="fw-bold mb-0"><?php echo $totalTrainers; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card trainer-stat-card"><div class="card-body">
            <div class="text-muted small">Đang hoạt động</div>
            <h3 class="fw-bold text-success mb-0"><?php echo $activeCount; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card trainer-stat-card"><div class="card-body">
            <div class="text-muted small">Lịch chờ xử lý</div>
            <h3 class="fw-bold text-warning mb-0"><?php echo $pendingBookings; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card trainer-stat-card"><div class="card-body">
            <div class="text-muted small">Phản hồi người dùng</div>
            <h3 class="fw-bold text-primary mb-0"><?php echo $reviewCount; ?></h3>
          </div></div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-6">
              <label class="form-label">Tìm kiếm</label>
              <input type="text" name="q" class="form-control" placeholder="Tên HLV, chuyên môn, SĐT, email" value="<?php echo h($keyword); ?>">
            </div>
            <div class="col-lg-3">
              <label class="form-label">Trạng thái</label>
              <select name="status" class="form-select">
                <option value="">Tất cả</option>
                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Tạm ngưng</option>
              </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
              <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Tìm / Lọc</button>
              <a href="trainers.php" class="btn btn-outline-secondary">Đặt lại</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0">Danh sách HLV</h5>
          <div class="d-flex gap-2">
            <a href="trainer-bookings.php" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-calendar-check me-1"></i>Lịch đặt
            </a>
            <a href="trainer-reviews.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-chat-square-quote me-1"></i>Đánh giá
            </a>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>HLV</th>
                  <th>Chuyên môn</th>
                  <th>Liên hệ</th>
                  <th>Kinh nghiệm</th>
                  <th>Đánh giá</th>
                  <th>Lịch đặt</th>
                  <th>Trạng thái</th>
                  <th class="text-end">Thao tác</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($trainers): ?>
                <?php foreach ($trainers as $trainer): ?>
                  <?php
                    $trainerId = (int) $trainer['id'];
                    $avatarUrl = resolve_trainer_avatar_url($trainerId, $trainer['avatar'] ?? '', '../');
                    $nextStatus = ($trainer['status'] ?? '') === 'active' ? 'inactive' : 'active';
                  ?>
                  <tr>
                    <td>
                      <div class="d-flex gap-3 align-items-center">
                        <img src="<?php echo h($avatarUrl); ?>" alt="<?php echo h($trainer['full_name']); ?>" class="trainer-admin-avatar">
                        <div>
                          <div class="fw-bold"><?php echo h($trainer['full_name']); ?></div>
                          <div class="trainer-admin-bio"><?php echo h($trainer['bio'] ?: 'Chưa có mô tả hồ sơ.'); ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?php echo h($trainer['specialty']); ?></td>
                    <td>
                      <div><?php echo h($trainer['phone'] ?: '-'); ?></div>
                      <div class="small text-muted"><?php echo h($trainer['email'] ?: ''); ?></div>
                    </td>
                    <td><?php echo (int) ($trainer['experience_years'] ?? 0); ?> năm</td>
                    <td>
                      <div class="fw-semibold text-warning">
                        <i class="bi bi-star-fill"></i>
                        <?php echo h(number_format((float) ($trainer['live_rating'] ?? $trainer['rating'] ?? 0), 1)); ?>
                      </div>
                      <div class="small text-muted"><?php echo (int) ($trainer['review_count'] ?? 0); ?> phản hồi</div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?php echo (int) ($trainer['booking_count'] ?? 0); ?></div>
                      <div class="small text-warning"><?php echo (int) ($trainer['pending_booking_count'] ?? 0); ?> chờ xử lý</div>
                    </td>
                    <td><?php echo admin_trainer_status_badge((string) ($trainer['status'] ?? 'inactive')); ?></td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2 flex-wrap">
                        <a href="../user/trainers/detail.php?id=<?php echo $trainerId; ?>" class="btn btn-info btn-sm" title="Xem trang user">
                          <i class="bi bi-eye"></i>
                        </a>
                        <a href="../php/trainers/edit-trainer.php?id=<?php echo $trainerId; ?>" class="btn btn-warning btn-sm" title="Sửa HLV">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="../php/trainers/toggle-status.php?id=<?php echo $trainerId; ?>&status=<?php echo h($nextStatus); ?>" class="btn btn-outline-secondary btn-sm" title="Đổi trạng thái">
                          <i class="bi bi-power"></i>
                        </a>
                        <form method="POST" action="../php/trainers/delete-trainer.php" onsubmit="return confirm('Xóa HLV này? Nếu đã có lịch/đánh giá, hệ thống sẽ tạm ngưng thay vì xóa.');">
                          <input type="hidden" name="id" value="<?php echo $trainerId; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                          <button type="submit" class="btn btn-danger btn-sm" title="Xóa HLV">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">Chưa có HLV phù hợp.</td>
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
<script src="../js/main.js"></script>
</body>
</html>
