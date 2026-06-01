<?php
$page_title = 'Thêm hội viên';
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';
$root_base_path = '../../';

$error = '';
$packages = [];
$form = [
    'full_name' => '',
    'gender' => 'Nam',
    'phone' => '',
    'email' => '',
    'date_of_birth' => '',
    'address' => '',
    'package_id' => '',
    'start_date' => date('Y-m-d'),
    'paid_amount' => '',
    'status' => 'active',
];

$result_packages = $conn->query("SELECT id, package_name, duration_months, price FROM packages WHERE status = 'active' ORDER BY id DESC");
if ($result_packages) {
    while ($row = $result_packages->fetch_assoc()) {
        $packages[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'CSRF token không hợp lệ.';
    }

    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? $value));
    }

    $full_name = $form['full_name'];
    $gender = $form['gender'] !== '' ? $form['gender'] : 'Nam';
    $phone = $form['phone'];
    $email = $form['email'];
    $date_of_birth = $form['date_of_birth'];
    $address = $form['address'];
    $package_id = (int) $form['package_id'];
    $start_date = $form['start_date'];
    $paid_amount_input = $form['paid_amount'];
    $status = $form['status'] !== '' ? $form['status'] : 'active';

    if ($error === '' && ($full_name === '' || $phone === '' || $package_id <= 0 || $start_date === '')) {
        $error = 'Vui lòng nhập đầy đủ các trường bắt buộc.';
    }

    if ($error === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    }

    if ($error === '' && $date_of_birth !== '') {
        $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        $dobErrors = DateTime::getLastErrors();
        if ($dobErrors === false) {
            $dobErrors = ['warning_count' => 0, 'error_count' => 0];
        }
        $isDobValid = $dob instanceof DateTime
            && $dob->format('Y-m-d') === $date_of_birth
            && (int) ($dobErrors['warning_count'] ?? 0) === 0
            && (int) ($dobErrors['error_count'] ?? 0) === 0;
        if (!$isDobValid) {
            $error = 'Ngày sinh không hợp lệ.';
        }
    }

    $package = null;
    if ($error === '') {
        $stmt_package = $conn->prepare('SELECT id, duration_months, price FROM packages WHERE id = ? LIMIT 1');
        $stmt_package->bind_param('i', $package_id);
        $stmt_package->execute();
        $result_package = $stmt_package->get_result();
        $package = $result_package ? $result_package->fetch_assoc() : null;
        $stmt_package->close();

        if (!$package) {
            $error = 'Gói tập không tồn tại.';
        }
    }

    if ($error === '') {
        try {
            $start = new DateTime($start_date);
            $end = clone $start;
            $end->modify('+' . (int) $package['duration_months'] . ' months');
            $end_date = $end->format('Y-m-d');
        } catch (Exception $e) {
            $error = 'Ngày bắt đầu không hợp lệ.';
        }
    }

    if ($error === '') {
        $paid_amount = 0.0;
        if ($paid_amount_input !== '') {
            $paid_amount = (float) str_replace([',', ' '], '', $paid_amount_input);
        }
        if ($paid_amount < 0) {
            $paid_amount = 0.0;
        }

        $package_price = (float) ($package['price'] ?? 0);
        if ($paid_amount > $package_price) {
            $paid_amount = $package_price;
        }
        $remaining_amount = max(0, $package_price - $paid_amount);
        $date_of_birth = $date_of_birth !== '' ? $date_of_birth : null;
        $member_package_status = ($status === 'active') ? 'active' : 'expired';
        $history_status = 'active';
        if ($status === 'expired') {
            $history_status = 'expired';
        } elseif ($status === 'inactive') {
            $history_status = 'cancelled';
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO members (full_name, gender, phone, email, date_of_birth, address, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssisss', $full_name, $gender, $phone, $email, $date_of_birth, $address, $package_id, $start_date, $end_date, $status);
            $stmt->execute();
            $member_id = (int) $conn->insert_id;
            $stmt->close();

            $stmt_member_package = $conn->prepare('INSERT INTO member_packages (member_id, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)');
            $stmt_member_package->bind_param('iisss', $member_id, $package_id, $start_date, $end_date, $member_package_status);
            $stmt_member_package->execute();
            $stmt_member_package->close();

            $history_note = 'Tạo hội viên mới';
            $stmt_history = $conn->prepare('INSERT INTO member_package_history (member_id, package_id, action_type, start_date, end_date, price, paid_amount, remaining_amount, status, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $action_type = 'new';
            $stmt_history->bind_param('iisssdddss', $member_id, $package_id, $action_type, $start_date, $end_date, $package_price, $paid_amount, $remaining_amount, $history_status, $history_note);
            $stmt_history->execute();
            $stmt_history->close();

            $conn->commit();
            header('Location: ' . $base_path . 'members.php?add=success');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Thêm hội viên thất bại: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $page_title; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $root_base_path; ?>css/style.css">
</head>
<body>
  <div class="d-flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Thêm hội viên</h2>
          <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
          </a>
        </div>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <form method="POST" action="">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($form['full_name']); ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Giới tính</label>
                  <select name="gender" class="form-select">
                    <option value="Nam" <?php echo $form['gender'] === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                    <option value="Nữ" <?php echo $form['gender'] === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                    <option value="Khác" <?php echo $form['gender'] === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                  <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($form['phone']); ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form['email']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Ngày sinh</label>
                  <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($form['date_of_birth']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Gói tập <span class="text-danger">*</span></label>
                  <select name="package_id" id="package_id" class="form-select" required>
                    <option value="">-- Chọn gói tập --</option>
                    <?php foreach ($packages as $package): ?>
                      <option value="<?php echo (int) $package['id']; ?>" data-duration="<?php echo (int) $package['duration_months']; ?>" <?php echo ((int) $form['package_id'] === (int) $package['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($package['package_name']); ?> (<?php echo (int) $package['duration_months']; ?> tháng)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Địa chỉ</label>
                  <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($form['address']); ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($form['start_date']); ?>" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ngày kết thúc</label>
                  <input type="date" id="end_date_display" class="form-control" readonly placeholder="Tự động tính">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="expired" <?php echo $form['status'] === 'expired' ? 'selected' : ''; ?>>Hết hạn</option>
                    <option value="inactive" <?php echo $form['status'] === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Số tiền đã trả</label>
                  <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($form['paid_amount']); ?>" placeholder="0">
                  <small class="text-muted">Hệ thống tự tính phần còn nợ.</small>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Lưu hội viên
                  </button>
                  <a href="<?php echo $base_path; ?>members.php" class="btn btn-outline-secondary ms-2">Hủy</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function addMonthsSafe(date, monthsToAdd) {
      const year = date.getFullYear();
      const month = date.getMonth();
      const day = date.getDate();
      const targetMonth = month + monthsToAdd;
      const targetDate = new Date(year, targetMonth, 1);
      const lastDayOfTargetMonth = new Date(targetDate.getFullYear(), targetDate.getMonth() + 1, 0).getDate();
      const safeDay = Math.min(day, lastDayOfTargetMonth);
      return new Date(targetDate.getFullYear(), targetDate.getMonth(), safeDay);
    }

    function calculateEndDate() {
      const packageSelect = document.getElementById('package_id');
      const startDateInput = document.getElementById('start_date');
      const endDateDisplay = document.getElementById('end_date_display');
      const selectedOption = packageSelect.options[packageSelect.selectedIndex];
      const duration = selectedOption ? parseInt(selectedOption.getAttribute('data-duration'), 10) : 0;
      const startDate = startDateInput.value;

      if (duration > 0 && startDate) {
        const start = new Date(startDate);
        const end = addMonthsSafe(start, duration);
        endDateDisplay.value = isNaN(end.getTime()) ? '' : end.toISOString().split('T')[0];
      } else {
        endDateDisplay.value = '';
      }
    }

    document.addEventListener('DOMContentLoaded', calculateEndDate);
    document.getElementById('package_id').addEventListener('change', calculateEndDate);
    document.getElementById('start_date').addEventListener('change', calculateEndDate);
  </script>
</body>
</html>
