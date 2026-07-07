<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    header('Location: ../../admin/trainer-reviews.php?status=csrf_error');
    exit;
}

$review_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = $_POST['status'] ?? '';

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
