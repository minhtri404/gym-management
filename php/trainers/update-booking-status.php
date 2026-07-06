<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../admin/trainer-bookings.php');
    exit;
}

$csrf_token = (string) ($_POST['csrf_token'] ?? '');
if ($csrf_token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf_token)) {
    header('Location: ../../admin/trainer-bookings.php?status=csrf_error');
    exit;
}

$booking_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';

$allowed_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];

if ($booking_id <= 0 || !in_array($status, $allowed_statuses, true)) {
    header('Location: ../../admin/trainer-bookings.php');
    exit;
}

$table_check = $conn->query("SHOW TABLES LIKE 'trainer_bookings'");
if (!$table_check || $table_check->num_rows === 0) {
    header('Location: ../../admin/trainer-bookings.php');
    exit;
}

$stmt = $conn->prepare("
    UPDATE trainer_bookings
    SET status = ?
    WHERE id = ?
");
$stmt->bind_param("si", $status, $booking_id);
$stmt->execute();
$stmt->close();

header('Location: ../../admin/trainer-bookings.php?status=updated');
exit;
