<?php
include __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$base_path = '../../';
$user_id = (int) $_SESSION['user_id'];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function userContactStatusBadge(?string $status, ?string $source = ''): string
{
    $status = strtolower(trim((string) $status));
    $source = strtolower(trim((string) $source));

    if ($source === 'trainer_bookings') {
        switch ($status) {
            case 'pending':
                return '<span class="badge bg-warning text-dark">Ch&#7901; x&aacute;c nh&#7853;n</span>';
            case 'confirmed':
                return '<span class="badge bg-primary">&#272;&atilde; x&aacute;c nh&#7853;n</span>';
            case 'completed':
                return '<span class="badge bg-success">Ho&agrave;n th&agrave;nh</span>';
            case 'cancelled':
                return '<span class="badge bg-danger">&#272;&atilde; h&#7911;y</span>';
            default:
                return '<span class="badge bg-secondary">' . ($status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;') . '</span>';
        }
    }

    if ($source === 'package_registrations') {
        switch ($status) {
            case 'new':
                return '<span class="badge bg-primary">&#272;&atilde; &#273;&#259;ng k&yacute;</span>';
            case 'contacted':
                return '<span class="badge bg-warning text-dark">&#272;&atilde; li&ecirc;n h&#7879;</span>';
            case 'closed':
                return '<span class="badge bg-success">&#272;&atilde; x&#7917; l&yacute;</span>';
            default:
                return '<span class="badge bg-secondary">' . ($status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;') . '</span>';
        }
    }

    switch ($status) {
        case 'new':
            return '<span class="badge bg-primary">M&#7899;i g&#7917;i</span>';
        case 'contacted':
            return '<span class="badge bg-warning text-dark">&#272;&atilde; li&ecirc;n h&#7879;</span>';
        case 'closed':
            return '<span class="badge bg-success">&#272;&atilde; ho&agrave;n t&#7845;t</span>';
        default:
            return '<span class="badge bg-secondary">' . ($status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;') . '</span>';
    }
}

function userContactMethodText(?string $method): string
{
    $method = strtolower(trim((string) $method));

    switch ($method) {
        case 'phone':
            return '&#272;i&#7879;n tho&#7841;i';
        case 'zalo':
            return 'Zalo';
        case 'email':
            return 'Email';
        case 'facebook':
            return 'Facebook';
        case 'app':
            return 'Trong h&#7879; th&#7889;ng';
        default:
            return $method !== '' ? h($method) : '-';
    }
}

function userContactTableExists(mysqli $conn, string $table): bool
{
    $allowed = [
        'contacts',
        'contact_messages',
        'members',
        'package_registrations',
        'packages',
        'trainer_bookings',
        'trainers',
    ];

    if (!in_array($table, $allowed, true)) {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $result && $result->num_rows > 0;
}

function userContactSourceBadge(?string $source): string
{
    $source = strtolower(trim((string) $source));

    switch ($source) {
        case 'trainer_bookings':
            return '<span class="badge rounded-pill text-bg-info">Li&ecirc;n h&#7879; HLV</span>';
        case 'package_registrations':
            return '<span class="badge rounded-pill text-bg-success">&#272;&#259;ng k&yacute; g&oacute;i</span>';
        case 'contacts':
        case 'contact_messages':
            return '<span class="badge rounded-pill text-bg-primary">Li&ecirc;n h&#7879; t&#432; v&#7845;n</span>';
        default:
            return '<span class="badge rounded-pill text-bg-secondary">Kh&aacute;c</span>';
    }
}

function userPaymentStatusText(?string $payment_status): string
{
    $payment_status = strtolower(trim((string) $payment_status));

    switch ($payment_status) {
        case 'paid':
            return '&#272;&atilde; thanh to&aacute;n online';
        case 'pending':
            return 'Ch&#7901; thanh to&aacute;n online';
        case 'failed':
            return 'Thanh to&aacute;n online th&#7845;t b&#7841;i';
        case 'unpaid':
            return 'Thanh to&aacute;n t&#7841;i ph&ograve;ng';
        default:
            return $payment_status !== '' ? h($payment_status) : '-';
    }
}

function userContactSourceFilterValue(?string $source): string
{
    $source = strtolower(trim((string) $source));

    if ($source === 'trainer_bookings') {
        return 'trainer';
    }

    if ($source === 'package_registrations') {
        return 'package';
    }

    return 'consultation';
}

function userContactStatusText(?string $status, ?string $source = ''): string
{
    $status = strtolower(trim((string) $status));
    $source = strtolower(trim((string) $source));

    if ($source === 'trainer_bookings') {
        return match ($status) {
            'pending' => 'Ch&#7901; x&aacute;c nh&#7853;n',
            'confirmed' => '&#272;&atilde; x&aacute;c nh&#7853;n',
            'completed' => 'Ho&agrave;n th&agrave;nh',
            'cancelled' => '&#272;&atilde; h&#7911;y',
            default => $status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;',
        };
    }

    if ($source === 'package_registrations') {
        return match ($status) {
            'new' => '&#272;&atilde; &#273;&#259;ng k&yacute;',
            'contacted' => '&#272;&atilde; li&ecirc;n h&#7879;',
            'closed' => '&#272;&atilde; x&#7917; l&yacute;',
            default => $status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;',
        };
    }

    return match ($status) {
        'new' => 'M&#7899;i g&#7917;i',
        'contacted' => '&#272;&atilde; li&ecirc;n h&#7879;',
        'closed' => '&#272;&atilde; ho&agrave;n t&#7845;t',
        default => $status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;',
    };
}

function userContactTextContains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    if (function_exists('mb_strtolower')) {
        return str_contains(mb_strtolower($haystack, 'UTF-8'), mb_strtolower($needle, 'UTF-8'));
    }

    return stripos($haystack, $needle) !== false;
}

function userContactPlainText($value): string
{
    return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
}

function userFetchContactRows(mysqli $conn, string $table, string $email, string $phone): array
{
    if (!userContactTableExists($conn, $table)) {
        return [];
    }

    $conditions = [];
    $params = [];
    $types = '';

    if ($email !== '') {
        $conditions[] = 'email = ?';
        $params[] = $email;
        $types .= 's';
    }

    if ($phone !== '') {
        $conditions[] = 'phone = ?';
        $params[] = $phone;
        $types .= 's';
    }

    if (empty($conditions)) {
        return [];
    }

    $adminNoteSelect = $table === 'contact_messages' ? 'admin_note' : "'' AS admin_note";
    $sql = "
        SELECT
            id,
            full_name,
            phone,
            email,
            subject,
            message,
            preferred_contact_method,
            status,
            {$adminNoteSelect},
            created_at,
            '{$table}' AS source_table
        FROM {$table}
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY created_at DESC, id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $row['subject'] = h($row['subject'] ?? '');
        $row['message'] = h($row['message'] ?? '');
        $row['admin_note'] = h($row['admin_note'] ?? '');
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function userFindMemberByContact(mysqli $conn, string $email, string $phone): ?array
{
    if (!userContactTableExists($conn, 'members')) {
        return null;
    }

    if ($email === '' && $phone === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, full_name, phone, email
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $phone, $email);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $member ?: null;
}

function userFetchPackageRegistrationRows(mysqli $conn, string $email, string $phone): array
{
    if (!userContactTableExists($conn, 'package_registrations')) {
        return [];
    }

    $conditions = [];
    $params = [];
    $types = '';

    if ($email !== '') {
        $conditions[] = 'pr.email = ?';
        $params[] = $email;
        $types .= 's';
    }

    if ($phone !== '') {
        $conditions[] = 'pr.phone = ?';
        $params[] = $phone;
        $types .= 's';
    }

    if (empty($conditions)) {
        return [];
    }

    $joinPackages = userContactTableExists($conn, 'packages');
    $packageNameSelect = $joinPackages ? 'p.package_name' : "'' AS package_name";
    $packageJoin = $joinPackages ? 'LEFT JOIN packages p ON p.id = pr.package_id' : '';

    $sql = "
        SELECT
            pr.id,
            pr.full_name,
            pr.phone,
            pr.email,
            pr.note,
            pr.status,
            pr.payment_status,
            pr.created_at,
            {$packageNameSelect}
        FROM package_registrations pr
        {$packageJoin}
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY pr.created_at DESC, pr.id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $packageName = trim((string) ($row['package_name'] ?? ''));
        $note = trim((string) ($row['note'] ?? ''));
        $messageParts = [];

        if ($packageName !== '') {
            $messageParts[] = 'G&oacute;i t&#7853;p: ' . h($packageName);
        }

        if ($note !== '') {
            $messageParts[] = 'Ghi ch&uacute;: ' . h($note);
        }

        $rows[] = [
            'id' => $row['id'],
            'full_name' => $row['full_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'subject' => $packageName !== '' ? '&#272;&#259;ng k&yacute; g&oacute;i ' . h($packageName) : '&#272;&#259;ng k&yacute; g&oacute;i t&#7853;p',
            'message' => implode("\n", $messageParts),
            'preferred_contact_method' => 'phone',
            'status' => $row['status'],
            'admin_note' => 'Thanh to&aacute;n: ' . userPaymentStatusText($row['payment_status'] ?? ''),
            'created_at' => $row['created_at'],
            'source_table' => 'package_registrations',
        ];
    }

    $stmt->close();
    return $rows;
}

function userFetchTrainerBookingRows(mysqli $conn, ?array $member, string $email, string $phone): array
{
    if (
        !userContactTableExists($conn, 'trainer_bookings')
        || !userContactTableExists($conn, 'trainers')
        || !userContactTableExists($conn, 'members')
    ) {
        return [];
    }

    $conditions = [];
    $params = [];
    $types = '';

    if ($member && !empty($member['id'])) {
        $conditions[] = 'tb.member_id = ?';
        $params[] = (int) $member['id'];
        $types .= 'i';
    }

    if ($email !== '') {
        $conditions[] = 'm.email = ?';
        $params[] = $email;
        $types .= 's';
    }

    if ($phone !== '') {
        $conditions[] = 'm.phone = ?';
        $params[] = $phone;
        $types .= 's';
    }

    if (empty($conditions)) {
        return [];
    }

    $sql = "
        SELECT
            tb.id,
            tb.booking_date,
            tb.start_time,
            tb.end_time,
            tb.goal,
            tb.note,
            tb.status,
            tb.created_at,
            t.full_name AS trainer_name,
            t.specialty AS trainer_specialty,
            m.full_name AS member_name,
            m.phone AS member_phone,
            m.email AS member_email
        FROM trainer_bookings tb
        JOIN trainers t ON t.id = tb.trainer_id
        JOIN members m ON m.id = tb.member_id
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY tb.created_at DESC, tb.booking_date DESC, tb.start_time DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $bookingDate = !empty($row['booking_date']) ? date('d/m/Y', strtotime((string) $row['booking_date'])) : '-';
        $startTime = !empty($row['start_time']) ? date('H:i', strtotime((string) $row['start_time'])) : '';
        $endTime = !empty($row['end_time']) ? date('H:i', strtotime((string) $row['end_time'])) : '';
        $trainerName = trim((string) ($row['trainer_name'] ?? ''));
        $messageParts = [
            'HLV: ' . h($trainerName !== '' ? $trainerName : 'HLV FLEXZONE'),
            'L&#7883;ch t&#432; v&#7845;n: ' . h(trim($bookingDate . ' ' . $startTime . ($endTime !== '' ? ' - ' . $endTime : ''))),
        ];

        if (!empty($row['goal'])) {
            $messageParts[] = 'M&#7909;c ti&ecirc;u: ' . h($row['goal']);
        }

        if (!empty($row['note'])) {
            $messageParts[] = 'Ghi ch&uacute;: ' . h($row['note']);
        }

        $rows[] = [
            'id' => $row['id'],
            'full_name' => $row['member_name'] ?? ($member['full_name'] ?? ''),
            'phone' => $row['member_phone'] ?? ($member['phone'] ?? ''),
            'email' => $row['member_email'] ?? ($member['email'] ?? ''),
            'subject' => 'Li&ecirc;n h&#7879; HLV' . ($trainerName !== '' ? ': ' . h($trainerName) : ''),
            'message' => implode("\n", $messageParts),
            'preferred_contact_method' => 'app',
            'status' => $row['status'],
            'admin_note' => !empty($row['trainer_specialty']) ? 'Chuy&ecirc;n m&ocirc;n: ' . h($row['trainer_specialty']) : '-',
            'created_at' => $row['created_at'],
            'source_table' => 'trainer_bookings',
        ];
    }

    $stmt->close();
    return $rows;
}

$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$user_email = trim((string) ($user['email'] ?? ''));
$user_phone = trim((string) ($user['phone'] ?? ''));
$member = userFindMemberByContact($conn, $user_email, $user_phone);

$contacts = array_merge(
    userFetchContactRows($conn, 'contacts', $user_email, $user_phone),
    userFetchContactRows($conn, 'contact_messages', $user_email, $user_phone),
    userFetchPackageRegistrationRows($conn, $user_email, $user_phone),
    userFetchTrainerBookingRows($conn, $member, $user_email, $user_phone)
);

usort($contacts, function ($a, $b) {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$total_contacts = count($contacts);
$consultation_contacts = 0;
$trainer_contacts = 0;
$active_contacts = 0;

foreach ($contacts as $contact) {
    $source = (string) ($contact['source_table'] ?? '');
    $status = strtolower((string) ($contact['status'] ?? ''));

    if ($source === 'trainer_bookings') {
        $trainer_contacts++;
    } else {
        $consultation_contacts++;
    }

    if (in_array($status, ['new', 'contacted', 'pending', 'confirmed'], true)) {
        $active_contacts++;
    }
}

$filter_keyword = trim((string) ($_GET['keyword'] ?? ''));
$filter_type = trim((string) ($_GET['type'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));
$allowed_types = ['consultation', 'package', 'trainer'];
$allowed_statuses = ['new', 'contacted', 'closed', 'pending', 'confirmed', 'completed', 'cancelled'];

if (!in_array($filter_type, $allowed_types, true)) {
    $filter_type = '';
}

if (!in_array($filter_status, $allowed_statuses, true)) {
    $filter_status = '';
}

$filtered_contacts = array_values(array_filter($contacts, function (array $contact) use ($filter_keyword, $filter_type, $filter_status): bool {
    $source = (string) ($contact['source_table'] ?? '');
    $status = strtolower((string) ($contact['status'] ?? ''));

    if ($filter_type !== '' && userContactSourceFilterValue($source) !== $filter_type) {
        return false;
    }

    if ($filter_status !== '' && $status !== $filter_status) {
        return false;
    }

    if ($filter_keyword === '') {
        return true;
    }

    $searchText = implode(' ', [
        userContactPlainText($contact['subject'] ?? ''),
        userContactPlainText($contact['message'] ?? ''),
        userContactPlainText($contact['admin_note'] ?? ''),
        $contact['full_name'] ?? '',
        $contact['phone'] ?? '',
        $contact['email'] ?? '',
        $status,
        userContactPlainText(userContactStatusText($status, $source)),
    ]);

    return userContactTextContains($searchText, $filter_keyword);
}));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L&#7883;ch s&#7917; li&ecirc;n h&#7879; - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <style>
        .contact-history-hero {
            background:
                radial-gradient(circle at top right, rgba(33, 183, 255, 0.16), transparent 34%),
                linear-gradient(135deg, #07111f 0%, #0d1320 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            color: #ffffff;
        }

        .contact-history-card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 22px;
            color: #e5e7eb;
            box-shadow: 0 22px 55px rgba(0, 0, 0, 0.22);
        }

        .contact-filter-panel {
            background: rgba(2, 6, 23, 0.38);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 16px;
        }

        .contact-filter-panel .form-label {
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 700;
        }

        .contact-filter-panel .form-control,
        .contact-filter-panel .form-select {
            background-color: rgba(15, 23, 42, 0.96);
            border-color: rgba(148, 163, 184, 0.28);
            color: #f8fafc;
        }

        .contact-filter-panel .form-control::placeholder {
            color: #64748b;
        }

        .contact-table-wrap {
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            overflow: hidden;
        }

        .contact-history-table {
            --bs-table-bg: transparent;
            --bs-table-color: #e5e7eb;
            --bs-table-border-color: rgba(148, 163, 184, 0.12);
            margin-bottom: 0;
        }

        .contact-history-table thead th {
            color: #f8fafc;
            background: rgba(30, 41, 59, 0.96);
            border-color: rgba(148, 163, 184, 0.14);
            white-space: nowrap;
            font-size: 13px;
            letter-spacing: 0;
            padding: 14px 16px;
        }

        .contact-history-table td {
            background: rgba(15, 23, 42, 0.86);
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, 0.1);
            vertical-align: middle;
            padding: 16px;
        }

        .contact-history-table tbody tr:hover td {
            background: rgba(30, 41, 59, 0.9);
        }

        .contact-title {
            color: #ffffff;
            font-weight: 800;
        }

        .contact-subtext {
            color: #94a3b8;
            font-size: 13px;
        }

        .contact-message-box {
            max-width: 520px;
            white-space: normal;
            line-height: 1.6;
        }

        .contact-preview {
            color: #cbd5e1;
            line-height: 1.55;
            max-width: 440px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .contact-detail-modal .modal-content {
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: #e5e7eb;
        }

        .contact-detail-modal .modal-header,
        .contact-detail-modal .modal-footer {
            border-color: rgba(148, 163, 184, 0.14);
        }

        .contact-detail-box {
            background: rgba(2, 6, 23, 0.36);
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 12px;
            padding: 14px;
        }

        .contact-detail-label {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .contact-empty {
            text-align: center;
            padding: 54px 22px;
            color: #94a3b8;
        }

        .contact-empty i {
            font-size: 48px;
            color: #38bdf8;
            margin-bottom: 14px;
        }

        .contact-stat {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 16px 18px;
            height: 100%;
        }

        .contact-stat-label {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .contact-stat-value {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .contact-history-hero {
            background: #f6f9fc;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            color: #303640;
        }

        .contact-history-card,
        .contact-filter-panel,
        .contact-table-wrap,
        .contact-detail-modal .modal-content,
        .contact-detail-box,
        .contact-empty,
        .contact-stat {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 8px;
            color: #303640;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
        }

        .contact-filter-panel .form-label,
        .contact-title,
        .contact-history-table thead th,
        .contact-stat-value {
            color: #303640;
        }

        .contact-subtext,
        .contact-preview,
        .contact-detail-label,
        .contact-empty,
        .contact-stat-label {
            color: #5b6675;
        }

        .contact-filter-panel .form-control,
        .contact-filter-panel .form-select {
            background-color: #ffffff;
            border-color: rgba(15, 23, 42, 0.14);
            color: #303640;
        }

        .contact-history-table {
            --bs-table-bg: #ffffff;
            --bs-table-color: #303640;
            --bs-table-border-color: rgba(15, 23, 42, 0.08);
        }

        .contact-history-table thead th,
        .contact-history-table td,
        .contact-history-table tbody tr:hover td {
            background: #ffffff;
            color: #303640;
            border-color: rgba(15, 23, 42, 0.08);
        }

        .contact-history-table thead th {
            background: #f8fafc;
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="contact-history-hero py-5">
    <div class="container" style="margin-top: 80px;">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="section-badge">Contact history</span>
                <h1 class="fw-bold mt-3 mb-2">L&#7883;ch s&#7917; li&ecirc;n h&#7879;</h1>
                <p class="text-secondary mb-0">
                    Theo d&otilde;i c&aacute;c y&ecirc;u c&#7847;u li&ecirc;n h&#7879; b&#7841;n &#273;&atilde; g&#7917;i cho FLEXZONE.
                </p>
            </div>
            <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-hero-primary" style="width:auto;">
                <i class="bi bi-plus-circle me-1"></i>
                G&#7917;i li&ecirc;n h&#7879; m&#7899;i
            </a>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="contact-history-card p-4">
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="contact-stat">
                        <div class="contact-stat-label">T&#7845;t c&#7843; li&ecirc;n h&#7879;</div>
                        <div class="contact-stat-value"><?php echo $total_contacts; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="contact-stat">
                        <div class="contact-stat-label">Li&ecirc;n h&#7879; t&#432; v&#7845;n</div>
                        <div class="contact-stat-value"><?php echo $consultation_contacts; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="contact-stat">
                        <div class="contact-stat-label">Li&ecirc;n h&#7879; HLV</div>
                        <div class="contact-stat-value"><?php echo $trainer_contacts; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="contact-stat">
                        <div class="contact-stat-label">&#272;ang x&#7917; l&yacute;</div>
                        <div class="contact-stat-value"><?php echo $active_contacts; ?></div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Danh s&aacute;ch li&ecirc;n h&#7879;</h3>
                    <p class="text-secondary mb-0">
                        T&#7921; &#273;&#7897;ng l&#7885;c theo email/s&#7889; &#273;i&#7879;n tho&#7841;i t&agrave;i kho&#7843;n c&#7911;a b&#7841;n.
                    </p>
                </div>
                <div class="text-secondary small">
                    <div>Email: <?php echo h($user_email !== '' ? $user_email : '-'); ?></div>
                    <div>S&#272;T: <?php echo h($user_phone !== '' ? $user_phone : '-'); ?></div>
                </div>
            </div>

            <?php if (!empty($contacts)): ?>
                <form method="GET" class="contact-filter-panel row g-3 align-items-end mb-4">
                    <div class="col-lg-5">
                        <label class="form-label">T&igrave;m ki&#7871;m</label>
                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="Nh&#7853;p HLV, g&oacute;i t&#7853;p, n&#7897;i dung, tr&#7841;ng th&aacute;i..."
                            value="<?php echo h($filter_keyword); ?>"
                        >
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Lo&#7841;i li&ecirc;n h&#7879;</label>
                        <select name="type" class="form-select">
                            <option value="">T&#7845;t c&#7843;</option>
                            <option value="consultation" <?php echo $filter_type === 'consultation' ? 'selected' : ''; ?>>Li&ecirc;n h&#7879; t&#432; v&#7845;n</option>
                            <option value="package" <?php echo $filter_type === 'package' ? 'selected' : ''; ?>>&#272;&#259;ng k&yacute; g&oacute;i</option>
                            <option value="trainer" <?php echo $filter_type === 'trainer' ? 'selected' : ''; ?>>Li&ecirc;n h&#7879; HLV</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label">Tr&#7841;ng th&aacute;i</label>
                        <select name="status" class="form-select">
                            <option value="">T&#7845;t c&#7843;</option>
                            <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>M&#7899;i / &#272;&atilde; &#273;&#259;ng k&yacute;</option>
                            <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>&#272;&atilde; li&ecirc;n h&#7879;</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Ch&#7901; x&aacute;c nh&#7853;n</option>
                            <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>&#272;&atilde; x&aacute;c nh&#7853;n</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>&#272;&atilde; x&#7917; l&yacute;</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Ho&agrave;n th&agrave;nh</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>&#272;&atilde; h&#7911;y</option>
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-hero-primary flex-grow-1" style="width:auto;">
                            <i class="bi bi-search"></i>
                        </button>
                        <a href="index.php" class="btn btn-outline-light" title="X&oacute;a l&#7885;c">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-secondary small">
                        Hi&#7875;n th&#7883; <?php echo count($filtered_contacts); ?> / <?php echo $total_contacts; ?> li&ecirc;n h&#7879;
                    </div>
                </div>

                <?php if (!empty($filtered_contacts)): ?>
                <div class="table-responsive contact-table-wrap">
                    <table class="table align-middle contact-history-table">
                        <thead>
                            <tr>
                                <th>Ng&agrave;y g&#7917;i</th>
                                <th>Lo&#7841;i</th>
                                <th>N&#7897;i dung li&ecirc;n h&#7879;</th>
                                <th>Tr&#7841;ng th&aacute;i</th>
                                <th class="text-end">Chi ti&#7871;t</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_contacts as $index => $contact): ?>
                                <?php
                                    $modal_id = 'contactDetail' . preg_replace('/[^a-zA-Z0-9]/', '', (string) ($contact['source_table'] ?? 'item')) . (int) ($contact['id'] ?? 0) . $index;
                                    $created_at_text = !empty($contact['created_at']) ? h(date('d/m/Y H:i', strtotime((string) $contact['created_at']))) : '-';
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="contact-title"><?php echo $created_at_text; ?></div>
                                        <div class="contact-subtext"><?php echo userContactMethodText($contact['preferred_contact_method'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo userContactSourceBadge($contact['source_table'] ?? ''); ?></td>
                                    <td>
                                        <div class="contact-title"><?php echo $contact['subject'] !== '' ? $contact['subject'] : 'Li&ecirc;n h&#7879; t&#432; v&#7845;n'; ?></div>
                                        <div class="contact-subtext mb-2">
                                            <?php echo h($contact['full_name'] ?? ''); ?>
                                            <?php if (!empty($contact['phone'])): ?>
                                                &middot; <?php echo h($contact['phone']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-preview"><?php echo nl2br((string) ($contact['message'] ?? '')); ?></div>
                                    </td>
                                    <td><?php echo userContactStatusBadge($contact['status'] ?? '', $contact['source_table'] ?? ''); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#<?php echo h($modal_id); ?>">
                                            <i class="bi bi-eye me-1"></i> Xem
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($filtered_contacts as $index => $contact): ?>
                    <?php
                        $modal_id = 'contactDetail' . preg_replace('/[^a-zA-Z0-9]/', '', (string) ($contact['source_table'] ?? 'item')) . (int) ($contact['id'] ?? 0) . $index;
                        $created_at_text = !empty($contact['created_at']) ? h(date('d/m/Y H:i', strtotime((string) $contact['created_at']))) : '-';
                    ?>
                    <div class="modal fade contact-detail-modal" id="<?php echo h($modal_id); ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <div class="mb-2"><?php echo userContactSourceBadge($contact['source_table'] ?? ''); ?></div>
                                        <h5 class="modal-title"><?php echo $contact['subject'] !== '' ? $contact['subject'] : 'Chi ti&#7871;t li&ecirc;n h&#7879;'; ?></h5>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="contact-detail-box">
                                                <div class="contact-detail-label">Ng&#224;y g&#7917;i</div>
                                                <div><?php echo $created_at_text; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="contact-detail-box">
                                                <div class="contact-detail-label">Tr&#7841;ng th&aacute;i</div>
                                                <div><?php echo userContactStatusBadge($contact['status'] ?? '', $contact['source_table'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="contact-detail-box">
                                                <div class="contact-detail-label">Ng&#432;&#7901;i g&#7917;i</div>
                                                <div><?php echo h($contact['full_name'] ?? '-'); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="contact-detail-box">
                                                <div class="contact-detail-label">Li&ecirc;n h&#7879;</div>
                                                <div><?php echo h($contact['phone'] ?? '-'); ?> / <?php echo h($contact['email'] ?? '-'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="contact-detail-box mb-3">
                                        <div class="contact-detail-label">N&#7897;i dung</div>
                                        <div class="contact-message-box"><?php echo nl2br((string) ($contact['message'] ?? '-')); ?></div>
                                    </div>
                                    <div class="contact-detail-box">
                                        <div class="contact-detail-label">Ghi ch&uacute; admin / th&ocirc;ng tin b&#7893; sung</div>
                                        <div><?php echo (string) ($contact['admin_note'] ?? '-') !== '' ? (string) ($contact['admin_note'] ?? '-') : '-'; ?></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">&#272;&oacute;ng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="contact-empty">
                        <i class="bi bi-search"></i>
                        <h4 class="text-white mb-2">Kh&ocirc;ng c&oacute; k&#7871;t qu&#7843; ph&ugrave; h&#7907;p</h4>
                        <p class="mb-4">Th&#7917; &#273;&#7893;i t&#7915; kh&oacute;a ho&#7863;c b&#7897; l&#7885;c &#273;&#7875; xem l&#7841;i danh s&aacute;ch.</p>
                        <a href="index.php" class="btn btn-hero-primary" style="width:auto;">
                            X&oacute;a b&#7897; l&#7885;c
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="contact-empty">
                    <i class="bi bi-chat-left-text"></i>
                    <h4 class="text-white mb-2">Ch&#432;a c&oacute; l&#7883;ch s&#7917; li&ecirc;n h&#7879;</h4>
                    <p class="mb-4">
                        Khi b&#7841;n g&#7917;i form li&ecirc;n h&#7879;, n&#7897;i dung s&#7869; hi&#7875;n th&#7883; t&#7841;i &#273;&acirc;y.
                    </p>
                    <a href="<?php echo $base_path; ?>contact-form.php" class="btn btn-hero-primary" style="width:auto;">
                        G&#7917;i li&ecirc;n h&#7879;
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
