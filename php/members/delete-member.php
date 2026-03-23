<?php
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
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
