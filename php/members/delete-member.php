<?php
include __DIR__ . '/../../includes/auth-check.php';
$base_path = '../../admin/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'members.php');
    exit;
}

$id = isset($_POST['id']) ? (int) ($_POST['id'] ?? 0) : 0;
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    header('Location: ' . $base_path . 'members.php?delete=error');
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('DELETE FROM workout_feedbacks WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM member_notes WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM checkins WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM member_package_history WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM member_packages WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM ai_workout_plans WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM ai_meal_plans WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM trainer_bookings WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM trainer_reviews WHERE member_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM members WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header('Location: ' . $base_path . 'members.php?delete=success');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    header('Location: ' . $base_path . 'members.php?delete=error');
    exit;
}
