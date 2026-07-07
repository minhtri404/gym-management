<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    header('Location: ' . $base_path . 'trainers.php?status=csrf_error');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = trim((string) ($_POST['status'] ?? ''));

if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    header('Location: ' . $base_path . 'trainers.php');
    exit;
}

$stmt = $conn->prepare('UPDATE trainers SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);
$stmt->execute();
$stmt->close();

header('Location: ' . $base_path . 'trainers.php?status=updated');
exit;
