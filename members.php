<?php
$page_title = "Quản lý hội viên";
include 'includes/auth-check.php';
$sql = "SELECT members.id, members.full_name, members.phone, members.status, packages.package_name
        FROM members
        LEFT JOIN packages ON members.package_id = packages.id
        ORDER BY members.id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Members - Gym Management</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet" />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body class="dashboard-page">

  <div class="d-flex dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include 'includes/navbar.php'; ?>

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

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Danh sách hội viên</h5>
              <a class="btn btn-primary btn-sm" href="php/members/add-member.php">
                <i class="bi bi-plus-circle me-2"></i>Thêm hội viên
              </a>
            </div>
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
                    <th class="text-end">Thao tác</th>
                      <th>Hành động</th>        
                  </tr>
                </thead>
                <tbody>
                  <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                      <tr>
                        <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['package_name'] ?? 'Chưa có gói'); ?></td>
                        <td>
                          <?php
                          if ($row['status'] === 'active') {
                            echo '<span class="badge bg-success">Đang hoạt động</span>';
                          } elseif ($row['status'] === 'expired') {
                            echo '<span class="badge bg-warning text-dark">Hết hạn</span>';
                          } else {
                            echo '<span class="badge bg-secondary">Ngưng hoạt động</span>';
                          }
                          ?>
                        </td>
                        <td class="text-end">
                          <a class="btn btn-warning btn-sm" href="php/members/edit-member.php?id=<?php echo (int) $row['id']; ?>">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <a
                            class="btn btn-danger btn-sm ms-1"
                            href="php/members/delete-member.php?id=<?php echo (int) $row['id']; ?>"
                            onclick="return confirm('Xóa hội viên này?');"
                          >
                            <i class="bi bi-trash"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted">Chưa có hội viên nào.</td>
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
