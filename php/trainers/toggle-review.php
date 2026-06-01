<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$review_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$status = $_GET['status'] ?? '';

if ($review_id <= 0 || !in_array($status, ['show', 'hide'], true)) {
    header('Location: ../../admin/trainer-reviews.php');
    exit;
}

$stmt = $conn->prepare("
    UPDATE trainer_reviews
    SET status = ?
    WHERE id = ?
");
$stmt->bind_param('si', $status, $review_id);
$stmt->execute();
$stmt->close();

header('Location: ../../admin/trainer-reviews.php?status=updated');
exit;
