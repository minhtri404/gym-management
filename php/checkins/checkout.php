<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../admin/';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        $status = 'checked_out';

        $stmt = $conn->prepare("
    UPDATE checkins
    SET checkout_time = NOW(),
        status = ?
    WHERE id = ? AND checkout_time IS NULL
");
       $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        if ($affected_rows > 0) {
            header("Location: " . $base_path . "checkins.php?checkout=1&feedback_checkin_id=" . $id);
            exit;
        }
    }
}

header("Location: " . $base_path . "checkins.php?error=1");
exit;


