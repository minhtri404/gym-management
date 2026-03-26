<?php
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base_path . "members.php");
    exit();
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    header("Location: " . $base_path . "members.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: " . $base_path . "members.php?delete=success");
    exit();
}

$stmt->close();
header("Location: " . $base_path . "members.php");
exit();
