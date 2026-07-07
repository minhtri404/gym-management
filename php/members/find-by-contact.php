<?php
include __DIR__ . '/../../includes/auth-check.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

// normalize
$phone_digits = preg_replace('/\D+/', '', $phone);
$email_l = strtolower($email);

if ($phone_digits === '' && $email_l === '') {
    echo json_encode(['success' => false, 'message' => 'Missing phone or email']);
    exit;
}

$member = null;

// Try email first (case-insensitive)
if ($email_l !== '') {
    $stmt = $conn->prepare("SELECT id, full_name, phone, email, date_of_birth, address FROM members WHERE LOWER(email) = ? LIMIT 1");
    $stmt->bind_param('s', $email_l);
    $stmt->execute();
    $res = $stmt->get_result();
    $member = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

// Then try exact phone match
if (!$member && $phone_digits !== '') {
    $stmt = $conn->prepare("SELECT id, full_name, phone, email, date_of_birth, address FROM members WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ? LIMIT 1");
    $stmt->bind_param('s', $phone_digits);
    $stmt->execute();
    $res = $stmt->get_result();
    $member = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if ($member) {
    echo json_encode(['success' => true, 'member' => $member]);
} else {
    // Not found in members - try users table as fallback
    $user = null;
    if ($email_l !== '') {
        $stmt = $conn->prepare("SELECT id, full_name, phone, email FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->bind_param('s', $email_l);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if (!$user && $phone_digits !== '') {
        $stmt = $conn->prepare("SELECT id, full_name, phone, email FROM users WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ? LIMIT 1");
        $stmt->bind_param('s', $phone_digits);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if ($user) {
        // map to same shape as member response (missing fields allowed)
        $mapped = [
            'id' => $user['id'],
            'full_name' => $user['full_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'email' => $user['email'] ?? null,
            'date_of_birth' => null,
            'address' => null,
            'source' => 'users'
        ];
        echo json_encode(['success' => true, 'member' => $mapped]);
    } else {
        echo json_encode(['success' => false, 'member' => null, 'message' => 'Not found']);
    }
}

exit;
