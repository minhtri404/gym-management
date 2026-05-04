<?php
$page_title = "Thêm h?i viên";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';

$success = "";
$error = "";

$packages = [];
$sql_packages = "SELECT id, package_name, duration_months FROM packages WHERE status = 'active' ORDER BY id DESC";
$result_packages = $conn->query($sql_packages);

if ($result_packages && $result_packages->num_rows > 0) {
  while ($row = $result_packages->fetch_assoc()) {
    $packages[] = $row;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $gender = trim($_POST['gender'] ?? 'Nam');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $date_of_birth = trim($_POST['date_of_birth'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $package_id = trim($_POST['package_id'] ?? '');
  $start_date = trim($_POST['start_date'] ?? '');
  $paid_amount_input = trim($_POST['paid_amount'] ?? '');
  $end_date = '';
  $status = trim($_POST['status'] ?? 'active');

  if ($full_name === '' || $phone === '' || $package_id === '' || $start_date === '') {
    $error = "Vui lòng nh?p d?y d? các tru?ng b?t bu?c.";
  } else {
    $stmt_package = $conn->prepare("SELECT duration_months FROM packages WHERE id = ? LIMIT 1");
    $stmt_package->bind_param("i", $package_id);
    $stmt_package->execute();
    $result_package = $stmt_package->get_result();

    if (!$result_package || $result_package->num_rows === 0) {
      $error = "Gói t?p không t?n t?i.";
    } else {
      $package = $result_package->fetch_assoc();
      $duration_months = (int)$package['duration_months'];

      try {
        $start = new DateTime($start_date);
        $end = clone $start;
        $end->modify("+{$duration_months} months");
        $end_date = $end->format('Y-m-d');
      } catch (Exception $e) {
        $error = "Ngày b?t d?u không h?p l?.";
      }
    }

    $stmt_package->close();

    if ($error === '') {
    $stmt = $conn->prepare("INSERT INTO members (full_name, gender, phone, email, date_of_birth, address, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
      "ssssssisss",
      $full_name,
      $gender,
      $phone,
      $email,
      $date_of_birth,
      $address,
      $package_id,
      $start_date,
      $end_date,
      $status
    );
    if ($stmt->execute()) {
      $member_id = $conn->insert_id;
      $stmt->close();

      /* L?y giá gói d? luu l?ch s? */
      $package_price = 0;
      $stmt_package = $conn->prepare("SELECT price FROM packages WHERE id = ? LIMIT 1");
      $stmt_package->bind_param("i", $package_id);
      $stmt_package->execute();
      $result_package = $stmt_package->get_result();

      if ($result_package && $result_package->num_rows > 0) {
        $package_row = $result_package->fetch_assoc();
        $package_price = (float)$package_row['price'];
      }
      $stmt_package->close();

      $paid_amount = 0.0;
      if ($paid_amount_input !== '') {
        $paid_amount = (float) str_replace([',', ' '], '', $paid_amount_input);
      }
      if ($paid_amount < 0) {
        $paid_amount = 0.0;
      }
      if ($paid_amount > $package_price) {
        $paid_amount = $package_price;
      }
      $remaining_amount = max(0, $package_price - $paid_amount);

      /* Luu l?ch s? gói t?p */
      $history_status = 'active';
      if ($status === 'expired') {
        $history_status = 'expired';
      } elseif ($status === 'inactive') {
        $history_status = 'cancelled';
      }

      $history_note = 'T?o h?i viên m?i';

      $stmt_history = $conn->prepare("
        INSERT INTO member_package_history (
            member_id,
            package_id,
            action_type,
            start_date,
            end_date,
            price,
            paid_amount,
            remaining_amount,
            status,
            note
        ) VALUES (?, ?, 'new', ?, ?, ?, ?, ?, ?, ?)
    ");
      $stmt_history->bind_param(
        "iissdddss",
        $member_id,
        $package_id,
        $start_date,
        $end_date,
        $package_price,
        $paid_amount,
        $remaining_amount,
        $history_status,
        $history_note
      );
      $stmt_history->execute();
      $stmt_history->close();

      header("Location: " . $base_path . "members.php?add=success");
      exit();
    } else {
      $error = "Thêm h?i viên th?t b?i: " . $stmt->error;
      $stmt->close();
    }

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
  <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>

<body>
  <div class="d-flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold">Thêm h?i viên</h2>
          <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay láº¡i
          </a>
        </div>

        <?php if ($error !== ""): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <form method="POST" action="">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">H? và tên <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Gi?i tính</label>
                  <select name="gender" class="form-select">
                    <option value="Nam">Nam</option>
                    <option value="N?">N?</option>
                    <option value="Khác">Khác</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">S? di?n tho?i <span class="text-danger">*</span></label>
                  <input type="text" name="phone" class="form-control" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Ngày sinh</label>
                  <input type="date" name="date_of_birth" class="form-control">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Gói t?p <span class="text-danger">*</span></label>
                  <select name="package_id" id="package_id" class="form-select" required>
                    <option value="">-- Ch?n gói t?p --</option>
                    <?php foreach ($packages as $package): ?>
                      <option value="<?php echo $package['id']; ?>" data-duration="<?php echo $package['duration_months']; ?>">
                        <?php echo htmlspecialchars($package['package_name']); ?> (<?php echo $package['duration_months']; ?> tháng)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">Ð?a ch?</label>
                  <input type="text" name="address" class="form-control">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ngày b?t d?u <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" id="start_date" class="form-control" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">NgÃ y káº¿t thÃºc</label>
                  <input type="date" id="end_date_display" class="form-control" readonly placeholder="T? d?ng tính">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Tr?ng thái</label>
                  <select name="status" class="form-select">
                    <option value="active">Ðang ho?t d?ng</option>
                    <option value="expired">H?t h?n</option>
                    <option value="inactive">Ngung ho?t d?ng</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">S? ti?n dã tr?</label>
                  <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" placeholder="0">
                  <small class="text-muted">H? th?ng t? tính còn n?.</small>
                </div>

                <div class="col-12 mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Luu h?i viên
                  </button>
                  <a href="<?php echo $base_path; ?>members.php" class="btn btn-outline-secondary ms-2">Há»§y</a>
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
      const lastDayOfTargetMonth = new Date(
        targetDate.getFullYear(),
        targetDate.getMonth() + 1,
        0
      ).getDate();

      const safeDay = Math.min(day, lastDayOfTargetMonth);
      return new Date(
        targetDate.getFullYear(),
        targetDate.getMonth(),
        safeDay
      );
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

    document.addEventListener('DOMContentLoaded', function () {
      calculateEndDate();
    });
    document.getElementById('package_id').addEventListener('change', calculateEndDate);
    document.getElementById('start_date').addEventListener('change', calculateEndDate);
  </script>
</body>

</html>


