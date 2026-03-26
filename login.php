<?php
include 'includes/config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = trim($_POST['username']);
    $input_password = trim($_POST['password']);

    if ($input_username == "" || $input_password == "") {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
        $sql = "SELECT * FROM admins WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $input_username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();

            // Day 4: kiểm tra password dạng thường để hiểu luồng trước
            if ($input_password === $admin['password']) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_full_name'] = $admin['full_name'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Sai mật khẩu.";
            }
        } else {
            $error = "Tài khoản không tồn tại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Gym Management</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  />
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
                <i class="bi bi-barbell"></i>
              </div>
              <h2 class="fw-bold">Gym Management</h2>
              <p class="text-muted mb-0">Hệ thống quản lý phòng gym</p>
            </div>

            <?php if (!empty($error)) : ?>
              <div class="alert alert-danger">
                <?php echo $error; ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="">
              <div class="mb-3">
                <label class="form-label">Tên đăng nhập</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="bi bi-person"></i>
                  </span>
                  <input
                    type="text"
                    class="form-control"
                    name="username"
                    placeholder="Nhập tên đăng nhập"
                    required
                  />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                  </span>
                  <input
                    type="password"
                    class="form-control"
                    name="password"
                    placeholder="Nhập mật khẩu"
                    required
                  />
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
              </button>
            </form>

            <p class="text-center mt-4 mb-0 text-muted small">
              Tài khoản test: admin / 123456
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>