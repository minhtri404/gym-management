<?php
include __DIR__ . '/../includes/config.php';

if (empty($_SESSION['reset_user_id'])) {
    header('Location: ../forgot-password.php');
    exit;
}

$error = trim($_GET['error'] ?? '');
$success = trim($_GET['success'] ?? '');
$email = $_SESSION['reset_user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đặt lại mật khẩu - Gym Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-page">

<div class="container">
  <div class="row min-vh-100 justify-content-center align-items-center">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5">
      <div class="card login-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

          <div class="text-center mb-4">
            <div class="logo-circle otp-logo-circle mb-3">
              <img src="../assets/images/1.png" class="otp-logo-image" alt="OTP">
            </div>
            <h2 class="fw-bold">Đặt lại mật khẩu</h2>
            <p class="text-muted mb-0">
              Nhập OTP đã gửi đến <strong><?php echo htmlspecialchars($email); ?></strong>
            </p>
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

          <form method="POST" action="../php/auth/reset-password.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-3">
              <label class="form-label">Mã OTP</label>
              <input
                type="text"
                name="otp"
                class="form-control text-center fs-4"
                maxlength="6"
                placeholder="------"
                required
              >
            </div>

            <div class="mb-3">
              <label class="form-label">Mật khẩu mới</label>
              <input
                type="password"
                name="new_password"
                class="form-control"
                required
                minlength="6"
              >
            </div>

            <div class="mb-3">
              <label class="form-label">Xác nhận mật khẩu mới</label>
              <input
                type="password"
                name="confirm_password"
                class="form-control"
                required
                minlength="6"
              >
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
              <i class="bi bi-check-circle me-2"></i>Cập nhật mật khẩu
            </button>
          </form>

          <div class="text-center">
            <a href="../forgot-password.php" class="text-decoration-none">Gửi lại OTP</a>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
