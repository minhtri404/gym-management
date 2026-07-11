<?php
include __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if (strtolower(trim($_SESSION['user_role'] ?? '')) === 'admin') {
        header('Location: admin/dashboard');
        exit;
    }

    header('Location: user/home');
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
  <title>&#272;&#259;ng nh&#7853;p - Gym Management</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css?v=light-1" />
</head>
<body class="login-page login-dev-page">

  <div class="container">
    <div class="row min-vh-100 justify-content-center align-items-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">
        <div class="card login-card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <div class="logo-circle mb-3">
                <img src="assets/images/1.png" alt="logo" class="login-logo">
              </div>
              <h2 class="fw-bold">Gym Management</h2>
              <p class="text-muted mb-0">&#272;&#259;ng nh&#7853;p h&#7879; th&#7889;ng</p>
            </div>
            <div class="login-divider"><span>ho&#7863;c</span></div>

<a href="php/auth/google-redirect.php" class="btn login-google-btn w-100 mb-4">
  <i class="bi bi-google me-2"></i>&#272;&#259;ng nh&#7853;p v&#7899;i Google
</a>
<?php if (isset($_GET['reset_success'])): ?>
  <div class="alert alert-success">
    &#272;&#7893;i m&#7853;t kh&#7849;u th&agrave;nh c&ocirc;ng. B&#7841;n c&oacute; th&#7875; &#273;&#259;ng nh&#7853;p b&#7857;ng m&#7853;t kh&#7849;u m&#7899;i.
  </div>
<?php endif; ?>
            <?php if ($success): ?>
              <div class="alert alert-success">Thao t&aacute;c th&agrave;nh c&ocirc;ng. B&#7841;n c&oacute; th&#7875; &#273;&#259;ng nh&#7853;p ngay.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="php/auth/login.php">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="mb-3">
                <label class="form-label">Email ho&#7863;c s&#7889; &#273;i&#7879;n tho&#7841;i</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                  </span>
                  <input
                    type="text"
                    class="form-control"
                    name="email_or_phone"
                    placeholder="Nh&#7853;p email ho&#7863;c s&#7889; &#273;i&#7879;n tho&#7841;i"
                    required
                  />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">M&#7853;t kh&#7849;u</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                  </span>
                  <input
                    type="password"
                    class="form-control"
                    name="password"
                    placeholder="Nh&#7853;p m&#7853;t kh&#7849;u"
                    required
                  />
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="forgot-password" class="text-decoration-none small">Qu&ecirc;n m&#7853;t kh&#7849;u?</a>
                <a href="register" class="text-decoration-none small">T&#7841;o t&agrave;i kho&#7843;n</a>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>&#272;&#259;ng nh&#7853;p
              </button>
            </form>

           
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>

