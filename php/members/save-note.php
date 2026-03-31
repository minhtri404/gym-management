<?php
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base_path . "members.php");
    exit();
}

$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
$note = trim($_POST['note'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

if ($member_id <= 0) {
    header("Location: " . $base_path . "members.php");
    exit();
}

if ($csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&note=csrf_error");
    exit();
}

if ($note === '') {
    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&note=empty");
    exit();
}

$created_by_name = $_SESSION['admin_full_name'] ?? ($_SESSION['admin_username'] ?? 'Admin');

$stmt = $conn->prepare("
    INSERT INTO member_notes (member_id, note, created_by_name)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $member_id, $note, $created_by_name);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&note=success");
    exit();
} else {
    $stmt->close();
    header("Location: " . $base_path . "php/members/view-member.php?id=" . $member_id . "&note=error");
    exit();
}