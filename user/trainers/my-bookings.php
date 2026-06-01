<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_text($status)
{
    switch ($status) {
        case 'pending':
            return 'Chờ xác nhận';
        case 'confirmed':
            return 'Đã xác nhận';
        case 'completed':
            return 'Hoàn thành';
        case 'cancelled':
            return 'Đã hủy';
        default:
            return 'Không rõ';
    }
}

function status_class($status)
{
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'confirmed':
            return 'status-confirmed';
        case 'completed':
            return 'status-completed';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

$user_id = (int)$_SESSION['user_id'];

/* Lấy user */
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

/* Tìm member theo phone/email */
$member = null;

if ($user) {
    $phone = trim((string)($user['phone'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param("ss", $phone, $email);
    $stmt_member->execute();
    $member = $stmt_member->get_result()->fetch_assoc();
    $stmt_member->close();
}

$bookings = [];

if ($member) {
    $member_id = (int)$member['id'];

    $stmt = $conn->prepare("
        SELECT 
            tb.id,
            tb.booking_date,
            tb.start_time,
            tb.end_time,
            tb.goal,
            tb.note,
            tb.status,
            tb.created_at,

            t.id AS trainer_id,
            t.full_name AS trainer_name,
            t.avatar AS trainer_avatar,
            t.specialty AS trainer_specialty
        FROM trainer_bookings tb
        JOIN trainers t ON t.id = tb.trainer_id
        WHERE tb.member_id = ?
        ORDER BY tb.booking_date DESC, tb.start_time DESC
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch HLV của tôi - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css">
    <link rel="stylesheet" href="../includes/assets/css/trainers.css">
</head>

<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="trainer-book-page">
    <section class="trainer-book-section">
        <div class="container">

            <div class="trainer-breadcrumb">
                <a href="<?php echo $base_path; ?>user/home.php">Trang chủ</a>
                <i class="bi bi-chevron-right"></i>
                <a href="index.php">HLV</a>
                <i class="bi bi-chevron-right"></i>
                <span>Lịch HLV của tôi</span>
            </div>

            <div class="trainer-book-form-card">

                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h1 class="mb-2">Lịch HLV của tôi</h1>
                        <p class="text-secondary mb-0">
                            Theo dõi các lịch tư vấn cá nhân bạn đã đặt với huấn luyện viên.
                        </p>
                    </div>

                    <a href="index.php" class="btn-trainer-primary" style="width:auto;">
                        <i class="bi bi-plus-circle me-1"></i>
                        Đặt HLV mới
                    </a>
                </div>

                <?php if (!$member): ?>
                    <div class="alert alert-warning">
                        Tài khoản của bạn chưa liên kết với hồ sơ hội viên nên chưa thể xem lịch HLV.
                    </div>
                <?php else: ?>

                    <?php if (!empty($bookings)): ?>
                        <div class="my-booking-list">

                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $avatar = !empty($booking['trainer_avatar'])
                                    ? $base_path . 'uploads/trainers/' . $booking['trainer_avatar']
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($booking['trainer_name']) . '&background=0f172a&color=ffffff';
                                ?>

                                <div class="my-booking-card">

                                    <img src="<?php echo h($avatar); ?>"
                                         alt="<?php echo h($booking['trainer_name']); ?>"
                                         class="my-booking-avatar">

                                    <div class="my-booking-info">
                                        <div class="my-booking-top">
                                            <div>
                                                <h3><?php echo h($booking['trainer_name']); ?></h3>
                                                <p><?php echo h($booking['trainer_specialty']); ?></p>
                                            </div>

                                            <span class="booking-status <?php echo status_class($booking['status']); ?>">
                                                <?php echo status_text($booking['status']); ?>
                                            </span>
                                        </div>

                                        <div class="my-booking-meta">
                                            <span>
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?>
                                            </span>

                                            <span>
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('H:i', strtotime($booking['start_time'])); ?>
                                                -
                                                <?php echo date('H:i', strtotime($booking['end_time'])); ?>
                                            </span>

                                            <span>
                                                <i class="bi bi-bullseye"></i>
                                                <?php echo h($booking['goal']); ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($booking['note'])): ?>
                                            <div class="my-booking-note">
                                                <strong>Ghi chú:</strong>
                                                <?php echo h($booking['note']); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="my-booking-actions">
                                            <a href="detail.php?id=<?php echo (int)$booking['trainer_id']; ?>" class="btn-trainer-outline">
                                                Xem HLV
                                            </a>

                                            <?php if ($booking['status'] === 'completed'): ?>
                                                <a href="review.php?trainer_id=<?php echo (int)$booking['trainer_id']; ?>" class="btn-trainer-primary">
                                                    Đánh giá HLV
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>

                        </div>
                    <?php else: ?>
                        <div class="trainers-empty">
                            <i class="bi bi-calendar-x"></i>
                            <h3>Bạn chưa đặt lịch HLV</h3>
                            <p>Hãy chọn một huấn luyện viên phù hợp và đặt lịch tư vấn.</p>

                            <a href="index.php" class="btn-trainer-primary mt-3" style="display:inline-block; width:auto;">
                                Xem danh sách HLV
                            </a>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>