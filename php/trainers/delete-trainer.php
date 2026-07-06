<?php
include __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions/trainer-image-helper.php';

$base_path = '../../admin/';
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$token = (string) ($_POST['csrf_token'] ?? '');

if ($id <= 0 || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    header('Location: ' . $base_path . 'trainers.php?delete=error');
    exit;
}

$bookingTableExists = ($conn->query("SHOW TABLES LIKE 'trainer_bookings'")?->num_rows ?? 0) > 0;
$reviewTableExists = ($conn->query("SHOW TABLES LIKE 'trainer_reviews'")?->num_rows ?? 0) > 0;
$related = 0;

if ($bookingTableExists) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM trainer_bookings WHERE trainer_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $related += (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

if ($reviewTableExists) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM trainer_reviews WHERE trainer_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $related += (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

if ($related > 0) {
    $stmt = $conn->prepare("UPDATE trainers SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . $base_path . 'trainers.php?delete=soft');
    exit;
}

$stmt = $conn->prepare('SELECT avatar FROM trainers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM trainers WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $stmt->close();

    $avatar = (string) ($trainer['avatar'] ?? '');
    if (is_local_trainer_uploaded_file($avatar)) {
        @unlink(__DIR__ . '/../../uploads/trainers/' . basename($avatar));
    }

    header('Location: ' . $base_path . 'trainers.php?delete=success');
    exit;
}

$stmt->close();
header('Location: ' . $base_path . 'trainers.php?delete=error');
exit;
