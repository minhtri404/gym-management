<?php
if (!isset($conn)) {
    include __DIR__ . '/../../includes/config.php';
}

$base_path = $base_path ?? '../';
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? '';

if ($is_logged_in && $user_avatar === '' && isset($conn)) {
    $avatar_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    if ($avatar_stmt) {
        $avatar_user_id = (int) $_SESSION['user_id'];
        $avatar_stmt->bind_param('i', $avatar_user_id);
        $avatar_stmt->execute();
        $avatar_result = $avatar_stmt->get_result()->fetch_assoc();
        $user_avatar = $avatar_result['avatar'] ?? '';
        $_SESSION['user_avatar'] = $user_avatar;
        $avatar_stmt->close();
    }
}

$avatar_url = $user_avatar !== '' ? $base_path . 'uploads/avatars/' . $user_avatar : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark user-navbar fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $base_path; ?>user/home.php">
            <span class="brand-dot me-2"></span>
            FLEXZONE
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar" aria-controls="userNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/package/index.php">Packages</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/plans/index.php">Plans</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>contact-form.php">Contact</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/dashboard/index.php">Dashboard</a>
                </li>

                <?php if (!empty($_SESSION['user_id'])): ?>
                    <?php
                    $nav_avatar = !empty($_SESSION['user_avatar'])
                        ? $base_path . 'uploads/avatars/' . $_SESSION['user_avatar']
                        : 'https://via.placeholder.com/40x40.png?text=U';

                    $nav_user_name = $_SESSION['user_name'] ?? 'Tài khoản';
                    ?>
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 user-nav-trigger"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <img src="<?php echo htmlspecialchars($nav_avatar); ?>"
                                alt="Avatar"
                                class="user-nav-avatar">
                            <span class="user-nav-name"><?php echo htmlspecialchars($nav_user_name); ?></span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/profile.php">
                                    <i class="bi bi-person-circle me-2"></i>Hồ sơ cá nhân
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/my-package/index.php">
                                    <i class="bi bi-box-seam me-2"></i>Gói của bạn
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/registrations/index.php">
                                    <i class="bi bi-card-checklist me-2"></i>Lịch sử đăng ký
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/checkins/index.php">
                                    <i class="bi bi-calendar-check me-2"></i>Lịch sử check-in
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo $base_path; ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-hero-primary btn-sm" href="<?php echo $base_path; ?>login.php">Đăng nhập</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    </div>
</nav>