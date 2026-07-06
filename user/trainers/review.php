<?php
include __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/trainer-image-helper.php';

$base_path = '../../';

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../../login.php');
    exit;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$user_id = (int)$_SESSION['user_id'];
$trainer_id = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : 0;

if ($trainer_id <= 0) {
    header('Location: index.php');
    exit;
}

/* Lấy HLV */
$stmt = $conn->prepare("
    SELECT id, full_name, avatar, specialty, status
    FROM trainers
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trainer) {
    header('Location: index.php');
    exit;
}

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

/* Tìm member theo email/phone */
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

$avatar = resolve_trainer_avatar_url($trainer_id, $trainer['avatar'] ?? '', $base_path);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$member) {
        $error = 'Tài khoản của bạn chưa liên kết với hồ sơ hội viên.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $member_id = (int)$member['id'];

        if ($rating < 1 || $rating > 5) {
            $error = 'Vui lòng chọn số sao từ 1 đến 5.';
        } elseif ($comment === '') {
            $error = 'Vui lòng nhập nội dung đánh giá.';
        } else {
            /*
             * Mỗi hội viên chỉ đánh giá 1 lần cho 1 HLV.
             * Nếu đã đánh giá thì cập nhật lại.
             */
            $stmt_check = $conn->prepare("
                SELECT id
                FROM trainer_reviews
                WHERE trainer_id = ?
                  AND member_id = ?
                LIMIT 1
            ");
            $stmt_check->bind_param("ii", $trainer_id, $member_id);
            $stmt_check->execute();
            $old_review = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($old_review) {
                $review_id = (int)$old_review['id'];

                $stmt_update = $conn->prepare("
                    UPDATE trainer_reviews
                    SET rating = ?,
                        comment = ?,
                        status = 'show',
                        created_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt_update->bind_param("isi", $rating, $comment, $review_id);

                if ($stmt_update->execute()) {
                    $success = 'Cập nhật đánh giá thành công.';
                } else {
                    $error = 'Có lỗi xảy ra khi cập nhật đánh giá.';
                }

                $stmt_update->close();
            } else {
                $stmt_insert = $conn->prepare("
                    INSERT INTO trainer_reviews (
                        trainer_id,
                        member_id,
                        rating,
                        comment,
                        status
                    )
                    VALUES (?, ?, ?, ?, 'show')
                ");
                $stmt_insert->bind_param("iiis", $trainer_id, $member_id, $rating, $comment);

                if ($stmt_insert->execute()) {
                    $success = 'Gửi đánh giá thành công.';
                } else {
                    $error = 'Có lỗi xảy ra khi gửi đánh giá.';
                }

                $stmt_insert->close();
            }

            if ($success) {
                header('Location: detail.php?id=' . $trainer_id . '&review=success');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đánh giá HLV - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="../includes/assets/css/trainers.css?v=trainers-light-1">
</head>

<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="trainer-book-page">
    <section class="trainer-book-section">
        <div class="container">

            <div class="trainer-breadcrumb">
                <a href="index.php">HLV</a>
                <i class="bi bi-chevron-right"></i>
                <a href="detail.php?id=<?php echo (int)$trainer['id']; ?>">
                    <?php echo h($trainer['full_name']); ?>
                </a>
                <i class="bi bi-chevron-right"></i>
                <span>Đánh giá</span>
            </div>

            <div class="trainer-book-layout">

                <div class="trainer-book-form-card">
                    <div class="trainer-book-header">
                        <h1>Đánh giá HLV</h1>
                        <p>Chia sẻ trải nghiệm của bạn sau khi được HLV hỗ trợ.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo h($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">Số sao đánh giá</label>
                            <select name="rating" class="form-control" required>
                                <option value="">Chọn số sao</option>
                                <option value="5">5 sao - Rất tốt</option>
                                <option value="4">4 sao - Tốt</option>
                                <option value="3">3 sao - Bình thường</option>
                                <option value="2">2 sao - Chưa hài lòng</option>
                                <option value="1">1 sao - Không hài lòng</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nhận xét của bạn</label>
                            <textarea name="comment"
                                      rows="6"
                                      class="form-control"
                                      placeholder="Ví dụ: HLV hướng dẫn kỹ, sửa form tốt, lịch tập phù hợp..."
                                      required></textarea>
                        </div>

                        <div class="trainer-book-actions">
                            <a href="detail.php?id=<?php echo (int)$trainer['id']; ?>" class="btn-trainer-outline">
                                Quay lại
                            </a>

                            <button type="submit" class="btn-trainer-primary">
                                <i class="bi bi-star me-1"></i>
                                Gửi đánh giá
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="trainer-book-side">
                    <img src="<?php echo h($avatar); ?>"
                         alt="<?php echo h($trainer['full_name']); ?>"
                         class="trainer-book-avatar">

                    <h3><?php echo h($trainer['full_name']); ?></h3>
                    <p><?php echo h($trainer['specialty']); ?></p>

                    <div class="trainer-side-note">
                        <i class="bi bi-info-circle"></i>
                        <p>
                            Đánh giá của bạn sẽ giúp hội viên khác chọn HLV phù hợp hơn.
                        </p>
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
