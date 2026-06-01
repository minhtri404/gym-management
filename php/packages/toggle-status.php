<?php
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'packages.php');
    exit();
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    header('Location: ' . $base_path . 'packages.php?toggle=invalid');
    exit();
}

$stmt = $conn->prepare('SELECT status FROM packages WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$package = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$package) {
    header('Location: ' . $base_path . 'packages.php?toggle=not_found');
    exit();
}

$current_status = trim((string) ($package['status'] ?? 'inactive'));
$next_status = $current_status === 'active' ? 'inactive' : 'active';

$stmt = $conn->prepare('UPDATE packages SET status = ? WHERE id = ?');
$stmt->bind_param('si', $next_status, $id);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: ' . $base_path . 'packages.php?toggle=success');
    exit();
}

$stmt->close();
header('Location: ' . $base_path . 'packages.php?toggle=failed');
exit();
