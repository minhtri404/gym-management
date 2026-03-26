<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (isset($_GET['member_id'])) {
    $member_id = (int)$_GET['member_id'];

    if ($member_id > 0) {
        $stmtMember = $conn->prepare("SELECT id FROM members WHERE id = ? AND status = 'active' LIMIT 1");
        $stmtMember->bind_param("i", $member_id);
        $stmtMember->execute();
        $resultMember = $stmtMember->get_result();
        $member = $resultMember->fetch_assoc();
        $stmtMember->close();

        if (!$member) {
            header("Location: " . $base_path . "members.php?checkin_error=1");
            exit;
        }

        $stmtCheck = $conn->prepare("
            SELECT id
            FROM checkins
            WHERE member_id = ? AND DATE(checkin_time) = CURDATE()
            LIMIT 1
        ");
        $stmtCheck->bind_param("i", $member_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $alreadyChecked = $resultCheck->fetch_assoc();
        $stmtCheck->close();

        if ($alreadyChecked) {
            header("Location: " . $base_path . "members.php?checkin_duplicate=1");
            exit;
        }

        $stmtInsert = $conn->prepare("INSERT INTO checkins (member_id) VALUES (?)");
        $stmtInsert->bind_param("i", $member_id);
        $stmtInsert->execute();
        $stmtInsert->close();

        header("Location: " . $base_path . "members.php?checkin_success=1");
        exit;
    }
}

header("Location: " . $base_path . "members.php?checkin_error=1");
exit;