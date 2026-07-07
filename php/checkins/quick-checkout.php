<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../admin/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'members.php?checkout_error=1');
    exit;
}

$csrf_token = (string) ($_POST['csrf_token'] ?? '');
if ($csrf_token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf_token)) {
    header('Location: ' . $base_path . 'members.php?checkout_error=1');
    exit;
}

if (isset($_POST['member_id'])) {
    $member_id = (int)$_POST['member_id'];

    if ($member_id > 0) {
        // find latest active checkin (no checkout_time yet)
        $stmt = $conn->prepare("SELECT id FROM checkins WHERE member_id = ? AND checkout_time IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && isset($row['id'])) {
            $checkin_id = (int)$row['id'];

            $status = 'checked_out';
            $stmt2 = $conn->prepare("UPDATE checkins SET checkout_time = NOW(), status = ? WHERE id = ? AND checkout_time IS NULL");
            $stmt2->bind_param("si", $status, $checkin_id);
            $stmt2->execute();
            $affected = $stmt2->affected_rows;
            $stmt2->close();

            if ($affected > 0) {
                header("Location: " . $base_path . "members.php?checkout=1&checkin_id=" . $checkin_id);
                exit;
            }
        }
    }
}

header("Location: " . $base_path . "members.php?checkout_error=1");
exit;


