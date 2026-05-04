<?php
$page_title = "Chi ti?t h?i viên";
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
}

// X? lý thêm/s?a/xóa ghi chú
$note_success = "";
$note_error = "";
$edit_note_id = isset($_GET['edit_note_id']) ? (int) $_GET['edit_note_id'] : 0;
$filter_note_date = isset($_GET['note_date']) ? trim($_GET['note_date']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_content = trim($_POST['note'] ?? '');

    if (empty($note_content)) {
        $note_error = "Vui lòng nh?p n?i dung ghi chú.";
    } else {
        $stmt_note = $conn->prepare("INSERT INTO member_notes (member_id, note, created_by_name) VALUES (?, ?, ?)");
        $created_by = "Admin"; // Có th? l?y t? session n?u có
        $stmt_note->bind_param("iss", $id, $note_content, $created_by);

        if ($stmt_note->execute()) {
            $note_success = "Ðã thêm ghi chú thành công.";
            header("Location: " . $_SERVER['REQUEST_URI'] . "&note_success=1");
            exit();
        } else {
            $note_error = "L?i khi thêm ghi chú: " . $stmt_note->error;
        }
        $stmt_note->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_note'])) {
    $note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
    $note_content = trim($_POST['note'] ?? '');

    if ($note_id <= 0 || $note_content === '') {
        $note_error = "Vui lòng nh?p n?i dung ghi chú.";
    } else {
        $stmt_update = $conn->prepare("UPDATE member_notes SET note = ? WHERE id = ? AND member_id = ?");
        $stmt_update->bind_param("sii", $note_content, $note_id, $id);
        if ($stmt_update->execute()) {
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?id=" . $id . "&note_updated=1");
            exit();
        } else {
            $note_error = "L?i khi c?p nh?t ghi chú: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    $note_id = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
    if ($note_id > 0) {
        $stmt_delete = $conn->prepare("DELETE FROM member_notes WHERE id = ? AND member_id = ?");
        $stmt_delete->bind_param("ii", $note_id, $id);
        if ($stmt_delete->execute()) {
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?id=" . $id . "&note_deleted=1");
            exit();
        } else {
            $note_error = "L?i khi xóa ghi chú: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }
}

// Ki?m tra thông báo t? URL
if (isset($_GET['note_success']) && $_GET['note_success'] === '1') {
    $note_success = "Ðã thêm ghi chú thành công.";
}
if (isset($_GET['note_updated']) && $_GET['note_updated'] === '1') {
    $note_success = "Ðã c?p nh?t ghi chú thành công.";
}
if (isset($_GET['note_deleted']) && $_GET['note_deleted'] === '1') {
    $note_success = "Ðã xóa ghi chú thành công.";
}

/* L?y thông tin h?i viên + gói t?p hi?n t?i */
$stmt = $conn->prepare("
    SELECT 
        m.*,
        p.package_name,
        p.price AS package_price,
        p.duration_months
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: " . $base_path . "members.php");
    exit();
}

$member = $result->fetch_assoc();
$stmt->close();

/* L?y l?ch s? gói */
$history = [];
$stmt_history = $conn->prepare("
    SELECT
        h.*,
        p.package_name
    FROM member_package_history h
    LEFT JOIN packages p ON h.package_id = p.id
    WHERE h.member_id = ?
    ORDER BY h.id DESC
");
$stmt_history->bind_param("i", $id);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

if ($result_history && $result_history->num_rows > 0) {
    while ($row = $result_history->fetch_assoc()) {
        $history[] = $row;
    }
}
$stmt_history->close();

// L?y ghi chú h?i viên (có l?c theo ngày n?u có)
$notes = [];
$sql_notes = "
    SELECT *
    FROM member_notes
    WHERE member_id = ?
";
if ($filter_note_date !== '') {
    $sql_notes .= " AND DATE(created_at) = ?";
}
$sql_notes .= " ORDER BY id DESC";

$stmt_notes = $conn->prepare($sql_notes);
if ($filter_note_date !== '') {
    $stmt_notes->bind_param("is", $id, $filter_note_date);
} else {
    $stmt_notes->bind_param("i", $id);
}
$stmt_notes->execute();
$result_notes = $stmt_notes->get_result();

if ($result_notes && $result_notes->num_rows > 0) {
    while ($row = $result_notes->fetch_assoc()) {
        $notes[] = $row;
    }
}
$stmt_notes->close();

$total_debt = 0.0;
$stmt_debt = $conn->prepare("
    SELECT COALESCE(SUM(remaining_amount), 0) AS total_debt
    FROM member_package_history
    WHERE member_id = ? AND remaining_amount > 0
");
$stmt_debt->bind_param("i", $id);
$stmt_debt->execute();
$result_debt = $stmt_debt->get_result();
if ($result_debt && $row_debt = $result_debt->fetch_assoc()) {
    $total_debt = (float) ($row_debt['total_debt'] ?? 0);
}
$stmt_debt->close();

function formatMemberStatus($status)
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Ðang ho?t d?ng</span>';
    }
    if ($status === 'expired') {
        return '<span class="badge bg-warning text-dark">H?t h?n</span>';
    }
    return '<span class="badge bg-secondary">Ngung ho?t d?ng</span>';
}

function formatHistoryStatus($status)
{
    if ($status === 'active') {
        return '<span class="badge bg-success">Ðang áp d?ng</span>';
    }
    if ($status === 'expired') {
        return '<span class="badge bg-warning text-dark">H?t h?n</span>';
    }
    return '<span class="badge bg-secondary">Ðã h?y</span>';
}
?><!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <style>
        .notes-list .note-item:last-child {
            border-bottom: none !important;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }
        .note-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <div class="main-content flex-grow-1">
            <?php include __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0">Chi ti?t h?i viên</h2>
                    <a href="<?php echo $base_path; ?>members.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Quay l?i
                    </a>
                </div>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Thông tin h?i viên</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="mb-3">
                                    <div class="text-muted small">H? và tên</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Gi?i tính</div>
                                    <div><?php echo htmlspecialchars($member['gender'] ?? ''); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">S? di?n tho?i</div>
                                    <div><?php echo htmlspecialchars($member['phone']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Email</div>
                                    <div><?php echo htmlspecialchars($member['email'] ?: 'Chua có'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Ngày sinh</div>
                                    <div><?php echo !empty($member['date_of_birth']) ? htmlspecialchars($member['date_of_birth']) : 'Chua có'; ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Ð?a ch?</div>
                                    <div><?php echo htmlspecialchars($member['address'] ?: 'Chua có'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Tr?ng thái</div>
                                    <div><?php echo formatMemberStatus($member['status']); ?></div>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">T?ng n?</div>
                                    <div class="fw-semibold text-danger">
                                        <?php echo number_format((float)$total_debt, 0, ',', '.'); ?> VNÐ
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <div class="text-muted small">Ngày t?o</div>
                                    <div><?php echo htmlspecialchars($member['created_at'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form thêm ghi chú -->
                        <div class="card shadow-sm border-0 mt-4" id="member-notes">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Thêm ghi chú</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($note_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($note_success); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($note_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($note_error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="add_note" value="1">
                                    <div class="mb-3">
                                        <label class="form-label">N?i dung ghi chú</label>
                                        <textarea name="note" class="form-control" rows="3" placeholder="Nh?p ghi chú v? h?i viên..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Thêm ghi chú
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">Gói hi?n t?i</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Tên gói</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($member['package_name'] ?: 'Chua có gói'); ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Giá gói</div>
                                        <div>
                                            <?php echo isset($member['package_price']) ? number_format((float)$member['package_price'], 0, ',', '.') . ' VNÐ' : 'Chua có'; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Ngày b?t d?u</div>
                                        <div><?php echo !empty($member['start_date']) ? htmlspecialchars($member['start_date']) : 'Chua có'; ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Ngày k?t thúc</div>
                                        <div><?php echo !empty($member['end_date']) ? htmlspecialchars($member['end_date']) : 'Chua có'; ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Th?i h?n gói</div>
                                        <div>
                                            <?php echo isset($member['duration_months']) ? (int)$member['duration_months'] . ' tháng' : 'Chua có'; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted small">Thao tác nhanh</div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="<?php echo $base_path; ?>php/members/edit-member.php?id=<?php echo (int)$member['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="bi bi-pencil me-1"></i>S?a h?i viên
                                            </a>

                                            <a href="<?php echo $base_path; ?>php/members/renew-package.php?id=<?php echo (int)$member['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-arrow-repeat me-1"></i>Gia h?n gói
                                            </a>
                                            <a href="<?php echo $base_path; ?>workout-plans.php?member_id=<?php echo (int)$member['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="bi bi-clipboard2-pulse me-1"></i>K? ho?ch t?p luy?n
                                            </a>
                                            <a href="<?php echo $base_path; ?>meal-plans.php?member_id=<?php echo (int)$member['id']; ?>" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-egg-fried me-1"></i>K? ho?ch dinh du?ng
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <h5 class="mb-0">L?ch s? gói t?p</h5>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Gói t?p</th>
                                                <th>Lo?i</th>
                                                <th>Th?i gian</th>
                                                <th>Thanh toán</th>
                                                <th>Tr?ng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($history)): ?>
                                                <?php foreach ($history as $item): ?>
                                                    <tr>
                                                        <td>#<?php echo (int)$item['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($item['package_name'] ?: 'Không xác d?nh'); ?></td>
                                                        <td><?php echo htmlspecialchars($item['action_type']); ?></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($item['start_date']); ?>
                                                            <br>
                                                            <small class="text-muted">d?n <?php echo htmlspecialchars($item['end_date']); ?></small>
                                                        </td>
                                                        <td>
                                                            <div>T?ng: <?php echo number_format((float)$item['price'], 0, ',', '.'); ?> VNÐ</div>
                                                            <small class="text-success d-block">Ðã tr?: <?php echo number_format((float)$item['paid_amount'], 0, ',', '.'); ?> VNÐ</small>
                                                            <small class="text-danger d-block">Còn n?: <?php echo number_format((float)$item['remaining_amount'], 0, ',', '.'); ?> VNÐ</small>
                                                        </td>
                                                        <td><?php echo formatHistoryStatus($item['status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">Chua có l?ch s? gói t?p.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Danh sách ghi chú -->
                        <div class="card shadow-sm border-0 mt-4">
                            <div class="card-header bg-white border-0 pt-4 px-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h5 class="mb-0">Danh sách ghi chú</h5>
                                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">
                                        <input type="date" name="note_date" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($filter_note_date); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">L?c</button>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?php echo $base_path; ?>php/members/view-member.php?id=<?php echo (int)$member['id']; ?>#member-notes">Reset</a>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body px-4 pb-4">
                                <?php if (!empty($notes)): ?>
                                    <div class="notes-list">
                                        <?php foreach ($notes as $note): ?>
                                            <div class="note-item border-bottom pb-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="fw-semibold">
                                                        <?php echo htmlspecialchars($note['created_by_name'] ?: 'Admin'); ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted d-block">
                                                            <?php echo !empty($note['created_at']) ? date('d/m/Y H:i', strtotime($note['created_at'])) : ''; ?>
                                                        </small>
                                                        <div class="mt-2 d-flex gap-2 justify-content-end">
                                                            <a class="btn btn-outline-secondary btn-sm"
                                                               href="<?php echo $base_path; ?>php/members/view-member.php?id=<?php echo (int)$member['id']; ?>&edit_note_id=<?php echo (int)$note['id']; ?>#member-notes">
                                                                <i class="bi bi-pencil-square me-1"></i>S?a
                                                            </a>
                                                            <form method="POST" action=""
                                                                  onsubmit="return confirm('Xóa ghi chú này?');">
                                                                <input type="hidden" name="delete_note" value="1">
                                                                <input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                    <i class="bi bi-trash me-1"></i>Xóa
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($edit_note_id === (int)$note['id']): ?>
                                                    <form method="POST" action="" class="mb-3">
                                                        <input type="hidden" name="update_note" value="1">
                                                        <input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
                                                        <div class="mb-2">
                                                            <textarea name="note" class="form-control" rows="3" required><?php echo htmlspecialchars($note['note']); ?></textarea>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <i class="bi bi-check-circle me-1"></i>Luu
                                                            </button>
                                                            <a class="btn btn-outline-secondary btn-sm"
                                                               href="<?php echo $base_path; ?>php/members/view-member.php?id=<?php echo (int)$member['id']; ?>#member-notes">
                                                                H?y
                                                            </a>
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="note-content">
                                                        <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-sticky-note fs-1 mb-2"></i>
                                        <div>Chua có ghi chú nào.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>


