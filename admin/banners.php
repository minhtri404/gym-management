<?php
$page_title = 'Quản lý banner FE';
include __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions/banner-functions.php';

$base_path = '';
$upload_error = '';

function admin_banner_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_banner_upload(array $file, string &$error): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error = 'Upload ảnh thất bại.';
        return '';
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Ảnh banner tối đa 5MB.';
        return '';
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $imageInfo = $tmpName !== '' ? @getimagesize($tmpName) : false;
    if ($imageInfo === false) {
        $error = 'File upload không phải ảnh hợp lệ.';
        return '';
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        $error = 'Chỉ hỗ trợ ảnh JPG, PNG hoặc WEBP.';
        return '';
    }

    $uploadDir = __DIR__ . '/../assets/images/banners';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $error = 'Không tạo được thư mục lưu banner.';
        return '';
    }

    $fileName = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $error = 'Không lưu được ảnh banner.';
        return '';
    }

    return 'assets/images/banners/' . $fileName;
}

ensure_home_banners_table($conn);
seed_home_banners_if_empty($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF token không hợp lệ.');
    }

    $action = trim((string) ($_POST['action'] ?? 'save'));
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM home_banners WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        }

        header('Location: banners.php?delete=success');
        exit;
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    $button_text = trim((string) ($_POST['button_text'] ?? ''));
    $button_link = trim((string) ($_POST['button_link'] ?? ''));
    $image_path = trim((string) ($_POST['existing_image_path'] ?? ''));
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'active'));

    if (!in_array($status, ['active', 'hidden'], true)) {
        $status = 'active';
    }

    $uploaded_path = admin_banner_upload($_FILES['image'] ?? [], $upload_error);
    if ($uploaded_path !== '') {
        $image_path = $uploaded_path;
    }

    if ($title === '') {
        $upload_error = 'Vui lòng nhập tiêu đề banner.';
    } elseif ($image_path === '') {
        $upload_error = 'Vui lòng chọn ảnh banner.';
    }

    if ($upload_error === '') {
        if ($id > 0) {
            $stmt = $conn->prepare('
                UPDATE home_banners
                SET title = ?, subtitle = ?, button_text = ?, button_link = ?, image_path = ?, sort_order = ?, status = ?
                WHERE id = ?
            ');
            if ($stmt) {
                $stmt->bind_param('sssssisi', $title, $subtitle, $button_text, $button_link, $image_path, $sort_order, $status, $id);
                $stmt->execute();
                $stmt->close();
            }

            header('Location: banners.php?update=success');
            exit;
        }

        $stmt = $conn->prepare('
            INSERT INTO home_banners (title, subtitle, button_text, button_link, image_path, sort_order, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmt) {
            $stmt->bind_param('sssssis', $title, $subtitle, $button_text, $button_link, $image_path, $sort_order, $status);
            $stmt->execute();
            $stmt->close();
        }

        header('Location: banners.php?add=success');
        exit;
    }
}

$edit_id = (int) ($_GET['edit'] ?? 0);
$edit_banner = null;
if ($edit_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM home_banners WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_banner = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

$banners = get_home_banners($conn, false);
$active_count = 0;
$hidden_count = 0;
foreach ($banners as $banner) {
    if (($banner['status'] ?? '') === 'active') {
        $active_count++;
    } else {
        $hidden_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quản lý banner FE - Gym Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    .banner-thumb {
      width: 170px;
      aspect-ratio: 16 / 7;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid rgba(15, 23, 42, 0.1);
      background: #f8fafc;
    }

    .banner-preview {
      width: 100%;
      max-width: 360px;
      aspect-ratio: 16 / 7;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid rgba(15, 23, 42, 0.1);
      background: #f8fafc;
    }

    .banner-table td {
      vertical-align: middle;
    }
  </style>
</head>
<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
      <?php include __DIR__ . '/../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <?php if ($upload_error !== ''): ?>
          <div class="alert alert-danger"><?php echo admin_banner_h($upload_error); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['add']) && $_GET['add'] === 'success'): ?>
          <div class="alert alert-success">Thêm banner thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
          <div class="alert alert-success">Cập nhật banner thành công.</div>
        <?php endif; ?>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
          <div class="alert alert-success">Xóa banner thành công.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="mb-1">Quản lý banner FE</h4>
            <p class="text-muted mb-0">Quản lý ảnh banner động đang hiển thị ở trang chủ hội viên.</p>
          </div>
          <a href="../user/home" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Xem trang chủ
          </a>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Tổng banner</div>
                <h3 class="mb-0"><?php echo count($banners); ?></h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Đang hiển thị</div>
                <h3 class="mb-0 text-success"><?php echo $active_count; ?></h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted mb-2">Đang ẩn</div>
                <h3 class="mb-0 text-secondary"><?php echo $hidden_count; ?></h3>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white">
                <h5 class="mb-0"><?php echo $edit_banner ? 'Sửa banner' : 'Thêm banner mới'; ?></h5>
              </div>
              <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo admin_banner_h($_SESSION['csrf_token'] ?? ''); ?>">
                  <input type="hidden" name="action" value="save">
                  <input type="hidden" name="id" value="<?php echo (int) ($edit_banner['id'] ?? 0); ?>">
                  <input type="hidden" name="existing_image_path" value="<?php echo admin_banner_h($edit_banner['image_path'] ?? ''); ?>">

                  <div class="mb-3">
                    <label class="form-label fw-semibold">Tiêu đề</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo admin_banner_h($edit_banner['title'] ?? ''); ?>">
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea name="subtitle" rows="4" class="form-control"><?php echo admin_banner_h($edit_banner['subtitle'] ?? ''); ?></textarea>
                  </div>

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Nút CTA</label>
                      <input type="text" name="button_text" class="form-control" value="<?php echo admin_banner_h($edit_banner['button_text'] ?? ''); ?>" placeholder="Xem gói tập">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Link CTA</label>
                      <input type="text" name="button_link" class="form-control" value="<?php echo admin_banner_h($edit_banner['button_link'] ?? ''); ?>" placeholder="user/package/index">
                    </div>
                  </div>

                  <div class="row g-3 mt-1">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Thứ tự</label>
                      <input type="number" name="sort_order" class="form-control" value="<?php echo (int) ($edit_banner['sort_order'] ?? (count($banners) + 1)); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Trạng thái</label>
                      <select name="status" class="form-select">
                        <?php $current_status = $edit_banner['status'] ?? 'active'; ?>
                        <option value="active" <?php echo $current_status === 'active' ? 'selected' : ''; ?>>Hiển thị</option>
                        <option value="hidden" <?php echo $current_status === 'hidden' ? 'selected' : ''; ?>>Ẩn</option>
                      </select>
                    </div>
                  </div>

                  <div class="mt-3 mb-3">
                    <label class="form-label fw-semibold">Ảnh banner</label>
                    <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-text">Nên dùng ảnh ngang, dung lượng dưới 5MB.</div>
                  </div>

                  <?php if (!empty($edit_banner['image_path'])): ?>
                    <div class="mb-3">
                      <img src="<?php echo admin_banner_h(banner_image_url((string) $edit_banner['image_path'], '../')); ?>" alt="Banner hiện tại" class="banner-preview">
                    </div>
                  <?php endif; ?>

                  <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-save me-1"></i><?php echo $edit_banner ? 'Cập nhật' : 'Thêm banner'; ?>
                    </button>
                    <?php if ($edit_banner): ?>
                      <a href="banners.php" class="btn btn-outline-secondary">Hủy</a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white">
                <h5 class="mb-0">Danh sách banner</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table banner-table align-middle">
                    <thead>
                      <tr>
                        <th>Ảnh</th>
                        <th>Nội dung</th>
                        <th>Thứ tự</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (count($banners) === 0): ?>
                        <tr>
                          <td colspan="5" class="text-center text-muted py-4">Chưa có banner.</td>
                        </tr>
                      <?php endif; ?>

                      <?php foreach ($banners as $banner): ?>
                        <tr>
                          <td>
                            <img src="<?php echo admin_banner_h(banner_image_url((string) $banner['image_path'], '../')); ?>" alt="<?php echo admin_banner_h($banner['title']); ?>" class="banner-thumb">
                          </td>
                          <td>
                            <div class="fw-bold"><?php echo admin_banner_h($banner['title']); ?></div>
                            <div class="text-muted small"><?php echo admin_banner_h($banner['subtitle']); ?></div>
                            <?php if (!empty($banner['button_text'])): ?>
                              <div class="small mt-1">Nút: <?php echo admin_banner_h($banner['button_text']); ?></div>
                            <?php endif; ?>
                          </td>
                          <td><?php echo (int) $banner['sort_order']; ?></td>
                          <td>
                            <?php if (($banner['status'] ?? '') === 'active'): ?>
                              <span class="badge bg-success">Hiển thị</span>
                            <?php else: ?>
                              <span class="badge bg-secondary">Ẩn</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <a href="banners.php?edit=<?php echo (int) $banner['id']; ?>" class="btn btn-sm btn-outline-primary">
                              <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa banner này?');">
                              <input type="hidden" name="csrf_token" value="<?php echo admin_banner_h($_SESSION['csrf_token'] ?? ''); ?>">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?php echo (int) $banner['id']; ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
