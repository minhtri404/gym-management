<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$status = trim((string) ($_GET['status'] ?? ''));

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
