<?php
$page_title = 'Quản lý đánh giá HLV';
include __DIR__ . '/../includes/auth-check.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function stars($rating)
{
    $html = '';

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= (int) $rating) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-warning"></i>';
        }
    }

    return $html;
}

$reviews = [];
$keyword = trim($_GET['q'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

$sql = "
    SELECT
        tr.id,
        tr.rating,
        tr.comment,
        tr.status,
        tr.created_at,
        t.full_name AS trainer_name,
        t.specialty,
        m.full_name AS member_name,
        m.phone AS member_phone,
        m.email AS member_email
    FROM trainer_reviews tr
    JOIN trainers t ON t.id = tr.trainer_id
    JOIN members m ON m.id = tr.member_id
";

$where = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $where[] = '(t.full_name LIKE ? OR m.full_name LIKE ?)';
    $search = '%' . $keyword . '%';
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (in_array($filter_status, ['show', 'hide'], true)) {
    $where[] = 'tr.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY tr.created_at DESC';

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý đánh giá HLV</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-page">

<div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Quản lý đánh giá HLV</h2>
                    <p class="text-muted mb-0">
                        Theo dõi và kiểm duyệt nhận xét của hội viên dành cho huấn luyện viên.
                    </p>
                </div>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                <div class="alert alert-success">
                    Cập nhật trạng thái đánh giá thành công.
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-6">
                            <label class="form-label">Tìm kiếm</label>
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                placeholder="Tên HLV hoặc tên hội viên"
                                value="<?php echo h($keyword); ?>"
                            >
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label">Lọc theo trạng thái</label>
                            <select name="filter_status" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="show" <?php echo $filter_status === 'show' ? 'selected' : ''; ?>>Đang hiển thị</option>
                                <option value="hide" <?php echo $filter_status === 'hide' ? 'selected' : ''; ?>>Đang ẩn</option>
                            </select>
                        </div>

                        <div class="col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>Tìm / Lọc
                            </button>
                            <a href="trainer-reviews.php" class="btn btn-outline-secondary">
                                Đặt lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold">Danh sách đánh giá</h5>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Hội viên</th>
                                <th>HLV</th>
                                <th>Đánh giá</th>
                                <th>Bình luận</th>
                                <th>Trạng thái</th>
                                <th>Ngày gửi</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (!empty($reviews)): ?>
                                <?php foreach ($reviews as $review): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($review['member_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo h($review['member_phone'] ?: $review['member_email']); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?php echo h($review['trainer_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo h($review['specialty']); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php echo stars($review['rating']); ?>
                                            <div class="small text-muted">
                                                <?php echo (int) $review['rating']; ?>/5
                                            </div>
                                        </td>

                                        <td style="max-width: 360px;">
                                            <?php echo h($review['comment']); ?>
                                        </td>

                                        <td>
                                            <?php if ($review['status'] === 'show'): ?>
                                                <span class="badge bg-success">Đang hiển thị</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Đang ẩn</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php echo !empty($review['created_at']) ? date('d/m/Y H:i', strtotime($review['created_at'])) : ''; ?>
                                        </td>

                                        <td class="text-end">
                                            <?php if ($review['status'] === 'show'): ?>
                                                <form method="POST" action="../php/trainers/toggle-review.php" class="d-inline">
                                                    <input type="hidden" name="id" value="<?php echo (int) $review['id']; ?>">
                                                    <input type="hidden" name="status" value="hide">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Ẩn</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="../php/trainers/toggle-review.php" class="d-inline">
                                                    <input type="hidden" name="id" value="<?php echo (int) $review['id']; ?>">
                                                    <input type="hidden" name="status" value="show">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Hiện</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Không có đánh giá HLV phù hợp với điều kiện tìm kiếm.
                                    </td>
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
