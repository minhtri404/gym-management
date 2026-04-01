<?php
include __DIR__ . '/includes/config.php';

$base_path = '';

$checkin_id = isset($_GET['checkin_id']) ? (int)$_GET['checkin_id'] : 0;
$checkin = null;
$error_message = '';
$success_message = '';

if ($checkin_id <= 0) {
    $error_message = 'Liên kết đánh giá không hợp lệ.';
} else {
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.member_id,
            c.checkin_time,
            c.checkout_time,
            m.full_name,
            m.phone
        FROM checkins c
        INNER JOIN members m ON c.member_id = m.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $checkin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $checkin = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$checkin) {
        $error_message = 'Không tìm thấy buổi tập này.';
    } elseif (empty($checkin['checkout_time'])) {
        $error_message = 'Buổi tập này chưa check-out nên chưa thể đánh giá.';
    } else {
        $stmt_feedback = $conn->prepare("
            SELECT id, feedback, created_at
            FROM workout_feedbacks
            WHERE checkin_id = ?
            LIMIT 1
        ");
        $stmt_feedback->bind_param("i", $checkin_id);
        $stmt_feedback->execute();
        $result_feedback = $stmt_feedback->get_result();
        $existing_feedback = $result_feedback ? $result_feedback->fetch_assoc() : null;
        $stmt_feedback->close();

        if ($existing_feedback) {
            $success_message = 'Bạn đã gửi đánh giá cho buổi tập này rồi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá buổi tập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f8f9fa;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Đánh giá buổi tập</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message !== ''): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php elseif ($success_message !== ''): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>

                            <?php if (!empty($existing_feedback)): ?>
                                <div class="border rounded p-3 bg-light">
                                    <div><strong>Hội viên:</strong> <?php echo htmlspecialchars($checkin['full_name']); ?></div>
                                    <div><strong>SĐT:</strong> <?php echo htmlspecialchars($checkin['phone']); ?></div>
                                    <div><strong>Giờ vào:</strong> <?php echo htmlspecialchars($checkin['checkin_time']); ?></div>
                                    <div><strong>Giờ ra:</strong> <?php echo htmlspecialchars($checkin['checkout_time']); ?></div>
                                    <div><strong>Đánh giá:</strong> <?php echo htmlspecialchars($existing_feedback['feedback']); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mb-3">
                                <div><strong>Hội viên:</strong> <?php echo htmlspecialchars($checkin['full_name']); ?></div>
                                <div><strong>SĐT:</strong> <?php echo htmlspecialchars($checkin['phone']); ?></div>
                                <div><strong>Giờ vào:</strong> <?php echo htmlspecialchars($checkin['checkin_time']); ?></div>
                                <div><strong>Giờ ra:</strong> <?php echo htmlspecialchars($checkin['checkout_time']); ?></div>
                            </div>

                            <form method="POST" action="<?php echo $base_path; ?>php/checkins/save-feedback.php">
                                <input type="hidden" name="checkin_id" value="<?php echo (int)$checkin['id']; ?>">
                                <input type="hidden" name="member_id" value="<?php echo (int)$checkin['member_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Hôm nay buổi tập của bạn thế nào?</label>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="feedback" id="feedback_tot" value="Tốt" required>
                                        <label class="form-check-label" for="feedback_tot">Tốt</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="feedback" id="feedback_binh_thuong" value="Bình thường" required>
                                        <label class="form-check-label" for="feedback_binh_thuong">Bình thường</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="feedback" id="feedback_met" value="Mệt" required>
                                        <label class="form-check-label" for="feedback_met">Mệt</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="feedback" id="feedback_nghi_som" value="Nghỉ sớm" required>
                                        <label class="form-check-label" for="feedback_nghi_som">Nghỉ sớm</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    Gửi đánh giá
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="text-center text-muted mt-3 mb-0">
                    Cảm ơn bạn đã phản hồi sau buổi tập.
                </p>
            </div>
        </div>
    </div>
</body>
</html>