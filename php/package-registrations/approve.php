<?php
/**
 * Approve a package registration (admin action)
 * - Reuse existing member (by phone OR email) if present
 * - If not present, try to create member from `users` (if matching account exists)
 * - Otherwise create a minimal `members` row
 * - Expire existing active member_packages for that member
 * - Insert a new member_packages active row
 * - Update members with package_id, start_date, end_date
 *
 * Requirements covered:
 * - Prepared statements for all DB queries
 * - Trim input, case-insensitive email compare
 * - Transactional (commit / rollback)
 */

include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'package-registrations.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    header('Location: ' . $base_path . 'package-registrations.php?approve=error');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    header('Location: ' . $base_path . 'package-registrations.php?approve=error');
    exit;
}

try {
    // load registration
    $stmt = $conn->prepare("SELECT id, full_name, phone, email, date_of_birth, address, package_id, note FROM package_registrations WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $registration = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$registration) {
        throw new Exception('Đăng ký không tồn tại');
    }

    // trim and normalize inputs
    $full_name = trim($registration['full_name'] ?? '');
    $phone_raw = trim($registration['phone'] ?? '');
    $email_raw = trim($registration['email'] ?? '');
    $date_of_birth = trim($registration['date_of_birth'] ?? '');
    $address = trim($registration['address'] ?? '');
    $note = trim($registration['note'] ?? 'Đăng ký từ admin');
    $package_id = isset($registration['package_id']) ? (int)$registration['package_id'] : 0;

    // validate package exists (only real error case)
    $stmt = $conn->prepare("
    SELECT 
        id,
        duration_months,
        duration_days,
        package_type,
        price
    FROM packages
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $package_id);
$stmt->execute();
$pkgRes = $stmt->get_result();
$package = $pkgRes ? $pkgRes->fetch_assoc() : null;
$stmt->close();

    if (!$package) {
        // only real error when package not found
        throw new Exception('Gói tập không tồn tại');
    }

    // compute start/end dates
   $start_date = date('Y-m-d');
$start = new DateTime($start_date);
$end = clone $start;

$is_free_trial = (($package['package_type'] ?? 'paid') === 'free_trial');

if ($is_free_trial) {
    $days = (int)($package['duration_days'] ?? 7);

    if ($days <= 0) {
        $days = 7;
    }

    $end->modify('+' . $days . ' days');
} else {
    $months = (int)($package['duration_months'] ?? 0);

    if ($months > 0) {
        $end->modify('+' . $months . ' months');
    }
}

$end_date = $end->format('Y-m-d');
    // Start transaction
    $conn->begin_transaction();

    // Try to find existing member by email (case-insensitive) first, then by normalized phone
    $member_id = null;
    $email_l = strtolower($email_raw);
    $phone_digits = preg_replace('/\D+/', '', $phone_raw);

    if ($email_l !== '') {
        $stmt = $conn->prepare("SELECT id, full_name, date_of_birth, address, phone, email FROM members WHERE LOWER(email) = ? LIMIT 1");
        $stmt->bind_param('s', $email_l);
        $stmt->execute();
        $mres = $stmt->get_result();
        $member = $mres ? $mres->fetch_assoc() : null;
        $stmt->close();
        if ($member) $member_id = (int)$member['id'];
    }

    if ($member_id === null && $phone_digits !== '') {
        // compare cleaned phone numbers
        $stmt = $conn->prepare("SELECT id, full_name, date_of_birth, address, phone, email FROM members WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') = ? LIMIT 1");
        $stmt->bind_param('s', $phone_digits);
        $stmt->execute();
        $mres = $stmt->get_result();
        $member = $mres ? $mres->fetch_assoc() : null;
        $stmt->close();
        if ($member) $member_id = (int)$member['id'];
    }

    // If still no member, try fallback: look into `users` (registered accounts) to create a member from that
    if ($member_id === null) {
        $user = null;
        if ($email_l !== '') {
            $stmt = $conn->prepare("SELECT id, full_name, phone, email FROM users WHERE LOWER(email) = ? LIMIT 1");
            $stmt->bind_param('s', $email_l);
            $stmt->execute();
            $ures = $stmt->get_result();
            $user = $ures ? $ures->fetch_assoc() : null;
            $stmt->close();
        }
        if (!$user && $phone_digits !== '') {
            $stmt = $conn->prepare("SELECT id, full_name, phone, email FROM users WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') = ? LIMIT 1");
            $stmt->bind_param('s', $phone_digits);
            $stmt->execute();
            $ures = $stmt->get_result();
            $user = $ures ? $ures->fetch_assoc() : null;
            $stmt->close();
        }

        if ($user) {
            // create a minimal member from user row
            $u_full = trim($user['full_name'] ?? $full_name);
            $u_phone = trim($user['phone'] ?? $phone_raw);
            $u_email = trim($user['email'] ?? $email_raw);

            $stmt = $conn->prepare("INSERT INTO members (full_name, phone, email, status) VALUES (?, ?, ?, 'active')");
            $stmt->bind_param('sss', $u_full, $u_phone, $u_email);
            $stmt->execute();
            $member_id = (int)$stmt->insert_id;
            $stmt->close();
        }
    }

    // If still no member, create a new minimal member
    if ($member_id === null) {
        $stmt = $conn->prepare("INSERT INTO members (full_name, phone, email, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param('sss', $full_name, $phone_raw, $email_raw);
        $stmt->execute();
        $member_id = (int)$stmt->insert_id;
        $stmt->close();
    }

    // Update members with package info (package_id, start/end dates). Only update fields that we intend to set.
    $stmt = $conn->prepare("UPDATE members SET package_id = ?, start_date = ?, end_date = ?, status = 'active' WHERE id = ?");
    $stmt->bind_param('issi', $package_id, $start_date, $end_date, $member_id);
    $stmt->execute();
    $stmt->close();

    // Expire existing active member_packages for this member
    $stmt = $conn->prepare("UPDATE member_packages SET status = 'expired' WHERE member_id = ? AND status = 'active'");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("
    UPDATE member_package_history
    SET status = 'expired'
    WHERE member_id = ?
      AND status = 'active'
");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$stmt->close();

    // Insert new member_packages (active)
    $stmt = $conn->prepare("INSERT INTO member_packages (member_id, package_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param('iiss', $member_id, $package_id, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();

    // Insert into member_package_history for audit
   $price = (float)($package['price'] ?? 0.0);

if ($is_free_trial) {
    $price = 0.0;
    $paid_amount = 0.0;
    $remaining_amount = 0.0;
    $history_note = $note ?: 'Đăng ký gói dùng thử 7 ngày';
} else {
    $paid_amount = 0.0;
    $remaining_amount = $price - $paid_amount;
    $history_note = $note ?: 'Đăng ký gói trả phí';
}

    $stmt = $conn->prepare(
        "INSERT INTO member_package_history ( member_id, package_id, action_type, start_date, end_date, price, paid_amount, remaining_amount, status, note ) VALUES (?, ?, 'new', ?, ?, ?, ?, ?, 'active', ?)"
    );
    $stmt->bind_param('iissddds', $member_id, $package_id, $start_date, $end_date, $price, $paid_amount, $remaining_amount, $history_note);
    $stmt->execute();
    $stmt->close();

    // Mark the registration as closed
 $stmt = $conn->prepare("
    UPDATE package_registrations
    SET status = 'closed',
        payment_status = ?
    WHERE id = ?
");

$registration_payment_status = $is_free_trial ? 'paid' : 'unpaid';

$stmt->bind_param('si', $registration_payment_status, $id);
$stmt->execute();
$stmt->close();

    $conn->commit();
    header('Location: ' . $base_path . 'package-registrations.php?approve=success');
    exit;

} catch (Exception $e) {
    // rollback and redirect with error
    $conn->rollback();
    // log the error server-side if needed
    error_log('Approve registration error: ' . $e->getMessage());
    header('Location: ' . $base_path . 'package-registrations.php?approve=error');
    exit;
}


