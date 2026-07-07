<?php
$page_title = 'Gia hạn gói tập';
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';
$root_base_path = '../../';

$member_id = isset($_GET['id']) ? (int) ($_GET['id'] ?? 0) : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? (int) ($_POST['member_id'] ?? 0) : 0;
}

if ($member_id <= 0) {
    header('Location: ' . $base_path . 'members.php');
    exit;
}

$stmt_member = $conn->prepare('
    SELECT 
        m.*, 
        p.package_name,
        p.package_type,
        p.duration_days,
        p.duration_months
    FROM members m 
    LEFT JOIN packages p ON m.package_id = p.id 
    WHERE m.id = ? 
    LIMIT 1
');
$stmt_member->bind_param('i', $member_id);
$stmt_member->execute();
$result_member = $stmt_member->get_result();
$member = $result_member ? $result_member->fetch_assoc() : null;
$stmt_member->close();

if (!$member) {
    header('Location: ' . $base_path . 'members.php');
    exit;
}
$is_current_trial = (($member['package_type'] ?? '') === 'free_trial');
$page_title = $is_current_trial ? 'Nâng cấp gói tập' : 'Gia hạn gói tập';
$packages = [];
$result_packages = $conn->query("
    SELECT 
        id, 
        package_name, 
        price, 
        duration_months,
        duration_days,
        package_type
    FROM packages 
    WHERE status = 'active'
      AND package_type = 'paid'
    ORDER BY price ASC, duration_months ASC, id ASC
");
if ($result_packages) {
    while ($row = $result_packages->fetch_assoc()) {
        $packages[] = $row;
    }
}

$error = '';
$default_start_date = date('Y-m-d');

if (!empty($member['end_date']) && !$is_current_trial) {
    $today = new DateTime(date('Y-m-d'));
    $current_end = DateTime::createFromFormat('Y-m-d', $member['end_date']);

    if ($current_end && $current_end >= $today) {
        $current_end->modify('+1 day');
        $default_start_date = $current_end->format('Y-m-d');
    }
}

$form = [
    'package_id' => '',
    'start_date' => $default_start_date,
    'paid_amount' => '',
    'note' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'CSRF token không hợp lệ.';
    }

    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? $value));
    }

    $package_id = (int) $form['package_id'];
    $start_date = $form['start_date'];
    $paid_amount_input = $form['paid_amount'];
    $note = $form['note'];

    if ($error === '' && ($package_id <= 0 || $start_date === '')) {
        $error = 'Vui lòng chọn gói và ngày bắt đầu.';
    }

    $package = null;
    if ($error === '') {
        $stmt_package = $conn->prepare('
    SELECT 
        id, 
        package_name, 
        price, 
        duration_months,
        duration_days,
        package_type
    FROM packages 
    WHERE id = ? 
      AND status = "active"
    LIMIT 1
');
        $stmt_package->bind_param('i', $package_id);
        $stmt_package->execute();
        $result_package = $stmt_package->get_result();
        $package = $result_package ? $result_package->fetch_assoc() : null;
        $stmt_package->close();

        if (!$package) {
            $error = 'Gói tập không tồn tại.';
        }
        if ($error === '' && (($package['package_type'] ?? '') !== 'paid')) {
            $error = 'Chỉ được chọn gói trả phí khi gia hạn hoặc nâng cấp.';
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
        $price = (float) ($package['price'] ?? 0);
        $paid_amount = 0.0;
        if ($paid_amount_input !== '') {
            $paid_amount = (float) str_replace([',', ' '], '', $paid_amount_input);
        }
        if ($paid_amount < 0) {
            $paid_amount = 0.0;
        }
        if ($paid_amount > $price) {
            $paid_amount = $price;
        }
        $remaining_amount = max(0, $price - $paid_amount);
        $history_note = $note !== ''
            ? $note
            : ($is_current_trial ? 'Nâng cấp từ gói dùng thử sang gói trả phí' : 'Gia hạn gói tập');

        $conn->begin_transaction();
        try {
            $stmt_expire = $conn->prepare("UPDATE member_packages SET status = 'expired' WHERE member_id = ? AND status = 'active'");
            $stmt_expire->bind_param('i', $member_id);
            $stmt_expire->execute();
            $stmt_expire->close();

            $stmt_expire_history = $conn->prepare("
                UPDATE member_package_history
                SET status = 'expired'
                WHERE member_id = ?
                  AND status = 'active'
            ");
            $stmt_expire_history->bind_param('i', $member_id);
            $stmt_expire_history->execute();
            $stmt_expire_history->close();

            $stmt_member_package = $conn->prepare('INSERT INTO member_packages (member_id, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)');
            $active_status = 'active';
            $stmt_member_package->bind_param('iisss', $member_id, $package_id, $start_date, $end_date, $active_status);
            $stmt_member_package->execute();
            $stmt_member_package->close();

            $stmt_update = $conn->prepare('UPDATE members SET package_id = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?');
            $member_status = 'active';
            $stmt_update->bind_param('isssi', $package_id, $start_date, $end_date, $member_status, $member_id);
            $stmt_update->execute();
            $stmt_update->close();

            $stmt_history = $conn->prepare('INSERT INTO member_package_history (member_id, package_id, action_type, start_date, end_date, price, paid_amount, remaining_amount, status, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $action_type = $is_current_trial ? 'upgrade' : 'renew';
            $history_status = 'active';
            $stmt_history->bind_param('iisssdddss', $member_id, $package_id, $action_type, $start_date, $end_date, $price, $paid_amount, $remaining_amount, $history_status, $history_note);
            $stmt_history->execute();
            $stmt_history->close();

            $conn->commit();
            header('Location: ' . $root_base_path . 'php/members/view-member.php?id=' . $member_id . '&renew=success');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Gia hạn thất bại: ' . $e->getMessage();
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
          <h2 class="fw-bold mb-0">
            <?php echo $is_current_trial ? 'Nâng cấp gói tập' : 'Gia hạn gói tập'; ?>
          </h2>
          <a href="<?php echo $root_base_path; ?>php/members/view-member.php?id=<?php echo (int) $member_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
          </a>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row g-4">
              <div class="col-lg-5">
                <div class="border rounded p-3 bg-light">
                  <h5 class="mb-3">Thông tin hội viên</h5>

                  <div class="mb-2">
                    <div class="text-muted small">Họ và tên</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Gói hiện tại</div>
                    <div><?php echo htmlspecialchars($member['package_name'] ?: 'Chưa có'); ?></div>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Loại gói hiện tại</div>
                    <div>
                      <?php if ($is_current_trial): ?>
                        <span class="badge bg-info text-dark">Dùng thử</span>
                      <?php else: ?>
                        <span class="badge bg-primary">Trả phí</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Ngày bắt đầu hiện tại</div>
                    <div><?php echo htmlspecialchars($member['start_date'] ?: 'Chưa có'); ?></div>
                  </div>

                  <div class="mb-0">
                    <div class="text-muted small">Ngày kết thúc hiện tại</div>
                    <div><?php echo htmlspecialchars($member['end_date'] ?: 'Chưa có'); ?></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-7">
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="member_id" value="<?php echo (int) $member_id; ?>">

                  <div class="mb-3">
                    <label class="form-label">
                      <?php echo $is_current_trial ? 'Chọn gói trả phí để nâng cấp' : 'Chọn gói để gia hạn'; ?>
                    </label>
                    <select name="package_id" id="package_id" class="form-select" required>
                      <option value="">-- Chọn gói --</option>
                      <?php foreach ($packages as $package): ?>
                        <option
                          value="<?php echo (int) $package['id']; ?>"
                          data-duration="<?php echo (int) $package['duration_months']; ?>"
                          <?php echo ((int) $form['package_id'] === (int) $package['id']) ? 'selected' : ''; ?>
                        >
                          <?php echo htmlspecialchars($package['package_name']); ?>
                          - <?php echo number_format((float) $package['price'], 0, ',', '.'); ?> VNĐ
                          - <?php echo (int) $package['duration_months']; ?> tháng
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($form['start_date']); ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ngày kết thúc dự kiến</label>
                    <input type="date" id="end_date_display" class="form-control" readonly>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Số tiền đã trả</label>
                    <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($form['paid_amount']); ?>" placeholder="0">
                    <small class="text-muted">Hệ thống tự tính phần còn nợ.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Ví dụ: Gia hạn thêm 12 tháng"><?php echo htmlspecialchars($form['note']); ?></textarea>
                  </div>

                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>
                    <?php echo $is_current_trial ? 'Lưu nâng cấp' : 'Lưu gia hạn'; ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

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
