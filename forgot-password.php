<?php
include __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if (strtolower(trim($_SESSION['user_role'] ?? '')) === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }

    header('Location: user/home.php');
    exit;
}

$error = trim($_GET['error'] ?? '');
$success = trim($_GET['success'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quên mật khẩu - Gym Management</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css?v=light-1" />
</head>
<body class="login-page">

  <div class="container">
    <div class="row min-vh-100 justify-content-center align-items-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card login-card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
              <div class="logo-circle otp-logo-circle mb-3">
                <img src="assets/images/1.png" class="otp-logo-image" alt="OTP">
              </div>
              <h2 class="fw-bold">Quên mật khẩu</h2>
              <p class="text-muted mb-0">Nhập email để nhận mã OTP</p>
            </div>

            <?php if ($success !== ''): ?>
              <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
              </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="php/auth/send-reset-otp.php">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="mb-3">
                <label class="form-label">Email tài khoản</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                  </span>
                  <input
                    type="email"
                    class="form-control"
                    name="email"
                    placeholder="Nhập email đã đăng ký"
                    required
                  />
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-send me-2"></i>Gửi mã OTP
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
