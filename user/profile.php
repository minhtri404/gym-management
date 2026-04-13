<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$base_path = '../';

include __DIR__ . '/../includes/config.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($full_name === '') {
        $error = 'Vui lòng nhập họ và tên.';
    } elseif ($phone === '') {
        $error = 'Vui lòng nhập số điện thoại.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không đúng định dạng.';
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt_update->bind_param('sssi', $full_name, $email, $phone, $user_id);

        if ($stmt_update->execute()) {
            $message = 'Cập nhật thông tin cá nhân thành công.';
            $_SESSION['user_name'] = $full_name;
        } else {
            $error = 'Không thể cập nhật thông tin cá nhân.';
        }

        $stmt_update->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $upload_dir = __DIR__ . '/../uploads/avatars/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!empty($_FILES['avatar']['name'])) {
        $file_name = $_FILES['avatar']['name'];
        $tmp_name = $_FILES['avatar']['tmp_name'];
        $file_size = (int) $_FILES['avatar']['size'];
        $file_error = (int) $_FILES['avatar']['error'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_error !== 0) {
            $error = 'Tải ảnh lên thất bại.';
        } elseif (!in_array($ext, $allowed_ext, true)) {
            $error = 'Chỉ chấp nhận file jpg, jpeg, png, webp.';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $error = 'Ảnh đại diện phải nhỏ hơn 2MB.';
        } else {
            $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($tmp_name, $target_file)) {
                $stmt_update = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt_update->bind_param('si', $new_file_name, $user_id);

                if ($stmt_update->execute()) {
                    $message = 'Cập nhật ảnh đại diện thành công.';
                    $_SESSION['user_avatar'] = $new_file_name;
                } else {
                    $error = 'Lưu ảnh đại diện vào database thất bại.';
                }

                $stmt_update->close();
            } else {
                $error = 'Không thể lưu file ảnh.';
            }
        }
    } else {
        $error = 'Vui lòng chọn ảnh trước khi tải lên.';
    }
}

$stmt = $conn->prepare("SELECT id, full_name, email, phone, role, avatar, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$avatar_path = !empty($user['avatar'])
    ? $base_path . 'uploads/avatars/' . $user['avatar']
    : 'https://via.placeholder.com/120x120.png?text=Avatar';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân - FLEXZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="includes/assets/css/user.css">
</head>
<body class="user-body">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="section-dark" style="padding-top: 130px; padding-bottom: 80px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <h2 class="fw-bold mb-4">Hồ sơ cá nhân</h2>

                        <?php if ($message !== ''): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($user): ?>
                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" class="rounded-circle border shadow-sm" width="120" height="120" style="object-fit: cover;">

                                <form method="POST" enctype="multipart/form-data" class="mt-3">
                                    <div class="mb-2">
                                        <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Cập nhật ảnh đại diện</button>
                                </form>
                            </div>

                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Họ và tên</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Số điện thoại</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" readonly>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Ngày tạo tài khoản</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['created_at'] ?? ''); ?>" readonly>
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                </div>
                            </form>

                            <div class="mt-4">
                                <a href="<?php echo $base_path; ?>user/home.php" class="btn btn-outline-secondary">Quay lại</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                Không tìm thấy thông tin tài khoản.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
