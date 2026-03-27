<?php
$page_title = "Gia hạn gói tập";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
}

if ($member_id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
}

/* Lấy thông tin hội viên */
$stmt_member = $conn->prepare("
    SELECT m.*, p.package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id = ?
    LIMIT 1
");
$stmt_member->bind_param("i", $member_id);
$stmt_member->execute();
$result_member = $stmt_member->get_result();

if (!$result_member || $result_member->num_rows === 0) {
    $stmt_member->close();
    header("Location: " . $base_path . "members.php");
    exit();
}

$member = $result_member->fetch_assoc();
$stmt_member->close();

/* Lấy danh sách gói */
$packages = [];
$result_packages = $conn->query("SELECT id, package_name, price, duration_months FROM packages ORDER BY id DESC");
if ($result_packages && $result_packages->num_rows > 0) {
    while ($row = $result_packages->fetch_assoc()) {
        $packages[] = $row;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
    $start_date = trim($_POST['start_date'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($package_id <= 0 || empty($start_date)) {
        $error = "Vui lòng chọn gói và ngày bắt đầu.";
    } else {
        /* Lấy thông tin gói mới */
        $stmt_package = $conn->prepare("
            SELECT id, package_name, price, duration_months
            FROM packages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt_package->bind_param("i", $package_id);
        $stmt_package->execute();
        $result_package = $stmt_package->get_result();

        if (!$result_package || $result_package->num_rows === 0) {
            $error = "Gói tập không tồn tại.";
        } else {
            $package = $result_package->fetch_assoc();
            $stmt_package->close();

            $price = (float)$package['price'];
            $duration_months = (int)$package['duration_months'];

            try {
                $start = new DateTime($start_date);
                $end = clone $start;
                $end->modify("+{$duration_months} months");
                $end_date = $end->format('Y-m-d');
            } catch (Exception $e) {
                $error = "Ngày bắt đầu không hợp lệ.";
            }

            if (empty($error)) {
                $conn->begin_transaction();

                try {
                    /* Cập nhật gói hiện tại trong members */
                    $new_status = 'active';

                    $stmt_update = $conn->prepare("
                        UPDATE members
                        SET package_id = ?, start_date = ?, end_date = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("isssi", $package_id, $start_date, $end_date, $new_status, $member_id);
                    $stmt_update->execute();
                    $stmt_update->close();

                    /* Lưu lịch sử gia hạn */
                    $history_note = !empty($note) ? $note : 'Gia hạn gói tập';

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
                        ) VALUES (?, ?, 'renew', ?, ?, ?, ?, 0, 'active', ?)
                    ");
                    $stmt_history->bind_param(
                        "iissdds",
                        $member_id,
                        $package_id,
                        $start_date,
                        $end_date,
                        $price,
                        $price,
                        $history_note
                    );
                    $stmt_history->execute();
                    $stmt_history->close();

                    $conn->commit();

                    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&renew=success");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Gia hạn thất bại: " . $e->getMessage();
                }
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
          <h2 class="fw-bold mb-0">Gia hạn gói tập</h2>
          <a href="<?php echo $base_path; ?>php/members/view-member.php?id=<?php echo (int)$member_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
          </a>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            <?php if (!empty($error)): ?>
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
                  <input type="hidden" name="member_id" value="<?php echo (int)$member_id; ?>">

                  <div class="mb-3">
                    <label class="form-label">Chọn gói mới</label>
                    <select name="package_id" class="form-select" required>
                      <option value="">-- Chọn gói --</option>
                      <?php foreach ($packages as $package): ?>
                        <option value="<?php echo (int)$package['id']; ?>">
                          <?php echo htmlspecialchars($package['package_name']); ?>
                          - <?php echo number_format((float)$package['price'], 0, ',', '.'); ?> VNĐ
                          - <?php echo (int)$package['duration_months']; ?> tháng
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Ví dụ: Gia hạn thêm 12 tháng"></textarea>
                  </div>

                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Lưu gia hạn
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</body>
</html>