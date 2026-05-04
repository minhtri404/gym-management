<?php
$page_title = "Ðang ký gói";
include __DIR__ . '/../includes/auth-check.php';

$base_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF token không h?p l?.');
    }

    $action = trim($_POST['action'] ?? 'update');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'delete') {
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM package_registrations WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: package-registrations.php?delete=success");
        exit;
    }

    if ($action === 'approve' && $id > 0) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, full_name, phone, email, note, package_id, date_of_birth, address FROM package_registrations WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $registration = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$registration) {
                throw new Exception('Không tìm th?y dang ký.');
            }

            $stmt = $conn->prepare("SELECT id FROM members WHERE phone = ? LIMIT 1");
            $stmt->bind_param("s", $registration['phone']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$registration) {
                throw new Exception('Không tìm th?y dang ký.');
            }

            // BU?C 1: Tìm h?i viên theo SÐT ho?c email, dùng l?i n?u t?n t?i, n?u không thì t?o m?i
            $phone = $registration['phone'];
            $email = $registration['email'];
            $full_name = $registration['full_name'];

            $stmtCheck = $conn->prepare("SELECT id FROM members WHERE phone = ? OR email = ? LIMIT 1");
            $stmtCheck->bind_param("ss", $phone, $email);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if ($row = $resultCheck->fetch_assoc()) {
                $member_id = (int) $row['id'];
                $stmtCheck->close();

                // N?u h?i viên dã t?n t?i: c?p nh?t nh?ng tru?ng còn thi?u t? dang ký (không ghi dè d? li?u dã có)
                $stmtM = $conn->prepare("SELECT full_name, date_of_birth, address FROM members WHERE id = ? LIMIT 1");
                $stmtM->bind_param("i", $member_id);
                $stmtM->execute();
                $existingMember = $stmtM->get_result()->fetch_assoc();
                $stmtM->close();

                $updateFields = [];
                $updateParams = [];
                $updateTypes = '';

                if ((empty($existingMember['full_name']) || $existingMember['full_name'] === '') && !empty($full_name)) {
                    $updateFields[] = 'full_name = ?';
                    $updateParams[] = $full_name;
                    $updateTypes .= 's';
                }
                if ((empty($existingMember['date_of_birth']) || $existingMember['date_of_birth'] === '') && !empty($registration['date_of_birth'])) {
                    $updateFields[] = 'date_of_birth = ?';
                    $updateParams[] = $registration['date_of_birth'];
                    $updateTypes .= 's';
                }
                if ((empty($existingMember['address']) || $existingMember['address'] === '') && !empty($registration['address'])) {
                    $updateFields[] = 'address = ?';
                    $updateParams[] = $registration['address'];
                    $updateTypes .= 's';
                }

                if (!empty($updateFields)) {
                    $sql = 'UPDATE members SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
                    $stmtU = $conn->prepare($sql);
                    // bind params dynamically
                    $updateTypes .= 'i';
                    $updateParams[] = $member_id;
                    $stmtU->bind_param($updateTypes, ...$updateParams);
                    $stmtU->execute();
                    $stmtU->close();
                }
            } else {
                $stmtCheck->close();

                // N?u không có trong members, th? tìm trong users (ngu?i dùng dã t?o tài kho?n)
                $stmtUser = $conn->prepare("SELECT id, full_name, phone, email FROM users WHERE phone = ? OR email = ? LIMIT 1");
                $stmtUser->bind_param("ss", $phone, $email);
                $stmtUser->execute();
                $userRow = $stmtUser->get_result()->fetch_assoc();
                $stmtUser->close();

                if ($userRow) {
                    // T?o member t? d? li?u user hi?n có
                    $stmtInsert = $conn->prepare("INSERT INTO members (full_name, phone, email, status) VALUES (?, ?, ?, 'active')");
                    $stmtInsert->bind_param("sss", $userRow['full_name'], $userRow['phone'], $userRow['email']);
                    $stmtInsert->execute();
                    $member_id = $stmtInsert->insert_id;
                    $stmtInsert->close();
                } else {
                    // T?o h?i viên m?i (t?i thi?u thông tin). Sau này s? update package và dates.
                    $stmtInsert = $conn->prepare("INSERT INTO members (full_name, phone, email, status) VALUES (?, ?, ?, 'active')");
                    $stmtInsert->bind_param("sss", $full_name, $phone, $email);
                    $stmtInsert->execute();
                    $member_id = $stmtInsert->insert_id;
                    $stmtInsert->close();
                }
            }

            // Tránh chèn trùng: ki?m tra xem dã có member_packages active cùng package chua
            $pkgId = (int)$registration['package_id'];
            $stmtSame = $conn->prepare("SELECT id, end_date FROM member_packages WHERE member_id = ? AND package_id = ? AND status = 'active' LIMIT 1");
            $stmtSame->bind_param("ii", $member_id, $pkgId);
            $stmtSame->execute();
            $existingMp = $stmtSame->get_result()->fetch_assoc();
            $stmtSame->close();

            if ($existingMp) {
                // N?u dã có active cùng package, ch? m? r?ng end_date n?u dang ký m?i dài hon
                if (!empty($existingMp['end_date']) && $existingMp['end_date'] < $end_date) {
                    $stmt = $conn->prepare("UPDATE member_packages SET end_date = ? WHERE id = ?");
                    $stmt->bind_param("si", $end_date, $existingMp['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // BU?C 2: Expire các b?n ghi member_packages active hi?n có cho h?i viên này
                $stmt = $conn->prepare("UPDATE member_packages SET status = 'expired' WHERE member_id = ? AND status = 'active'");
                $stmt->bind_param("i", $member_id);
                $stmt->execute();
                $stmt->close();

                // BU?C 3: Chèn member_packages m?i v?i tr?ng thái active
                $stmt = $conn->prepare("INSERT INTO member_packages (member_id, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("iiss", $member_id, $pkgId, $start_date, $end_date);
                $stmt->execute();
                $stmt->close();
            }

            // BU?C 4: C?p nh?t b?ng members d? ph?n ánh gói hi?n t?i và ngày
            $stmt = $conn->prepare("UPDATE members SET package_id = ?, start_date = ?, end_date = ?, status = 'active' WHERE id = ?");
            $stmt->bind_param("issi", $registration['package_id'], $start_date, $end_date, $member_id);
            $stmt->execute();
            $stmt->close();

            $price = (float) ($package['price'] ?? 0);
            $paid_amount = 0.0;
            $remaining_amount = $price;
            $history_note = 'Ðang ký t? website';

            $stmt = $conn->prepare("
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
                ) VALUES (?, ?, 'new', ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->bind_param(
                "iissddds",
                $member_id,
                $registration['package_id'],
                $start_date,
                $end_date,
                $price,
                $paid_amount,
                $remaining_amount,
                $history_note
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE package_registrations SET status = 'closed' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: package-registrations.php?approve=success");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: package-registrations.php?approve=error");
            exit;
        }
    }

    $status = trim($_POST['status'] ?? 'new');
    $allowed_statuses = ['new', 'contacted', 'closed'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'new';
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE package_registrations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: package-registrations.php?update=success");
        exit;
    }
}

$total_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations");
$total_registrations = $total_result ? (int) $total_result->fetch_assoc()['total'] : 0;

$new_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'new'");
$new_registrations = $new_result ? (int) $new_result->fetch_assoc()['total'] : 0;

$contacted_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'contacted'");
$contacted_registrations = $contacted_result ? (int) $contacted_result->fetch_assoc()['total'] : 0;

$closed_result = $conn->query("SELECT COUNT(*) AS total FROM package_registrations WHERE status = 'closed'");
$closed_registrations = $closed_result ? (int) $closed_result->fetch_assoc()['total'] : 0;

$filter_status = trim($_GET['status'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, ['new', 'contacted', 'closed'], true)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($keyword !== '') {
    $where_conditions[] = "(pr.full_name LIKE ? OR pr.phone LIKE ? OR pr.email LIKE ? OR p.package_name LIKE ?)";
    $keyword_like = '%' . $keyword . '%';
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $params[] = $keyword_like;
    $types .= 'ssss';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "
    SELECT 
        pr.id,
        pr.full_name,
        pr.phone,
        pr.email,
        pr.note,
        pr.status,
        pr.created_at,
        p.package_name,
        p.price,
        p.duration_months
    FROM package_registrations pr
    LEFT JOIN packages p ON pr.package_id = p.id
    $where_sql
    ORDER BY pr.id DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ðang ký gói - Gym Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
</head>

<body class="dashboard-page">

    <div class="d-flex dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
                    <div class="alert alert-success">C?p nh?t dang ký thành công.</div>
                <?php endif; ?>

                <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
                    <div class="alert alert-success">Xóa dang ký thành công.</div>
                <?php endif; ?>
                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'success'): ?>
                    <div class="alert alert-success">Ðã duy?t và t?o h?i viên thành công.</div>
                <?php endif; ?>
                <?php if (isset($_GET['approve']) && $_GET['approve'] === 'error'): ?>
                    <div class="alert alert-danger">Duy?t th?t b?i. Ki?m tra l?i d? li?u (trùng SÐT ho?c gói không t?n t?i).</div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">Qu?n lý dang ký gói t?p</h4>
                        <p class="text-muted mb-0">Theo dõi yêu c?u dang ký gói t? website và x? lý nhanh.</p>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">T?ng dang ký</div>
                                <h3 class="mb-0"><?php echo $total_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">M?i</div>
                                <h3 class="mb-0 text-primary"><?php echo $new_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Ðã liên h?</div>
                                <h3 class="mb-0 text-warning"><?php echo $contacted_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted mb-2">Ðã dóng</div>
                                <h3 class="mb-0 text-success"><?php echo $closed_registrations; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Tìm ki?m</label>
                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="Tên / SÐT / Email / Gói"
                            value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">L?c theo tr?ng thái</label>
                        <select name="status" class="form-select">
                            <option value="">T?t c?</option>
                            <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>M?i</option>
                            <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>Ðã liên h?</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Ðã dóng</option>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Tìm / L?c
                        </button>
                    </div>

                    <div class="col-md-auto">
                        <a href="package-registrations.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0">Danh sách dang ký gói t?p</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Khách hàng</th>
                                        <th>Gói dang ký</th>
                                        <th>Ghi chú</th>
                                        <th>Tr?ng thái</th>
                                        <th>Ngày g?i</th>
                                        <th class="text-end">X? lý</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="small text-muted">SÐT: <?php echo htmlspecialchars($row['phone']); ?></div>
                                                    <div class="small text-muted">Email: <?php echo htmlspecialchars($row['email'] ?: 'Chua có'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['package_name'] ?: 'Không xác d?nh'); ?></div>
                                                    <?php if (!empty($row['price'])): ?>
                                                        <div class="small text-muted"><?php echo number_format((float)$row['price'], 0, ',', '.'); ?> VNÐ</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['duration_months'])): ?>
                                                        <div class="small text-muted">Th?i h?n: <?php echo (int)$row['duration_months']; ?> tháng</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="min-width: 200px;"><?php echo nl2br(htmlspecialchars($row['note'] ?? '')); ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'new'): ?>
                                                        <span class="badge bg-primary">M?i</span>
                                                    <?php elseif ($row['status'] === 'contacted'): ?>
                                                        <span class="badge bg-warning text-dark">Ðã liên h?</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Ðã dóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : ''; ?></td>
                                                <td class="text-end" style="min-width: 220px;">
                                                    <form method="POST" class="row g-2 justify-content-end">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                                        <div class="col-12">
                                                            <select name="status" class="form-select form-select-sm">
                                                                <option value="new" <?php echo $row['status'] === 'new' ? 'selected' : ''; ?>>M?i</option>
                                                                <option value="contacted" <?php echo $row['status'] === 'contacted' ? 'selected' : ''; ?>>Ðã liên h?</option>
                                                                <option value="closed" <?php echo $row['status'] === 'closed' ? 'selected' : ''; ?>>Ðã dóng</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-12">
                                                            <input type="hidden" name="action" value="update">
                                                            <button type="submit" class="btn btn-sm btn-warning w-100">
                                                                <i class="bi bi-save me-1"></i> C?p nh?t
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <?php if ($row['status'] !== 'closed'): ?>
                                                        <form method="POST" action="php/package-registrations/approve.php" class="mt-2" onsubmit="return confirm('Duy?t và t?o h?i viên m?i?');">
                                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                                <i class="bi bi-check-circle me-1"></i> Duy?t & t?o h?i viên
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" class="mt-2" onsubmit="return confirm('Xóa dang ký này?');">
                                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                            <i class="bi bi-trash me-1"></i> Xóa
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Chua có dang ký gói nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="../js/main.js"></script>
</body>

</html>
