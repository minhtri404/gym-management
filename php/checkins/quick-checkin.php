<?php
include __DIR__ . '/../../includes/auth-check.php';
include __DIR__ . '/../../includes/config.php';

$base_path = '../../admin/';

if (isset($_GET['member_id'])) {
    $member_id = (int)$_GET['member_id'];
    $premium_checkin_min_price = 1000000;

    if ($member_id > 0) {
        $stmtMember = $conn->prepare("
            SELECT 
                m.id,
                m.status,
                m.end_date,
                m.package_id,
                p.price
            FROM members m
            LEFT JOIN packages p ON m.package_id = p.id
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmtMember->bind_param("i", $member_id);
        $stmtMember->execute();
        $resultMember = $stmtMember->get_result();
        $member = $resultMember->fetch_assoc();
        $stmtMember->close();

        if (!$member || ($member['status'] ?? '') !== 'active') {
            header("Location: " . $base_path . "members.php?checkin_error=1");
            exit;
        }

        if (empty($member['package_id']) || (float)($member['price'] ?? 0) < $premium_checkin_min_price) {
            header("Location: " . $base_path . "members.php?checkin_not_premium=1");
            exit;
        }

        if (!empty($member['end_date']) && $member['end_date'] < date('Y-m-d')) {
            header("Location: " . $base_path . "members.php?checkin_expired=1");
            exit;
        }

        $stmtCheck = $conn->prepare("
            SELECT id
            FROM checkins
            WHERE member_id = ?
              AND checkin_date = CURDATE()
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

        $status = 'checked_in';
        $checkin_method = 'manual';

        $stmtInsert = $conn->prepare("
            INSERT INTO checkins (member_id, checkin_date, checkin_time, status, checkin_method)
            VALUES (?, CURDATE(), NOW(), ?, ?)
        ");
        $stmtInsert->bind_param("iss", $member_id, $status, $checkin_method);
        $stmtInsert->execute();
        $stmtInsert->close();

        header("Location: " . $base_path . "members.php?checkin_success=1");
        exit;
    }
}

header("Location: " . $base_path . "members.php?checkin_error=1");
exit;

