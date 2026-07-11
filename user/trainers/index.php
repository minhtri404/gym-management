<?php
include __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/trainer-image-helper.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$specialty_filter = $_GET['specialty'] ?? 'all';

if ($specialty_filter !== 'all') {
    $search = '%' . $specialty_filter . '%';

    $stmt = $conn->prepare("
        SELECT id, full_name, avatar, specialty, experience_years, bio, rating, total_members, status
        FROM trainers
        WHERE status = 'active'
          AND specialty LIKE ?
        ORDER BY rating DESC, experience_years DESC
    ");
    $stmt->bind_param("s", $search);
} else {
    $stmt = $conn->prepare("
        SELECT id, full_name, avatar, specialty, experience_years, bio, rating, total_members, status
        FROM trainers
        WHERE status = 'active'
        ORDER BY rating DESC, experience_years DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();

$trainers = [];
while ($row = $result->fetch_assoc()) {
    $trainers[] = $row;
}

$stmt->close();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Huấn luyện viên - FLEXZONE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="../includes/assets/css/trainers.css?v=trainers-light-1">
</head>

<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="trainers-page">

    <section class="trainers-hero">
        <div class="container">
            <div class="trainers-hero-inner">
                <div>
                    <div class="trainers-eyebrow">
                        <i class="bi bi-person-badge me-1"></i>
                        Huấn luyện viên cá nhân
                    </div>

                    <h1>Chọn HLV phù hợp với mục tiêu của bạn</h1>

                    <p>
                        Đội ngũ huấn luyện viên tại FLEXZONE hỗ trợ hội viên xây dựng lịch tập,
                        sửa kỹ thuật và theo dõi tiến độ theo từng mục tiêu cụ thể.
                    </p>
                </div>

                <div class="trainers-summary-card">
                    <strong><?php echo count($trainers); ?> HLV</strong>
                    <span>đang sẵn sàng hỗ trợ hội viên</span>
                </div>
            </div>
        </div>
    </section>

    <section class="trainers-list-section">
        <div class="container">

            <div class="trainers-filter-bar">
                <a href="index.php?specialty=all"
                   class="trainer-filter <?php echo $specialty_filter === 'all' ? 'active' : ''; ?>">
                    Tất cả
                </a>

                <a href="index.php?specialty=Tăng cơ"
                   class="trainer-filter <?php echo $specialty_filter === 'Tăng cơ' ? 'active' : ''; ?>">
                    Tăng cơ
                </a>

                <a href="index.php?specialty=Giảm mỡ"
                   class="trainer-filter <?php echo $specialty_filter === 'Giảm mỡ' ? 'active' : ''; ?>">
                    Giảm mỡ
                </a>

                <a href="index.php?specialty=Cardio"
                   class="trainer-filter <?php echo $specialty_filter === 'Cardio' ? 'active' : ''; ?>">
                    Tim m&#7841;ch
                </a>

                <a href="index.php?specialty=Powerlifting"
                   class="trainer-filter <?php echo $specialty_filter === 'Powerlifting' ? 'active' : ''; ?>">
                    S&#7913;c m&#7841;nh
                </a>
            </div>

            <?php if (!empty($trainers)): ?>
                <div class="trainers-grid">
                    <?php foreach ($trainers as $trainer): ?>
                        <?php
                        $trainer_id = (int)$trainer['id'];
                        $avatar = resolve_trainer_avatar_url($trainer_id, $trainer['avatar'] ?? '', $base_path);
                        ?>

                        <article class="trainer-card" id="trainer-<?php echo $trainer_id; ?>">
                            <div class="trainer-avatar-wrap">
                                <img src="<?php echo h($avatar); ?>"
                                     alt="<?php echo h($trainer['full_name']); ?>"
                                     class="trainer-avatar">

                                <span class="trainer-status">
                                    <i class="bi bi-circle-fill"></i>
                                    Đang nhận lịch
                                </span>
                            </div>

                            <div class="trainer-info">
                                <div class="trainer-top">
                                    <div>
                                        <h3><?php echo h($trainer['full_name']); ?></h3>
                                        <p><?php echo h($trainer['specialty']); ?></p>
                                    </div>

                                    <div class="trainer-rating">
                                        <i class="bi bi-star-fill"></i>
                                        <?php echo h($trainer['rating']); ?>
                                    </div>
                                </div>

                                <p class="trainer-bio">
                                    <?php echo h($trainer['bio']); ?>
                                </p>

                                <div class="trainer-meta">
                                    <span>
                                        <i class="bi bi-briefcase"></i>
                                        <?php echo (int)$trainer['experience_years']; ?> năm kinh nghiệm
                                    </span>

                                    <span>
                                        <i class="bi bi-people"></i>
                                        <?php echo (int)$trainer['total_members']; ?>+ hội viên
                                    </span>
                                </div>

                                <div class="trainer-actions">
                                    <a href="detail.php?id=<?php echo $trainer_id; ?>" class="btn-trainer-outline">
                                        Xem hồ sơ
                                    </a>

                                    <a href="book.php?trainer_id=<?php echo $trainer_id; ?>" class="btn-trainer-primary">
                                        Đặt lịch tư vấn
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="trainers-empty">
                    <i class="bi bi-person-x"></i>
                    <h3>Chưa có HLV phù hợp</h3>
                    <p>Hiện chưa có huấn luyện viên thuộc nhóm chuyên môn bạn chọn.</p>
                </div>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
