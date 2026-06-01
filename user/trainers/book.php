<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (empty($_SESSION['user_id'])) {
    $_SESSION['post_login_redirect'] = 'user/trainers/book.php?trainer_id=' . (int)($_GET['trainer_id'] ?? 0);
    header('Location: ../../login.php');
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function trainer_book_avatar($avatar, $name, $base_path)
{
    if (!empty($avatar)) {
        return $base_path . 'uploads/trainers/' . rawurlencode($avatar);
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0f172a&color=ffffff';
}

$trainer_id = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : (int)($_POST['trainer_id'] ?? 0);

if ($trainer_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, avatar, specialty, experience_years, bio, rating, total_members, status
    FROM trainers
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");
$stmt->bind_param('i', $trainer_id);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trainer) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$account_name = trim((string)($user['full_name'] ?? ''));
$account_phone = trim((string)($user['phone'] ?? ''));
$account_email = trim((string)($user['email'] ?? ''));

$member = null;
if ($user) {
    $phone = $account_phone;
    $email = $account_email;

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param('ss', $phone, $email);
    $stmt_member->execute();
    $member = $stmt_member->get_result()->fetch_assoc();
    $stmt_member->close();
}

$table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'trainer_bookings'");
$table_exists = $table_check && $table_check->num_rows > 0;

$errors = [];
$success = false;
$booking_date = $_POST['booking_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$goal = trim((string)($_POST['goal'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $errors[] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng thử lại.';
    }

    if (!$table_exists) {
        $errors[] = 'Bảng lịch HLV chưa tồn tại. Vui lòng tạo bảng trainer_bookings trong database.';
    }

    if (!$member) {
        $errors[] = 'Tài khoản của bạn chưa liên kết với hồ sơ hội viên, chưa thể đặt lịch HLV.';
    }

    if ($booking_date === '') {
        $errors[] = 'Vui lòng chọn ngày tư vấn.';
    }

    if ($start_time === '') {
        $errors[] = 'Vui lòng chọn giờ bắt đầu.';
    }

    if ($goal === '') {
        $errors[] = 'Vui lòng nhập mục tiêu tư vấn.';
    }

    $start = null;
    if ($booking_date !== '' && $start_time !== '') {
        $start = DateTime::createFromFormat('Y-m-d H:i', $booking_date . ' ' . $start_time);
        $today = new DateTime('today');

        if (!$start) {
            $errors[] = 'Thời gian tư vấn không hợp lệ.';
        } elseif ($start < $today) {
            $errors[] = 'Không thể đặt lịch cho ngày đã qua.';
        }
    }

    if (!$errors && $member && $start) {
        $end = clone $start;
        $end->modify('+1 hour');

        $member_id = (int)$member['id'];
        $date_value = $start->format('Y-m-d');
        $start_value = $start->format('H:i:s');
        $end_value = $end->format('H:i:s');
        $status = 'pending';

        $stmt_insert = $conn->prepare("
            INSERT INTO trainer_bookings (
                trainer_id,
                member_id,
                booking_date,
                start_time,
                end_time,
                goal,
                note,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->bind_param(
            'iissssss',
            $trainer_id,
            $member_id,
            $date_value,
            $start_value,
            $end_value,
            $goal,
            $note,
            $status
        );

        if ($stmt_insert->execute()) {
            $stmt_insert->close();
            header('Location: my-bookings.php?booking=success');
            exit;
        }

        $errors[] = 'Không thể lưu lịch HLV. Vui lòng thử lại.';
        $stmt_insert->close();
    }
}

$avatar = trainer_book_avatar($trainer['avatar'] ?? '', $trainer['full_name'] ?? 'HLV FLEXZONE', $base_path);
$min_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lịch HLV - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="../includes/assets/css/trainers.css?v=trainers-light-1">
    <style>
        .trainer-account-panel {
            background: rgba(2, 6, 23, 0.32);
            border: 1px solid rgba(56, 189, 248, 0.18);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .trainer-book-form .form-control[readonly] {
            background: rgba(30, 41, 59, 0.72);
            border-color: rgba(148, 163, 184, 0.22);
            color: #e5e7eb;
            cursor: default;
        }

        .trainer-account-warning {
            color: #facc15;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
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
                <span>Đặt lịch tư vấn</span>
            </div>

            <div class="trainer-book-layout">
                <div class="trainer-book-form-card">
                    <div class="trainer-book-header">
                        <span class="trainers-eyebrow">
                            <i class="bi bi-calendar-check me-1"></i>
                            Đặt lịch HLV
                        </span>
                        <h1>Chọn thời gian tư vấn</h1>
                        <p>Gửi yêu cầu đặt lịch, admin sẽ xác nhận và cập nhật trạng thái trong lịch HLV của bạn.</p>
                    </div>

                    <?php if (!$table_exists): ?>
                        <div class="alert alert-warning">
                            Bảng <code>trainer_bookings</code> chưa tồn tại. Hãy chạy file
                            <code>database/create_trainer_bookings_table.sql</code> trước.
                        </div>
                    <?php endif; ?>

                    <?php if (!$member): ?>
                        <div class="alert alert-warning">
                            Tài khoản của bạn chưa liên kết với hồ sơ hội viên. Vui lòng dùng email hoặc số điện thoại trùng với hồ sơ hội viên để đặt lịch.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo h($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="trainer-book-form">
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="trainer_id" value="<?php echo (int)$trainer_id; ?>">

                        <div class="trainer-account-panel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">H&#7885; t&ecirc;n</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?php echo $account_name !== '' ? h($account_name) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?>"
                                        readonly>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">S&#7889; &#273;i&#7879;n tho&#7841;i</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?php echo $account_phone !== '' ? h($account_phone) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?>"
                                        readonly>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Email</label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        value="<?php echo $account_email !== '' ? h($account_email) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?>"
                                        readonly>
                                </div>
                            </div>

                            <?php if ($account_phone === '' || $account_email === ''): ?>
                                <div class="trainer-account-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Account ch&#432;a &#273;&#7911; S&#272;T/email. H&atilde;y c&#7853;p nh&#7853;t h&#7891; s&#417; &#273;&#7875; admin li&ecirc;n h&#7879; d&#7877; h&#417;n.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ngày tư vấn</label>
                                <input
                                    type="date"
                                    name="booking_date"
                                    class="form-control"
                                    min="<?php echo h($min_date); ?>"
                                    value="<?php echo h($booking_date); ?>"
                                    required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Giờ bắt đầu</label>
                                <input
                                    type="time"
                                    name="start_time"
                                    class="form-control"
                                    value="<?php echo h($start_time ?: '08:00'); ?>"
                                    required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Mục tiêu tư vấn</label>
                                <input
                                    type="text"
                                    name="goal"
                                    class="form-control"
                                    maxlength="255"
                                    placeholder="Ví dụ: tăng cơ, giảm mỡ, sửa form squat..."
                                    value="<?php echo h($goal); ?>"
                                    required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Ghi chú thêm</label>
                                <textarea
                                    name="note"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Thời gian rảnh, tình trạng sức khỏe, kinh nghiệm tập luyện..."><?php echo h($note); ?></textarea>
                            </div>
                        </div>

                        <div class="trainer-book-actions">
                            <a href="detail.php?id=<?php echo (int)$trainer_id; ?>" class="btn-trainer-outline">
                                Quay lại
                            </a>
                            <button type="submit" class="btn-trainer-primary" <?php echo (!$table_exists || !$member) ? 'disabled' : ''; ?>>
                                <i class="bi bi-send me-1"></i>
                                Gửi yêu cầu đặt lịch
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="trainer-book-side">
                    <img src="<?php echo h($avatar); ?>" alt="<?php echo h($trainer['full_name']); ?>" class="trainer-book-avatar">
                    <h3><?php echo h($trainer['full_name']); ?></h3>
                    <p><?php echo h($trainer['specialty'] ?? 'Huấn luyện cá nhân'); ?></p>

                    <div class="trainer-info-row">
                        <span><i class="bi bi-briefcase"></i> Kinh nghiệm</span>
                        <strong><?php echo (int)($trainer['experience_years'] ?? 0); ?> năm</strong>
                    </div>

                    <div class="trainer-info-row">
                        <span><i class="bi bi-star"></i> Đánh giá</span>
                        <strong><?php echo h($trainer['rating'] ?? 0); ?>/5</strong>
                    </div>

                    <div class="trainer-side-note">
                        <i class="bi bi-info-circle"></i>
                        <p>Lịch đặt mới sẽ ở trạng thái chờ xác nhận cho đến khi admin duyệt.</p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
