<?php
include __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header('Location: dashboard.php');
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
  <title>Đăng ký - Gym Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="login-page">
  <div class="container">
    <div class="row min-vh-100 justify-content-center align-items-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-6">
        <div class="card login-card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <div class="logo-circle mb-3">
                <i class="bi bi-person-plus"></i>
              </div>
              <h2 class="fw-bold">Tạo tài khoản</h2>
              <p class="text-muted mb-0">Đăng ký tài khoản hội viên</p>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success">Đăng ký thành công. Bạn có thể đăng nhập ngay.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="php/auth/register.php">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="mb-3">
                <label class="form-label">Họ và tên</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" name="full_name" placeholder="Nhập họ và tên" required maxlength="100">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" name="email" placeholder="Nhập email" required maxlength="120">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Số điện thoại</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                  <input type="text" class="form-control" name="phone" placeholder="Nhập số điện thoại" maxlength="20">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" name="password" placeholder="Nhập mật khẩu" required minlength="6">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Xác nhận mật khẩu</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                  <input type="password" class="form-control" name="confirm_password" placeholder="Nhập lại mật khẩu" required minlength="6">
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-person-check me-2"></i>Đăng ký
              </button>
            </form>

            <div class="text-center">
              <a href="login.php" class="text-decoration-none">Đã có tài khoản? Đăng nhập</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>