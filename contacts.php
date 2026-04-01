<?php
$page_title = "Liên hệ khách hàng";
include 'includes/auth-check.php';

$base_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die('CSRF token không hợp lệ.');
  }

  $action = trim($_POST['action'] ?? 'update');
  $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

  if ($action === 'delete') {
    if ($id > 0) {
      $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();
    }

    header("Location: contacts.php?delete=success");
    exit;
  }

  $status = trim($_POST['status'] ?? 'new');
  $admin_note = trim($_POST['admin_note'] ?? '');

  $allowed_statuses = ['new', 'contacted', 'closed'];
  if (!in_array($status, $allowed_statuses, true)) {
    $status = 'new';
  }

  if ($id > 0) {
    $stmt = $conn->prepare("UPDATE contact_messages SET status = ?, admin_note = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_note, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: contacts.php?update=success");
    exit;
  }
}
$total_result = $conn->query("SELECT COUNT(*) AS total FROM contact_messages");
$total_contacts = $total_result ? (int) $total_result->fetch_assoc()['total'] : 0;

$new_result = $conn->query("SELECT COUNT(*) AS total FROM contact_messages WHERE status = 'new'");
$new_contacts = $new_result ? (int) $new_result->fetch_assoc()['total'] : 0;

$contacted_result = $conn->query("SELECT COUNT(*) AS total FROM contact_messages WHERE status = 'contacted'");
$contacted_contacts = $contacted_result ? (int) $contacted_result->fetch_assoc()['total'] : 0;

$closed_result = $conn->query("SELECT COUNT(*) AS total FROM contact_messages WHERE status = 'closed'");
$closed_contacts = $closed_result ? (int) $closed_result->fetch_assoc()['total'] : 0;

$filter_status = trim($_GET['status'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, ['new', 'contacted', 'closed'], true)) {
  $where_conditions[] = "status = ?";
  $params[] = $filter_status;
  $types .= 's';
}

if ($keyword !== '') {
  $where_conditions[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR subject LIKE ?)";
  $keyword_like = '%' . $keyword . '%';
  $params[] = $keyword_like;
  $params[] = $keyword_like;
  $params[] = $keyword_like;
  $params[] = $keyword_like;
  $types .= 'ssss';
}

$where_sql = '';
if (!empty($where_conditions)) {
  $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT id, full_name, phone, email, subject, message, preferred_contact_method, status, admin_note, created_at
        FROM contact_messages
        $where_sql
        ORDER BY id DESC";

if (!empty($params)) {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Liên hệ khách hàng - Gym Management</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body class="dashboard-page">

  <div class="d-flex dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include 'includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
          <div class="alert alert-success">Cập nhật liên hệ thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
          <div class="alert alert-success">Xóa liên hệ thành công.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="mb-1">Quản lý liên hệ khách hàng</h4>
            <p class="text-muted mb-0">Theo dõi yêu cầu liên hệ, tình trạng xử lý và ghi chú chăm sóc khách hàng.</p>
          </div>
          <div>
            <a href="contact-form.php" target="_blank" class="btn btn-outline-primary">
              Xem form liên hệ public
            </a>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Tổng yêu cầu</div>
                <h3 class="mb-0"><?php echo $total_contacts; ?></h3>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Mới</div>
                <h3 class="mb-0 text-primary"><?php echo $new_contacts; ?></h3>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Đã liên hệ</div>
                <h3 class="mb-0 text-warning"><?php echo $contacted_contacts; ?></h3>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Đã đóng</div>
                <h3 class="mb-0 text-success"><?php echo $closed_contacts; ?></h3>
              </div>
            </div>
          </div>
        </div>

        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Tìm kiếm</label>
            <input
              type="text"
              name="keyword"
              class="form-control"
              placeholder="Tên / SĐT / Email / Chủ đề"
              value="<?php echo htmlspecialchars($keyword); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Lọc theo trạng thái</label>
            <select name="status" class="form-select">
              <option value="">Tất cả</option>
              <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>Mới</option>
              <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
              <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
            </select>
          </div>

          <div class="col-md-auto">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-search me-1"></i> Tìm / Lọc
            </button>
          </div>

          <div class="col-md-auto">
            <a href="contacts.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
          </div>
        </form>
                      

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0">Danh sách yêu cầu liên hệ</h5>
          </div>
          <div class="card-body px-4 pb-4">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Khách hàng</th>
                    <th>Chủ đề</th>
                    <th>Nội dung</th>
                    <th>Trạng thái</th>
                    <th>Ngày gửi</th>
                    <th class="text-end">Xử lý</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                          <div class="small text-muted">SĐT: <?php echo htmlspecialchars($row['phone']); ?></div>
                          <div class="small text-muted">Email: <?php echo htmlspecialchars($row['email'] ?: 'Chưa có'); ?></div>
                          <div class="small text-muted">Ưu tiên: <?php echo htmlspecialchars($row['preferred_contact_method']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td style="min-width: 240px;">
                          <div><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
                          <?php if (!empty($row['admin_note'])): ?>
                            <div class="mt-2 small text-muted"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($row['admin_note']); ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($row['status'] === 'new'): ?>
                            <span class="badge bg-primary">Mới</span>
                          <?php elseif ($row['status'] === 'contacted'): ?>
                            <span class="badge bg-warning text-dark">Đã liên hệ</span>
                          <?php else: ?>
                            <span class="badge bg-success">Đã đóng</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        <td class="text-end" style="min-width: 320px;">
                          <form method="POST" class="row g-2 justify-content-end">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="col-12">
                              <select name="status" class="form-select form-select-sm">
                                <option value="new" <?php echo $row['status'] === 'new' ? 'selected' : ''; ?>>Mới</option>
                                <option value="contacted" <?php echo $row['status'] === 'contacted' ? 'selected' : ''; ?>>Đã liên hệ</option>
                                <option value="closed" <?php echo $row['status'] === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                              </select>
                            </div>

                            <div class="col-12">
                              <input
                                type="text"
                                name="admin_note"
                                class="form-control form-control-sm"
                                placeholder="Ghi chú xử lý"
                                value="<?php echo htmlspecialchars($row['admin_note'] ?? ''); ?>">
                            </div>

                            <div class="col-12">
                              <input type="hidden" name="action" value="update">
                              <button type="submit" class="btn btn-sm btn-warning w-100">
                                <i class="bi bi-save me-1"></i> Cập nhật
                              </button>
                            </div>
                          </form>
                          <form method="POST" class="mt-2" onsubmit="return confirm('Xóa liên hệ này?');">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                              <i class="bi bi-trash me-1"></i> Xóa
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted">Chưa có yêu cầu liên hệ nào.</td>
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


