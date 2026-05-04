<?php
include __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }

    header('Location: user/home.php');
    exit;
}

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quên mật khẩu - Gym Management</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="login-page">

  <div class="container">
    <div class="row min-vh-100 justify-content-center align-items-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card login-card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <div class="logo-circle mb-3">
                <i class="bi bi-key"></i>
              </div>
              <h2 class="fw-bold">Quên mật khẩu</h2>
              <p class="text-muted mb-0">Đổi mật khẩu bằng email</p>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success">Đổi mật khẩu thành công. Bạn có thể đăng nhập lại.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="php/auth/forgot-password.php">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" name="email" placeholder="Nhập email tài khoản" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Mật khẩu mới</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" name="new_password" placeholder="Nhập mật khẩu mới" required minlength="6">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Xác nhận mật khẩu mới</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                  <input type="password" class="form-control" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required minlength="6">
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-arrow-repeat me-2"></i>Đổi mật khẩu
              </button>
            </form>

            <div class="text-center">
              <a href="login.php" class="text-decoration-none">Quay lại đăng nhập</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
