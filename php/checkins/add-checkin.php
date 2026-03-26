<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $note = trim($_POST['note'] ?? '');

    if ($member_id > 0) {
        $stmtCheck = $conn->prepare("
            SELECT id 
            FROM checkins 
            WHERE member_id = ? 
              AND DATE(checkin_time) = CURDATE()
            LIMIT 1
        ");
        $stmtCheck->bind_param("i", $member_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $alreadyCheckedIn = $resultCheck->fetch_assoc();
        $stmtCheck->close();

        if ($alreadyCheckedIn) {
            header("Location: " . $base_path . "checkins.php?duplicate=1");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO checkins (member_id, note) VALUES (?, ?)");
        $stmt->bind_param("is", $member_id, $note);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $base_path . "checkins.php?success=1");
        exit;
    }
}

header("Location: " . $base_path . "checkins.php?error=1");
exit;