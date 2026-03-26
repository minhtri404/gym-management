<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE checkins
            SET checkout_time = NOW()
            WHERE id = ? AND checkout_time IS NULL
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $base_path . "checkins.php?checkout=1");
        exit;
    }
}

header("Location: " . $base_path . "checkins.php?error=1");
exit;