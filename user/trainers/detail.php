<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function trainer_avatar_url(?string $avatar, string $name, string $basePath): string
{
    $avatar = trim((string) $avatar);

    if ($avatar !== '') {
        return $basePath . 'uploads/trainers/' . rawurlencode($avatar);
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0f172a&color=ffffff';
}

function review_initial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'U';
    }

    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($name, 0, 1));
}

$trainer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($trainer_id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, avatar, specialty, experience_years, phone, email, bio, rating, total_members, status, created_at
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

$avatar = trainer_avatar_url($trainer['avatar'] ?? '', (string) ($trainer['full_name'] ?? 'HLV FLEXZONE'), $base_path);
$specialty = trim((string) ($trainer['specialty'] ?? ''));

/* =========================
   LẤY ĐÁNH GIÁ HLV
========================= */
$reviews = [];
$total_reviews = 0;
$average_rating = 0;

$stmt_review = $conn->prepare("
    SELECT
        tr.id,
        tr.rating,
        tr.comment,
        tr.created_at,
        m.full_name,
        m.email
    FROM trainer_reviews tr
    JOIN members m ON m.id = tr.member_id
    WHERE tr.trainer_id = ?
      AND tr.status = 'show'
    ORDER BY tr.created_at DESC
    LIMIT 6
");
$stmt_review->bind_param('i', $trainer_id);
$stmt_review->execute();
$review_result = $stmt_review->get_result();

while ($row = $review_result->fetch_assoc()) {
    $reviews[] = $row;
}

$stmt_review->close();

$stmt_avg = $conn->prepare("
    SELECT
        COUNT(*) AS total_reviews,
        AVG(rating) AS average_rating
    FROM trainer_reviews
    WHERE trainer_id = ?
      AND status = 'show'
");
$stmt_avg->bind_param('i', $trainer_id);
$stmt_avg->execute();
$avg_row = $stmt_avg->get_result()->fetch_assoc();
$stmt_avg->close();

$total_reviews = (int) ($avg_row['total_reviews'] ?? 0);
$average_rating = $total_reviews > 0
    ? round((float) ($avg_row['average_rating'] ?? 0), 1)
    : (float) ($trainer['rating'] ?? 0);

$experience_years = (int) ($trainer['experience_years'] ?? 0);
$supported_members = (int) ($trainer['total_members'] ?? 0);
$contact_subject = 'Đặt HLV: ' . (string) ($trainer['full_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title><?php echo h($trainer['full_name']); ?> - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="../includes/assets/css/trainers.css?v=trainers-light-1">
</head>

<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="trainer-detail-page">
        <section class="trainer-detail-section">
            <div class="container">
                <div class="trainer-breadcrumb">
                    <a href="<?php echo $base_path; ?>user/home.php">Trang chủ</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="index.php">HLV</a>
                    <i class="bi bi-chevron-right"></i>
                    <span><?php echo h($trainer['full_name']); ?></span>
                </div>

                <div class="trainer-detail-layout">
                    <div class="trainer-profile-card">
                        <div class="trainer-detail-image-wrap">
                            <img
                                src="<?php echo h($avatar); ?>"
                                alt="<?php echo h($trainer['full_name']); ?>"
                                class="trainer-detail-image">
                        </div>

                        <div class="trainer-profile-content">
                            <span class="trainer-detail-badge">
                                <i class="bi bi-patch-check-fill"></i>
                                HLV đang nhận lịch
                            </span>

                            <h1><?php echo h($trainer['full_name']); ?></h1>

                            <p class="trainer-specialty">
                                <?php echo h($specialty !== '' ? $specialty : 'Huấn luyện cá nhân'); ?>
                            </p>

                            <p class="trainer-detail-bio">
                                <?php echo h(trim((string) ($trainer['bio'] ?? 'HLV đồng hành cùng hội viên theo mục tiêu tập luyện và thể trạng thực tế.'))); ?>
                            </p>

                            <div class="trainer-detail-stats">
                                <div>
                                    <strong><?php echo $experience_years; ?></strong>
                                    <span>Năm kinh nghiệm</span>
                                </div>

                                <div>
                                    <strong><?php echo h($average_rating); ?>/5</strong>
                                    <span><?php echo $total_reviews; ?> đánh giá</span>
                                </div>

                                <div>
                                    <strong><?php echo $supported_members; ?>+</strong>
                                    <span>Hội viên đã hỗ trợ</span>
                                </div>
                            </div>

                            <div class="trainer-detail-actions">
                                <a href="book.php?trainer_id=<?php echo (int)$trainer['id']; ?>" class="btn-trainer-primary">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Đặt HLV
                                </a>

                                   <a href="review.php?trainer_id=<?php echo (int)$trainer['id']; ?>" class="btn-trainer-outline">
        <i class="bi bi-star me-1"></i>
        Viết đánh giá
    </a>

                                <a href="index.php" class="btn-trainer-outline">
                                    Quay lại danh sách
                                </a>
                            </div>
                        </div>
                    </div>

                    <aside class="trainer-info-side">
                        <h3>Thông tin HLV</h3>

                        <div class="trainer-info-row">
                            <span><i class="bi bi-briefcase"></i> Chuyên môn</span>
                            <strong><?php echo h($specialty !== '' ? $specialty : 'Huấn luyện cá nhân'); ?></strong>
                        </div>

                        <div class="trainer-info-row">
                            <span><i class="bi bi-clock-history"></i> Kinh nghiệm</span>
                            <strong><?php echo $experience_years; ?> năm</strong>
                        </div>

                        <div class="trainer-info-row">
                            <span><i class="bi bi-star"></i> Đánh giá</span>
                            <strong><?php echo h($average_rating); ?>/5</strong>
                        </div>

                        <div class="trainer-info-row">
                            <span><i class="bi bi-people"></i> Hội viên</span>
                            <strong><?php echo $supported_members; ?>+</strong>
                        </div>

                        <div class="trainer-side-note">
                            <i class="bi bi-info-circle"></i>
                            <p>
                                Sau khi đặt lịch, admin sẽ kiểm tra và xác nhận yêu cầu tư vấn của bạn trong thời gian sớm nhất.
                            </p>
                        </div>
                    </aside>
                </div>

                <div class="trainer-review-section">
                    <div class="trainer-review-header">
                        <div>
                            <h2>Đánh giá từ hội viên</h2>
                            <p>Nhận xét thực tế của hội viên sau khi tập luyện hoặc tư vấn cùng HLV.</p>
                        </div>

                        <div class="trainer-review-score">
                            <strong><?php echo h($average_rating); ?></strong>
                            <span>/5</span>
                            <small><?php echo $total_reviews; ?> đánh giá</small>
                        </div>
                    </div>

                    <?php if (!empty($reviews)): ?>
                        <div class="trainer-review-grid">
                            <?php foreach ($reviews as $review): ?>
                                <article class="trainer-review-card">
                                    <div class="review-user">
                                        <div class="review-avatar">
                                            <?php echo h(review_initial((string) ($review['full_name'] ?? 'U'))); ?>
                                        </div>

                                        <div>
                                            <h4><?php echo h($review['full_name'] ?? 'Hội viên'); ?></h4>
                                            <span><?php echo !empty($review['created_at']) ? date('d/m/Y', strtotime((string) $review['created_at'])) : ''; ?></span>
                                        </div>
                                    </div>

                                    <div class="review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= (int) ($review['rating'] ?? 0)): ?>
                                                <i class="bi bi-star-fill"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>

                                    <p><?php echo h(trim((string) ($review['comment'] ?? ''))); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="trainer-review-empty">
                            <i class="bi bi-chat-left-text"></i>
                            <h4>Chưa có đánh giá</h4>
                            <p>HLV này chưa có nhận xét từ hội viên.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="trainer-detail-bottom">
                    <div class="trainer-service-card">
                        <i class="bi bi-clipboard2-check"></i>
                        <h4>Lộ trình rõ ràng</h4>
                        <p>HLV hỗ trợ xây dựng lịch tập phù hợp với thể trạng và mục tiêu.</p>
                    </div>

                    <div class="trainer-service-card">
                        <i class="bi bi-heart-pulse"></i>
                        <h4>Sửa kỹ thuật</h4>
                        <p>Hướng dẫn form tập đúng để giảm rủi ro chấn thương.</p>
                    </div>

                    <div class="trainer-service-card">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h4>Theo dõi tiến độ</h4>
                        <p>Đánh giá kết quả tập luyện và điều chỉnh kế hoạch theo từng giai đoạn.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
