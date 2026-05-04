<?php
$page_title = "Gia h?n gói t?p";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
}

if ($member_id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
}

/* L?y thông tin h?i viên */
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

// L?y danh sách gói t?p d? hi?n th? trong form
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
    $paid_amount_input = trim($_POST['paid_amount'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($package_id <= 0 || empty($start_date)) {
        $error = "Vui lòng ch?n gói và ngày b?t d?u.";
    } else {
        /* Láº¥y thÃ´ng tin gÃ³i má»›i */
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
            $error = "Gói t?p không t?n t?i.";
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
                $error = "Ngày b?t d?u không h?p l?.";
            }

                try {
                    /* H?T GÓI CU: mark previous member_packages as expired */
                    $stmtExpire = $conn->prepare("\n                        UPDATE member_packages\n                        SET status = 'expired'\n                        WHERE member_id = ? AND status = 'active'\n                    ");
                    $stmtExpire->bind_param("i", $member_id);
                    $stmtExpire->execute();
                    $stmtExpire->close();

                    /* LUU L?CH GÓI: insert new active member_packages row */
                    $stmtHistory = $conn->prepare("\n                        INSERT INTO member_packages (member_id, package_id, start_date, end_date, status)\n                        VALUES (?, ?, ?, ?, 'active')\n                    ");
                    if ($stmtHistory === false) {
                        throw new Exception('Prepare failed (member_packages): ' . $conn->error);
                    }
                    $stmtHistory->bind_param("iiss", $member_id, $package_id, $start_date, $end_date);
                    $stmtHistory->execute();
                    $stmtHistory->close();

                    /* C?p nh?t gói hi?n t?i trong members */
                    $new_status = 'active';

                    $stmt_update = $conn->prepare("\n                        UPDATE members\n                        SET package_id = ?, start_date = ?, end_date = ?, status = ?\n                        WHERE id = ?\n                    ");
                    $stmt_update->bind_param("isssi", $package_id, $start_date, $end_date, $new_status, $member_id);
                    $stmt_update->execute();
                    $stmt_update->close();

                    /* Luu l?ch s? gia h?n */
                    $history_note = !empty($note) ? $note : 'Gia h?n gói t?p';
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

                    $stmt_history = $conn->prepare("\n                      INSERT INTO member_package_history (\n                        member_id,\n                        package_id,\n                        action_type,\n                        start_date,\n                        end_date,\n                        price,\n                        paid_amount,\n                        remaining_amount,\n                        status,\n                        note\n                      ) VALUES (?, ?, 'renew', ?, ?, ?, ?, ?, 'active', ?)\n                    ");
                    if ($stmt_history === false) {
                      throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $stmt_history->bind_param(
                      "iissddds",
                      $member_id,
                      $package_id,
                      $start_date,
                      $end_date,
                      $price,
                      $paid_amount,
                      $remaining_amount,
                      $history_note
                    );
                    $stmt_history->execute();
                    $stmt_history->close();

                    $conn->commit();

                    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&renew=success");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Gia h?n th?t b?i: " . $e->getMessage();
                }
                      $member_id,
                      $package_id,
                      $start_date,
                      $end_date,
                      $price,
                      $paid_amount,
                      $remaining_amount,
                      $history_note
                    );
                    $stmt_history->execute();
                    $stmt_history->close();

                    $conn->commit();

                    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&renew=success");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Gia háº¡n tháº¥t báº¡i: " . $e->getMessage();
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
          <h2 class="fw-bold mb-0">Gia h?n gói t?p</h2>
          <a href="<?php echo $base_path; ?>php/members/view-member.php?id=<?php echo (int)$member_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay láº¡i
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
                  <h5 class="mb-3">Thông tin h?i viên</h5>

                  <div class="mb-2">
                    <div class="text-muted small">H? và tên</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Gói hi?n t?i</div>
                    <div><?php echo htmlspecialchars($member['package_name'] ?: 'ChÆ°a cÃ³'); ?></div>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">Ngày b?t d?u hi?n t?i</div>
                    <div><?php echo htmlspecialchars($member['start_date'] ?: 'ChÆ°a cÃ³'); ?></div>
                  </div>

                  <div class="mb-0">
                    <div class="text-muted small">Ngày k?t thúc hi?n t?i</div>
                    <div><?php echo htmlspecialchars($member['end_date'] ?: 'ChÆ°a cÃ³'); ?></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-7">
                <form method="POST">
                  <input type="hidden" name="member_id" value="<?php echo (int)$member_id; ?>">

                  <div class="mb-3">
                    <label class="form-label">Ch?n gói m?i</label>
                    <select name="package_id" class="form-select" required>
                      <option value="">-- Ch?n gói --</option>
                      <?php foreach ($packages as $package): ?>
                        <option value="<?php echo (int)$package['id']; ?>">
                          <?php echo htmlspecialchars($package['package_name']); ?>
                          - <?php echo number_format((float)$package['price'], 0, ',', '.'); ?> VNÐ
                          - <?php echo (int)$package['duration_months']; ?> tháng
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ngày b?t d?u</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">S? ti?n dã tr?</label>
                    <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" placeholder="0">
                    <small class="text-muted">H? th?ng t? tính còn n?.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Ví d?: Gia h?n thêm 12 tháng"></textarea>
                  </div>

                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Luu gia h?n
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



