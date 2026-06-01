<?php
if (!isset($conn)) {
    include __DIR__ . '/../../includes/config.php';
}
include_once __DIR__ . '/../../includes/functions/avatar-helper.php';

$base_path = $base_path ?? '../';
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? '';

if ($is_logged_in && isset($conn)) {
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

$avatar_url = resolve_user_avatar_url($user_avatar, $base_path, $user_name !== '' ? $user_name : 'User');
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
                    <a class="nav-link" href="<?php echo $base_path; ?>user/trainers/index.php">HLV</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>contact-form.php">Contact</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/dashboard/index.php">Khu v&#7921;c h&#7897;i vi&ecirc;n</a>
                </li>

                <?php if (!empty($_SESSION['user_id'])): ?>
                    <?php
                    $nav_avatar = resolve_user_avatar_url(
                        $_SESSION['user_avatar'] ?? '',
                        $base_path,
                        $_SESSION['user_name'] ?? 'User'
                    );

                    $nav_user_name = $_SESSION['user_name'] ?? 'Tai khoan';
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
                                    <i class="bi bi-person-circle me-2"></i>H&#7891; s&#417; c&aacute; nh&acirc;n
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/my-package/index.php">
                                    <i class="bi bi-box-seam me-2"></i>G&oacute;i c&#7911;a b&#7841;n
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/registrations/index.php">
                                    <i class="bi bi-card-checklist me-2"></i>L&#7883;ch s&#7917; &#273;&#259;ng k&yacute;
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/checkins/index.php">
                                    <i class="bi bi-calendar-check me-2"></i>L&#7883;ch s&#7917; check-in
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/trainers/my-bookings.php">
                                    <i class="bi bi-person-badge me-2"></i>L&#7883;ch s&#7917; HLV
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>user/contacts/index.php">
                                    <i class="bi bi-chat-left-text me-2"></i>L&#7883;ch s&#7917; li&ecirc;n h&#7879;
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo $base_path; ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>&#272;&#259;ng xu&#7845;t
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-hero-primary btn-sm" href="<?php echo $base_path; ?>login.php">&#272;&#259;ng nh&#7853;p</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
