<?php
$page_title = $page_title ?? 'Dashboard';
$admin_name = $_SESSION['admin_full_name'] ?? 'Admin';
?>

<nav class="navbar navbar-expand-lg topbar px-4">
  <div class="container-fluid p-0">
    <span class="navbar-brand fw-bold mb-0"><?php echo $page_title; ?></span>

    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-light position-relative">
        <i class="bi bi-bell"></i>
        <span class="notification-dot"></span>
      </button>

      <div class="admin-info d-flex align-items-center">
        <div class="admin-avatar me-2">A</div>
        <div>
          <div class="fw-semibold"><?php echo $admin_name; ?></div>
          <small class="text-muted">Quản trị viên</small>
        </div>
      </div>
    </div>
  </div>
</nav>