<?php
$page_title = 'Sửa huấn luyện viên';
include __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions/trainer-image-helper.php';

$base_path = '../../admin/';
$root_base_path = '../../';
$error = '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($id <= 0) {
    header('Location: ' . $base_path . 'trainers.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, full_name, avatar, specialty, experience_years, phone, email, bio, rating, total_members, status FROM trainers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trainer) {
    header('Location: ' . $base_path . 'trainers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $specialty = trim((string) ($_POST['specialty'] ?? ''));
    $experience_years = max(0, (int) ($_POST['experience_years'] ?? 0));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $rating = (float) ($_POST['rating'] ?? 5);
    $total_members = max(0, (int) ($_POST['total_members'] ?? 0));
    $status = trim((string) ($_POST['status'] ?? 'active'));
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    $upload_dir = __DIR__ . '/../../uploads/trainers/';
    $next_image = (string) ($trainer['avatar'] ?? '');

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($full_name === '' || $specialty === '') {
        $error = 'Vui lòng nhập tên HLV và chuyên môn.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email HLV không hợp lệ.';
    } elseif ($rating < 0 || $rating > 5) {
        $error = 'Đánh giá mặc định phải nằm trong khoảng 0 đến 5.';
    } else {
        if ($remove_image) {
            $next_image = '';
        }

        if (!empty($_FILES['avatar']['name'] ?? '')) {
            $upload = upload_trainer_avatar_file($_FILES['avatar'], $upload_dir, $id);
            if (empty($upload['success'])) {
                $error = $upload['message'] ?? 'Không thể tải ảnh HLV.';
            } else {
                $next_image = $upload['file_name'] ?? '';
            }
        }
    }

    if ($error === '') {
        $stmt = $conn->prepare('
            UPDATE trainers
            SET full_name = ?,
                avatar = ?,
                specialty = ?,
                experience_years = ?,
                phone = ?,
                email = ?,
                bio = ?,
                rating = ?,
                total_members = ?,
                status = ?
            WHERE id = ?
        ');
        $stmt->bind_param(
            'sssisssdisi',
            $full_name,
            $next_image,
            $specialty,
            $experience_years,
            $phone,
            $email,
            $bio,
            $rating,
            $total_members,
            $status,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            $old_image = (string) ($trainer['avatar'] ?? '');
            if (($remove_image || $next_image !== $old_image) && is_local_trainer_uploaded_file($old_image)) {
                @unlink($upload_dir . basename($old_image));
            }

            header('Location: ' . $base_path . 'trainers.php?edit=success');
            exit;
        }

        if ($next_image !== ($trainer['avatar'] ?? '') && is_file($upload_dir . $next_image)) {
            @unlink($upload_dir . $next_image);
        }

        $error = 'Cập nhật HLV thất bại: ' . $stmt->error;
        $stmt->close();
    }
}

$avatarUrl = resolve_trainer_avatar_url($id, $trainer['avatar'] ?? '', $root_base_path);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sửa huấn luyện viên</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $root_base_path; ?>css/style.css">
</head>
<body class="dashboard-page">
<div class="d-flex dashboard-wrapper">
  <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

  <div class="main-content flex-grow-1">
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="fw-bold mb-1">Sửa huấn luyện viên</h2>
          <p class="text-muted mb-0">Cập nhật thông tin hiển thị, ảnh đại diện, chuyên môn và trạng thái của HLV.</p>
        </div>
        <a href="<?php echo $base_path; ?>trainers.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
      <?php endif; ?>

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Tên HLV <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required value="<?php echo h($_POST['full_name'] ?? $trainer['full_name']); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Chuyên môn <span class="text-danger">*</span></label>
                <input type="text" name="specialty" class="form-control" required value="<?php echo h($_POST['specialty'] ?? $trainer['specialty']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Kinh nghiệm (năm)</label>
                <input type="number" name="experience_years" class="form-control" min="0" value="<?php echo h($_POST['experience_years'] ?? $trainer['experience_years']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Đánh giá mặc định</label>
                <input type="number" name="rating" class="form-control" min="0" max="5" step="0.1" value="<?php echo h($_POST['rating'] ?? $trainer['rating']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Hội viên đã hỗ trợ</label>
                <input type="number" name="total_members" class="form-control" min="0" value="<?php echo h($_POST['total_members'] ?? $trainer['total_members']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                  <?php $statusValue = (string) ($_POST['status'] ?? $trainer['status']); ?>
                  <option value="active" <?php echo $statusValue === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                  <option value="inactive" <?php echo $statusValue === 'inactive' ? 'selected' : ''; ?>>Tạm ngưng</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Số điện thoại</label>
                <input type="text" name="phone" class="form-control" value="<?php echo h($_POST['phone'] ?? $trainer['phone']); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo h($_POST['email'] ?? $trainer['email']); ?>">
              </div>
              <div class="col-md-8">
                <label class="form-label">Ảnh HLV mới</label>
                <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">Hỗ trợ JPG, PNG, WEBP. Tối đa 3MB.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Ảnh hiện tại</label>
                <div class="border rounded p-2 bg-light text-center">
                  <img src="<?php echo h($avatarUrl); ?>" alt="<?php echo h($trainer['full_name']); ?>" style="width:100%; max-height:150px; object-fit:cover; border-radius:10px;">
                </div>
              </div>
              <?php if (!empty($trainer['avatar'])): ?>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                    <label class="form-check-label" for="removeImage">Xóa ảnh hiện tại và dùng ảnh mặc định</label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="col-12">
                <label class="form-label">Tiểu sử / mô tả</label>
                <textarea name="bio" class="form-control" rows="5"><?php echo h($_POST['bio'] ?? $trainer['bio']); ?></textarea>
              </div>
              <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i>Cập nhật HLV
                </button>
                <a href="<?php echo $base_path; ?>trainers.php" class="btn btn-outline-secondary ms-2">Hủy</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
