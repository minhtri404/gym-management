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
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#about">About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/package/index.php">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#gallery">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#trainers">Trainers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>contact-form.php">Contact</a>
                </li>

                <?php if ($is_logged_in && $user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>dashboard.php">Admin</a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="#" class="social-link" aria-label="Facebook">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" class="social-link" aria-label="Instagram">
                    <i class="bi bi-instagram"></i>
                </a>

                <?php if ($is_logged_in): ?>
                    <a href="<?php echo $base_path; ?>user/profile.php" class="user-greeting text-decoration-none">
                        <?php if ($avatar_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="user-avatar me-2">
                        <?php else: ?>
                            <i class="bi bi-person-circle me-1"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($user_name); ?>
                    </a>

                    <a href="<?php echo $base_path; ?>logout.php" class="btn btn-user-outline btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Đăng xuất
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" class="btn btn-user-outline btn-sm">
                        Đăng nhập
                    </a>
                    <a href="<?php echo $base_path; ?>register.php" class="btn btn-hero-primary btn-sm">
                        Đăng ký
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

