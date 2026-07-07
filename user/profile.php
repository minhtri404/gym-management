<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$base_path = '../';

include __DIR__ . '/../includes/config.php';
include __DIR__ . '/../includes/functions/avatar-helper.php';

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        http_response_code(403);
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && ($_POST['action'] ?? '') === 'update_profile') {
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
        $stmt_update = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && ($_POST['action'] ?? '') === 'update_avatar') {
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $maxAvatarSize = 5 * 1024 * 1024;
    $upload_dir = __DIR__ . '/../uploads/avatars/';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        $error = 'Không thể tạo thư mục lưu ảnh đại diện.';
    } elseif (!is_writable($upload_dir)) {
        $error = 'Thư mục lưu ảnh đại diện không có quyền ghi.';
    } elseif (!isset($_FILES['avatar']) || empty($_FILES['avatar']['name'])) {
        $error = 'Vui lòng chọn ảnh trước khi tải lên.';
    } else {
        $tmp_name = $_FILES['avatar']['tmp_name'];
        $file_size = (int)$_FILES['avatar']['size'];
        $file_error = (int)$_FILES['avatar']['error'];

        if ($file_error !== UPLOAD_ERR_OK) {
            $error = 'Tải ảnh lên thất bại.';
        } elseif ($file_size <= 0) {
            $error = 'File tải lên không hợp lệ.';
        } elseif ($file_size > $maxAvatarSize) {
            $error = 'Ảnh đại diện phải nhỏ hơn 5MB.';
        } elseif (!is_uploaded_file($tmp_name)) {
            $error = 'File tải lên không hợp lệ.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) {
                finfo_close($finfo);
            }

            if (!isset($allowedMimeTypes[$mimeType])) {
                $error = 'Chỉ chấp nhận file JPG, JPEG, PNG hoặc WEBP.';
            } else {
                $stmtCurrent = $conn->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
                $old_avatar = '';

                if ($stmtCurrent) {
                    $stmtCurrent->bind_param('i', $user_id);
                    $stmtCurrent->execute();
                    $currentUser = $stmtCurrent->get_result()->fetch_assoc();
                    $old_avatar = $currentUser['avatar'] ?? '';
                    $stmtCurrent->close();
                }

                $ext = $allowedMimeTypes[$mimeType];
                $new_file_name = 'avatar_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($tmp_name, $target_file)) {
                    @chmod($target_file, 0644);
                    $stmt_update = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt_update->bind_param('si', $new_file_name, $user_id);

                    if ($stmt_update->execute()) {
                        if (is_local_avatar_file($old_avatar)) {
                            $old_avatar_path = $upload_dir . $old_avatar;
                            if (is_file($old_avatar_path) && $old_avatar !== $new_file_name) {
                                @unlink($old_avatar_path);
                            }
                        }

                        $message = 'Cập nhật ảnh đại diện thành công.';
                        $_SESSION['user_avatar'] = $new_file_name;
                    } else {
                        @unlink($target_file);
                        $error = 'Lưu ảnh đại diện vào database thất bại.';
                    }

                    $stmt_update->close();
                } else {
                    $error = 'Không thể lưu file ảnh.';
                }
            }
        }
    }
}

$stmt = $conn->prepare('SELECT id, full_name, email, phone, role, avatar, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$avatar_path = resolve_user_avatar_url(
    $user['avatar'] ?? '',
    $base_path,
    $user['full_name'] ?? 'User',
    '0f172a',
    'ffffff'
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>H&#7891; s&#417; c&aacute; nh&acirc;n - FLEXZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="includes/assets/css/user.css?v=light-1">
</head>
<body class="user-body">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="section-dark" style="padding-top: 130px; padding-bottom: 80px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <h2 class="fw-bold mb-4">H&#7891; s&#417; c&aacute; nh&acirc;n</h2>

                        <?php if ($message !== ''): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($user): ?>
                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($avatar_path, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="rounded-circle border shadow-sm" width="120" height="120" style="object-fit: cover;">

                                <form method="POST" enctype="multipart/form-data" class="mt-3">
                                    <input type="hidden" name="action" value="update_avatar">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-2">
                                        <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                                    </div>
                                    <div class="small text-muted mb-2">H&#7895; tr&#7907;: JPG, PNG, WEBP. K&iacute;ch th&#432;&#7899;c t&#7889;i &#273;a 5MB.</div>
                                    <button type="submit" class="btn btn-primary btn-sm">C&#7853;p nh&#7853;t &#7843;nh &#273;&#7841;i di&#7879;n</button>
                                </form>
                            </div>

                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">H&#7885; v&agrave; t&ecirc;n</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">S&#7889; &#273;i&#7879;n tho&#7841;i</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai tr&ograve;</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Ng&agrave;y t&#7841;o t&agrave;i kho&#7843;n</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">L&#432;u thay &#273;&#7893;i</button>
                                </div>
                            </form>

                            <div class="mt-4">
                                <a href="<?php echo $base_path; ?>user/home" class="btn btn-outline-secondary">Quay l&#7841;i</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                Kh&ocirc;ng t&igrave;m th&#7845;y th&ocirc;ng tin t&agrave;i kho&#7843;n.
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
