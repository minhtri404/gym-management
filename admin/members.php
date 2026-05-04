<?php
$page_title = "Qu?n lý h?i viên";
include __DIR__ . '/../includes/auth-check.php';

// --- Filtering / Search / Pagination for admin members list ---
// Read filters from GET
$q = trim($_GET['q'] ?? ''); // search keyword (name/phone/email)
$filter_package = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$filter_status = trim($_GET['status'] ?? ''); // active|expired|inactive or empty

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
if ($per_page <= 0) $per_page = 15;
if ($per_page > 100) $per_page = 100;

// fetch packages for filter dropdown
$pkgs = [];
$pkgRes = $conn->query("SELECT id, package_name FROM packages ORDER BY package_name ASC");
if ($pkgRes) {
  while ($p = $pkgRes->fetch_assoc()) $pkgs[] = $p;
}

// Build WHERE clauses dynamically with prepared params
$where = [];
$params = [];
$types = '';

if ($q !== '') {
  $where[] = "(members.full_name LIKE ? OR members.phone LIKE ? OR members.email LIKE ? )";
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}

if ($filter_package > 0) {
  $where[] = 'members.package_id = ?';
  $params[] = $filter_package;
  $types .= 'i';
}

if ($filter_status !== '') {
  // allow mapping from friendly values to DB values
  $allowed = ['active','expired','inactive'];
  if (in_array($filter_status, $allowed, true)) {
    $where[] = 'members.status = ?';
    $params[] = $filter_status;
    $types .= 's';
  }
}

$where_sql = '';
if (!empty($where)) $where_sql = 'WHERE ' . implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) AS total FROM members $where_sql";
$total = 0;
if (!empty($params)) {
  $stmt = $conn->prepare($count_sql);
  // bind dynamic
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rowc = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  $total = $rowc ? (int)$rowc['total'] : 0;
} else {
  $res = $conn->query($count_sql);
  $rowc = $res ? $res->fetch_assoc() : null;
  $total = $rowc ? (int)$rowc['total'] : 0;
}

$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Main query with limit/offset
$sql = "SELECT members.id, members.full_name, members.phone, members.email, members.status, packages.package_name
    FROM members
    LEFT JOIN packages ON members.package_id = packages.id
    $where_sql
    ORDER BY members.id DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
// bind params + two integers for limit/offset
if (!empty($params)) {
  $bind_types = $types . 'ii';
  $bind_params = array_merge($params, [$per_page, $offset]);
  $stmt->bind_param($bind_types, ...$bind_params);
} else {
  $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
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
          <div class="alert alert-success">Thêm h?i viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['edit']) && $_GET['edit'] === 'success'): ?>
          <div class="alert alert-success">C?p nh?t h?i viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
          <div class="alert alert-success">Xóa h?i viên thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_success']) && $_GET['checkin_success'] === '1'): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Check-in thành công!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_duplicate']) && $_GET['checkin_duplicate'] === '1'): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>H?i viên dã check-in hôm nay r?i!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['checkin_error']) && $_GET['checkin_error'] === '1'): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle me-2"></i>L?i check-in, h?i viên không t?n t?i ho?c không ho?t d?ng!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Danh sách h?i viên</h5>
              <a class="btn btn-primary btn-sm" href="php/members/add-member.php">
                <i class="bi bi-plus-circle me-2"></i>Thêm h?i viên
              </a>
            </div>
          </div>
          <div class="card-body px-4 pb-4">
            <form method="GET" class="row g-2 mb-3 align-items-center">
              <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Tìm theo tên, SÐT, email" value="<?php echo htmlspecialchars($q); ?>">
              </div>
              <div class="col-md-3">
                <select name="package_id" class="form-select">
                  <option value="0">-- T?t c? gói --</option>
                  <?php foreach ($pkgs as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo $filter_package === (int)$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['package_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="status" class="form-select">
                  <option value="">-- T?t c? tr?ng thái --</option>
                  <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Ðang ho?t d?ng</option>
                  <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>H?t h?n</option>
                  <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Ngung ho?t d?ng</option>
                </select>
              </div>
              <div class="col-md-1">
                <select name="per_page" class="form-select">
                  <?php foreach ([10,15,25,50] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo $per_page == $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-auto">
                <button class="btn btn-primary">Tìm / L?c</button>
                <a href="members.php" class="btn btn-outline-secondary ms-1">Ð?t l?i</a>
              </div>
            </form>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>H? tên</th>
                    <th>S? di?n tho?i</th>
                    <th>Email</th>
                    <th>Gói t?p</th>
                    <th>Tr?ng thái</th>
                    <th class="text-end">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name'] ?? 'Chua có gói'); ?></td>
                        <td>
                          <?php
                          if ($row['status'] === 'active') {
                            echo '<span class="badge bg-success">Ðang ho?t d?ng</span>';
                          } elseif ($row['status'] === 'expired') {
                            echo '<span class="badge bg-warning text-dark">H?t h?n</span>';
                          } else {
                            echo '<span class="badge bg-secondary">Ngung ho?t d?ng</span>';
                          }
                          ?>
                        </td>
                        <td class="text-end">
                          <?php if ($row['status'] === 'active'): ?>
                            <?php
                              // Check if there is an active (not checked out) checkin for this member
                              $stmtActive = $conn->prepare("SELECT id FROM checkins WHERE member_id = ? AND checkout_time IS NULL ORDER BY id DESC LIMIT 1");
                              $stmtActive->bind_param("i", $row['id']);
                              $stmtActive->execute();
                              $resActive = $stmtActive->get_result();
                              $activeCheck = $resActive ? $resActive->fetch_assoc() : null;
                              $stmtActive->close();
                            ?>
                            <?php if ($activeCheck): ?>
                              <a class="btn btn-secondary btn-sm" href="php/checkins/quick-checkout.php?member_id=<?php echo (int) $row['id']; ?>" title="Check-out nhanh">
                                <i class="bi bi-box-arrow-right me-1"></i>Check-out
                              </a>
                            <?php else: ?>
                              <a class="btn btn-success btn-sm" href="php/checkins/quick-checkin.php?member_id=<?php echo (int) $row['id']; ?>" title="Check-in nhanh">
                                <i class="bi bi-check-circle me-1"></i>Check-in
                              </a>
                            <?php endif; ?>
                          <?php endif; ?>

                          <a class="btn btn-info btn-sm ms-1" href="php/members/view-member.php?id=<?php echo (int) $row['id']; ?>" title="Xem chi ti?t">
                            <i class="bi bi-eye"></i>
                          </a>

                          <a class="btn btn-warning btn-sm ms-1" href="php/members/edit-member.php?id=<?php echo (int) $row['id']; ?>">
                            <i class="bi bi-pencil"></i>
                          </a>

                          <form class="d-inline-block ms-1" method="POST" action="php/members/delete-member.php" onsubmit="return confirm('Xóa h?i viên này?');">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted">Chua có h?i viên nào.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="text-muted">Hi?n th? <strong><?php echo ($total > 0) ? (($offset + 1) . ' - ' . min($offset + $per_page, $total)) : '0'; ?></strong> trong <strong><?php echo $total; ?></strong> h?i viên</div>
              <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                  <?php
                    $show = 7; // max page links to show
                    $start = max(1, $page - intval($show/2));
                    $end = min($total_pages, $start + $show - 1);
                    if ($end - $start + 1 < $show) $start = max(1, $end - $show + 1);

                    // previous
                    $prev_disabled = $page <= 1 ? ' disabled' : '';
                    $qp = $_GET; $qp['page'] = max(1, $page - 1);
                  ?>
                  <li class="page-item<?php echo $prev_disabled; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($qp)); ?>" aria-label="Previous">&laquo;</a>
                  </li>

                  <?php for ($p = $start; $p <= $end; $p++):
                    $qp = $_GET; $qp['page'] = $p; ?>
                    <li class="page-item<?php echo $p == $page ? ' active' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($qp)); ?>"><?php echo $p; ?></a></li>
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

