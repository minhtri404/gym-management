<?php
$page_title = 'Quản lý hội viên';
include __DIR__ . '/../includes/auth-check.php';

$q = trim($_GET['q'] ?? '');
$filter_package = isset($_GET['package_id']) ? (int) ($_GET['package_id'] ?? 0) : 0;
$filter_status = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = (int) ($_GET['per_page'] ?? 15);
if ($per_page <= 0) {
    $per_page = 15;
}
if ($per_page > 100) {
    $per_page = 100;
}

$pkgs = [];
$pkgRes = $conn->query('SELECT id, package_name FROM packages ORDER BY package_name ASC');
if ($pkgRes) {
    while ($p = $pkgRes->fetch_assoc()) {
        $pkgs[] = $p;
    }
}

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(members.full_name LIKE ? OR members.phone LIKE ? OR members.email LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($filter_package > 0) {
    $where[] = 'members.package_id = ?';
    $params[] = $filter_package;
    $types .= 'i';
}

if ($filter_status !== '' && in_array($filter_status, ['active', 'expired', 'inactive'], true)) {
    $where[] = 'members.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$count_sql = "SELECT COUNT(*) AS total FROM members $where_sql";
$total = 0;

if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rowc = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $total = $rowc ? (int) ($rowc['total'] ?? 0) : 0;
} else {
    $res = $conn->query($count_sql);
    $rowc = $res ? $res->fetch_assoc() : null;
    $total = $rowc ? (int) ($rowc['total'] ?? 0) : 0;
}

$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$sql = "SELECT members.id, members.full_name, members.phone, members.email, members.status, packages.package_name
        FROM members
        LEFT JOIN packages ON members.package_id = packages.id
        $where_sql
        ORDER BY members.id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $bind_types = $types . 'ii';
    $bind_params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($bind_types, ...$bind_params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

function member_status_badge(string $status): string
{
    return match ($status) {
        'active' => '<span class="badge bg-success">Đang hoạt động</span>',
        'expired' => '<span class="badge bg-warning text-dark">Hết hạn</span>',
        default => '<span class="badge bg-secondary">Ngưng hoạt động</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Members - Gym Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="../css/style.css" />
</head>
<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include __DIR__ . '/../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <?php if (isset($_GET['add']) && $_GET['add'] === 'success'): ?>
          <div class="alert alert-success">Thêm hội viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['edit']) && $_GET['edit'] === 'success'): ?>
          <div class="alert alert-success">Cập nhật hội viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
          <div class="alert alert-success">Xóa hội viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'error'): ?>
          <div class="alert alert-danger">Xóa hội viên thất bại. Vui lòng kiểm tra dữ liệu liên quan rồi thử lại.</div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_success']) && $_GET['checkin_success'] === '1'): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Check-in thành công.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_duplicate']) && $_GET['checkin_duplicate'] === '1'): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>Hội viên đã check-in hôm nay rồi.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_error']) && $_GET['checkin_error'] === '1'): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle me-2"></i>Lỗi check-in, hội viên không tồn tại hoặc không hoạt động.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Danh sách hội viên</h5>
              <a class="btn btn-primary btn-sm" href="../php/members/add-member.php">
                <i class="bi bi-plus-circle me-2"></i>Thêm hội viên
              </a>
            </div>
          </div>
          <div class="card-body px-4 pb-4">
            <form method="GET" class="row g-2 mb-3 align-items-center">
              <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Tìm theo tên, SĐT, email" value="<?php echo htmlspecialchars($q); ?>">
              </div>
              <div class="col-md-3">
                <select name="package_id" class="form-select">
                  <option value="0">-- Tất cả gói --</option>
                  <?php foreach ($pkgs as $p): ?>
                    <option value="<?php echo (int) $p['id']; ?>" <?php echo $filter_package === (int) $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['package_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="status" class="form-select">
                  <option value="">-- Tất cả trạng thái --</option>
                  <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                  <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Hết hạn</option>
                  <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                </select>
              </div>
              <div class="col-md-1">
                <select name="per_page" class="form-select">
                  <?php foreach ([10, 15, 25, 50] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo $per_page === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-auto">
                <button class="btn btn-primary">Tìm / Lọc</button>
                <a href="members.php" class="btn btn-outline-secondary ms-1">Đặt lại</a>
              </div>
            </form>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Gói tập</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td>#<?php echo str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name'] ?? 'Chưa có gói'); ?></td>
                        <td><?php echo member_status_badge((string) ($row['status'] ?? 'inactive')); ?></td>
                        <td class="text-end">

                          <a class="btn btn-info btn-sm ms-1" href="../php/members/view-member.php?id=<?php echo (int) $row['id']; ?>" title="Xem chi tiết">
                            <i class="bi bi-eye"></i>
                          </a>

                          <a class="btn btn-warning btn-sm ms-1" href="../php/members/edit-member.php?id=<?php echo (int) $row['id']; ?>" title="Sửa hội viên">
                            <i class="bi bi-pencil"></i>
                          </a>

                          <form class="d-inline-block ms-1" method="POST" action="../php/members/delete-member.php" onsubmit="return confirm('Xóa hội viên này?');">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted">Chưa có hội viên nào.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="text-muted">Hiển thị <strong><?php echo $total > 0 ? (($offset + 1) . ' - ' . min($offset + $per_page, $total)) : '0'; ?></strong> trong <strong><?php echo $total; ?></strong> hội viên</div>
              <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                  <?php
                    $show = 7;
                    $start = max(1, $page - intdiv($show, 2));
                    $end = min($total_pages, $start + $show - 1);
                    if ($end - $start + 1 < $show) {
                      $start = max(1, $end - $show + 1);
                    }
                    $prev_disabled = $page <= 1 ? ' disabled' : '';
                    $qp = $_GET;
                    $qp['page'] = max(1, $page - 1);
                  ?>
                  <li class="page-item<?php echo $prev_disabled; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($qp)); ?>" aria-label="Previous">&laquo;</a>
                  </li>

                  <?php for ($p = $start; $p <= $end; $p++): ?>
                    <?php $qp = $_GET; $qp['page'] = $p; ?>
                    <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($qp)); ?>"><?php echo $p; ?></a></li>
                  <?php endfor; ?>

                  <?php $qp = $_GET; $qp['page'] = min($total_pages, $page + 1); $next_disabled = $page >= $total_pages ? ' disabled' : ''; ?>
                  <li class="page-item<?php echo $next_disabled; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($qp)); ?>" aria-label="Next">&raquo;</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="../js/main.js"></script>
</body>
</html>
