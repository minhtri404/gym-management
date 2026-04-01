<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = $base_path ?? '';
?>

<aside class="sidebar p-3">
  <div class="brand-box mb-4">
    <div class="brand-icon">
      <i class="bi bi-barbell"></i>
    </div>
    <div>
      <h4 class="mb-0">Gym Admin</h4>
      <small>Management System</small>
    </div>
  </div>

  <ul class="nav flex-column sidebar-menu">
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="bi bi-house-door me-2"></i>Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>members.php" class="nav-link <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>">
        <i class="bi bi-people me-2"></i>Hội viên
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>packages.php" class="nav-link <?php echo ($current_page == 'packages.php') ? 'active' : ''; ?>">
        <i class="bi bi-box-seam me-2"></i>Gói tập
      </a>
    </li>

     <li class="nav-item">
      <a href="<?php echo $base_path; ?>workout-plans.php" class="nav-link <?php echo ($current_page == 'workout-plans.php') ? 'active' : ''; ?>">
        <i class="bi bi-clipboard2-pulse me-2"></i>Kế hoạch tập luyện
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>meal-plans.php" class="nav-link <?php echo ($current_page == 'meal-plans.php') ? 'active' : ''; ?>">
        <i class="bi bi-egg-fried me-2"></i>Kế hoạch dinh dưỡng
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>checkins.php" class="nav-link <?php echo ($current_page == 'checkins.php') ? 'active' : ''; ?>">
        <i class="bi bi-check-circle me-2"></i>Check-in
      </a>
    </li>
    <li class="nav-item mt-3">
      <a href="<?php echo $base_path; ?>logout.php" class="nav-link text-warning">
        <i class="bi bi-box-arrow-left me-2"></i>Logout
      </a>
    </li>
  </ul>
</aside>