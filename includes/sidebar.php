<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = $base_path ?? '';
$logout_path = $logout_path ?? ($base_path !== '' ? rtrim(dirname(rtrim($base_path, '/')), '/') . '/logout.php' : '../logout.php');
$root_base_path = $root_base_path ?? '../';

include_once __DIR__ . '/functions/avatar-helper.php';

if (!function_exists('sidebar_h')) {
    function sidebar_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$admin_name = trim((string) ($_SESSION['admin_full_name'] ?? $_SESSION['user_name'] ?? 'Quản trị viên'));
$admin_avatar = trim((string) ($_SESSION['user_avatar'] ?? ''));

if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    $admin_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT full_name, avatar FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result ? $result->fetch_assoc() : null;
        if ($admin) {
            $admin_name = trim((string) ($admin['full_name'] ?? $admin_name)) ?: $admin_name;
            $admin_avatar = trim((string) ($admin['avatar'] ?? $admin_avatar));
            $_SESSION['user_name'] = $admin_name;
            $_SESSION['admin_full_name'] = $admin_name;
            $_SESSION['user_avatar'] = $admin_avatar;
        }
        $stmt->close();
    }
}

$admin_avatar_url = resolve_user_avatar_url($admin_avatar, $root_base_path, $admin_name, 'ffd9b3', '1f2937');
?>

<aside class="sidebar p-3">
  <div class="brand-box mb-4">
    <div class="brand-icon">
      <i class="bi bi-barbell"></i>
    </div>
    <div>
      <h4 class="mb-0">Qu&#7843;n tr&#7883; Gym</h4>
      <small>H&#7879; th&#7889;ng qu&#7843;n l&yacute;</small>
    </div>
  </div>

  <div class="sidebar-admin-card mb-4">
    <img src="<?php echo sidebar_h($admin_avatar_url); ?>" alt="<?php echo sidebar_h($admin_name); ?>" class="sidebar-admin-avatar">
    <div class="sidebar-admin-meta">
      <div class="sidebar-admin-name"><?php echo sidebar_h($admin_name); ?></div>
      <div class="sidebar-admin-role">Qu&#7843;n tr&#7883; vi&ecirc;n</div>
    </div>
  </div>

  <ul class="nav flex-column sidebar-menu">
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>dashboard" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="bi bi-house-door me-2"></i>T&#7893;ng quan
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>members" class="nav-link <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>">
        <i class="bi bi-people me-2"></i>Hội viên
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>packages" class="nav-link <?php echo ($current_page == 'packages.php') ? 'active' : ''; ?>">
        <i class="bi bi-box-seam me-2"></i>Gói tập
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>banners" class="nav-link <?php echo ($current_page == 'banners.php') ? 'active' : ''; ?>">
        <i class="bi bi-images me-2"></i>Banner FE
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>workout-plans" class="nav-link <?php echo ($current_page == 'workout-plans.php') ? 'active' : ''; ?>">
        <i class="bi bi-clipboard2-pulse me-2"></i>Kế hoạch tập luyện
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>meal-plans" class="nav-link <?php echo ($current_page == 'meal-plans.php') ? 'active' : ''; ?>">
        <i class="bi bi-egg-fried me-2"></i>Kế hoạch dinh dưỡng
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>checkins" class="nav-link <?php echo ($current_page == 'checkins.php') ? 'active' : ''; ?>">
        <i class="bi bi-check-circle me-2"></i>Check-in
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>contacts" class="nav-link <?php echo ($current_page == 'contacts.php') ? 'active' : ''; ?>">
        <i class="bi bi-telephone me-2"></i>Liên hệ
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>package-registrations" class="nav-link <?php echo ($current_page == 'package-registrations.php') ? 'active' : ''; ?>">
        <i class="bi bi-bag-check me-2"></i>Đăng ký gói
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>trainers" class="nav-link <?php echo ($current_page == 'trainers.php') ? 'active' : ''; ?>">
        <i class="bi bi-person-badge me-2"></i>Huấn luyện viên
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>trainer-bookings" class="nav-link <?php echo ($current_page == 'trainer-bookings.php') ? 'active' : ''; ?>">
        <i class="bi bi-calendar-check me-2"></i>Lịch đặt HLV
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>trainer-reviews" class="nav-link <?php echo ($current_page == 'trainer-reviews.php') ? 'active' : ''; ?>">
        <i class="bi bi-chat-square-quote me-2"></i>Đánh giá HLV
      </a>
    </li>
    <li class="nav-item">
      <a href="<?php echo $base_path; ?>../user/home" class="nav-link">
        <i class="bi bi-arrow-up-right-circle me-2"></i>Về giao diện user
      </a>
    </li>
    <li class="nav-item mt-3">
      <a href="<?php echo $logout_path; ?>" class="nav-link text-warning">
        <i class="bi bi-box-arrow-left me-2"></i>&#272;&#259;ng xu&#7845;t
      </a>
    </li>
  </ul>
</aside>
