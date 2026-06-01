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

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>&#272;&#259;ng k&yacute; - Gym Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="css/style.css?v=register-readable-1" />
</head>
<body class="login-page register-page">
  <div class="container">
    <div class="row min-vh-100 justify-content-center align-items-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-6">
        <div class="card login-card shadow-lg border-0">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <div class="logo-circle mb-3">
                <i class="bi bi-person-plus"></i>
              </div>
              <h2 class="fw-bold">T&#7841;o t&agrave;i kho&#7843;n</h2>
              <p class="text-muted mb-0">&#272;&#259;ng k&yacute; t&agrave;i kho&#7843;n h&#7897;i vi&ecirc;n</p>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success">&#272;&#259;ng k&yacute; th&agrave;nh c&ocirc;ng. B&#7841;n c&oacute; th&#7875; &#273;&#259;ng nh&#7853;p ngay.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="php/auth/register.php">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="mb-3">
                <label class="form-label">H&#7885; v&agrave; t&ecirc;n</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" name="full_name" placeholder="Nh&#7853;p h&#7885; v&agrave; t&ecirc;n" required maxlength="100">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" name="email" placeholder="Nh&#7853;p email" required maxlength="120">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">S&#7889; &#273;i&#7879;n tho&#7841;i</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                  <input type="text" class="form-control" name="phone" placeholder="Nh&#7853;p s&#7889; &#273;i&#7879;n tho&#7841;i" maxlength="20">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">M&#7853;t kh&#7849;u</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" name="password" placeholder="Nh&#7853;p m&#7853;t kh&#7849;u" required minlength="6">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">X&aacute;c nh&#7853;n m&#7853;t kh&#7849;u</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                  <input type="password" class="form-control" name="confirm_password" placeholder="Nh&#7853;p l&#7841;i m&#7853;t kh&#7849;u" required minlength="6">
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-person-check me-2"></i>&#272;&#259;ng k&yacute;
              </button>
            </form>

            <div class="text-center">
              <a href="login.php" class="text-decoration-none">&#272;&atilde; c&oacute; t&agrave;i kho&#7843;n? &#272;&#259;ng nh&#7853;p</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

