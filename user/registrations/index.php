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

function registrationStatusBadge(?string $status): string
{
    $status = strtolower(trim((string) $status));

    return match ($status) {
        'new' => '<span class="badge bg-primary">&#272;&atilde; &#273;&#259;ng k&yacute;</span>',
        'contacted' => '<span class="badge bg-warning text-dark">&#272;&atilde; li&ecirc;n h&#7879;</span>',
        'closed' => '<span class="badge bg-success">&#272;&atilde; x&#7917; l&yacute;</span>',
        default => '<span class="badge bg-secondary">' . ($status !== '' ? h($status) : 'Kh&ocirc;ng r&otilde;') . '</span>',
    };
}

function paymentMethodText(?int $paymentId): string
{
    return ($paymentId ?? 0) > 0 ? 'Online / VNPAY' : 'Thanh to&aacute;n t&#7841;i ph&ograve;ng';
}

function paymentStatusBadge(?string $paymentStatus, ?int $paymentId): string
{
    if (($paymentId ?? 0) <= 0) {
        return '<span class="badge bg-secondary">Thanh to&aacute;n t&#7841;i ph&ograve;ng</span>';
    }

    $paymentStatus = strtolower(trim((string) $paymentStatus));

    return match ($paymentStatus) {
        'paid' => '<span class="badge bg-success">&#272;&atilde; thanh to&aacute;n online</span>',
        'pending' => '<span class="badge bg-warning text-dark">Ch&#7901; thanh to&aacute;n online</span>',
        'failed' => '<span class="badge bg-danger">Thanh to&aacute;n online th&#7845;t b&#7841;i</span>',
        default => '<span class="badge bg-secondary">' . ($paymentStatus !== '' ? h($paymentStatus) : 'Kh&ocirc;ng r&otilde;') . '</span>',
    };
}

function registrationPlainText($value): string
{
    return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
}

function registrationTextContains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    if (function_exists('mb_strtolower')) {
        return str_contains(mb_strtolower($haystack, 'UTF-8'), mb_strtolower($needle, 'UTF-8'));
    }

    return stripos($haystack, $needle) !== false;
}

$stmt_user = $conn->prepare('
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
');
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$user_name = trim((string) ($user['full_name'] ?? ''));
$user_email = trim((string) ($user['email'] ?? ''));
$user_phone = trim((string) ($user['phone'] ?? ''));

$registrations = [];

if ($user_email !== '' || $user_phone !== '') {
    $conditions = [];
    $params = [];
    $types = '';

    if ($user_phone !== '') {
        $conditions[] = 'pr.phone = ?';
        $params[] = $user_phone;
        $types .= 's';
    }

    if ($user_email !== '') {
        $conditions[] = 'pr.email = ?';
        $params[] = $user_email;
        $types .= 's';
    }

    $sql = "
        SELECT
            pr.id,
            pr.full_name,
            pr.phone,
            pr.email,
            pr.date_of_birth,
            pr.address,
            pr.note,
            pr.status,
            pr.payment_status,
            pr.payment_id,
            pr.created_at,
            p.package_name,
            p.price,
            p.duration_months
        FROM package_registrations pr
        LEFT JOIN packages p ON p.id = pr.package_id
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY pr.created_at DESC, pr.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $registrations[] = $row;
        }
        $stmt->close();
    }
}

$total_registrations = count($registrations);
$processing_count = 0;
$closed_count = 0;
$paid_count = 0;

foreach ($registrations as $registration) {
    $status = strtolower((string) ($registration['status'] ?? ''));
    if (in_array($status, ['new', 'contacted'], true)) {
        $processing_count++;
    }
    if ($status === 'closed') {
        $closed_count++;
    }
    if (($registration['payment_status'] ?? '') === 'paid') {
        $paid_count++;
    }
}

$filter_keyword = trim((string) ($_GET['keyword'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));
$filter_payment = trim((string) ($_GET['payment'] ?? ''));
$allowed_statuses = ['new', 'contacted', 'closed'];
$allowed_payments = ['cash', 'paid', 'pending', 'failed'];

if (!in_array($filter_status, $allowed_statuses, true)) {
    $filter_status = '';
}

if (!in_array($filter_payment, $allowed_payments, true)) {
    $filter_payment = '';
}

$filtered_registrations = array_values(array_filter($registrations, function (array $item) use ($filter_keyword, $filter_status, $filter_payment): bool {
    $status = strtolower((string) ($item['status'] ?? ''));
    $paymentStatus = strtolower((string) ($item['payment_status'] ?? ''));
    $paymentId = isset($item['payment_id']) ? (int) $item['payment_id'] : 0;

    if ($filter_status !== '' && $status !== $filter_status) {
        return false;
    }

    if ($filter_payment !== '') {
        if ($filter_payment === 'cash' && $paymentId > 0) {
            return false;
        }

        if ($filter_payment !== 'cash' && ($paymentId <= 0 || $paymentStatus !== $filter_payment)) {
            return false;
        }
    }

    if ($filter_keyword === '') {
        return true;
    }

    $searchText = implode(' ', [
        $item['id'] ?? '',
        $item['full_name'] ?? '',
        $item['phone'] ?? '',
        $item['email'] ?? '',
        $item['address'] ?? '',
        $item['note'] ?? '',
        $item['package_name'] ?? '',
        $item['price'] ?? '',
        $item['duration_months'] ?? '',
        $status,
        $paymentStatus,
        registrationPlainText(registrationStatusBadge($status)),
        registrationPlainText(paymentStatusBadge($paymentStatus, $paymentId)),
    ]);

    return registrationTextContains($searchText, $filter_keyword);
}));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L&#7883;ch s&#7917; &#273;&#259;ng k&yacute; g&oacute;i - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <style>
        .registration-hero {
            background:
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 34%),
                linear-gradient(135deg, #07111f 0%, #0d1320 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            color: #ffffff;
        }

        .registration-card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 22px;
            color: #e5e7eb;
            box-shadow: 0 22px 55px rgba(0, 0, 0, 0.22);
        }

        .registration-stat {
            background: rgba(2, 6, 23, 0.34);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 16px 18px;
            height: 100%;
        }

        .registration-stat-label,
        .registration-muted {
            color: #94a3b8;
        }

        .registration-stat-value {
            color: #ffffff;
            font-size: 28px;
            font-weight: 850;
            line-height: 1;
        }

        .account-info {
            background: rgba(2, 6, 23, 0.32);
            border: 1px solid rgba(56, 189, 248, 0.18);
            border-radius: 16px;
            padding: 16px;
        }

        .account-info-label {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .account-info-value {
            color: #ffffff;
            font-weight: 750;
            word-break: break-word;
        }

        .registration-filter-panel {
            background: rgba(2, 6, 23, 0.34);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .registration-filter-panel .form-label {
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 750;
        }

        .registration-filter-panel .form-control,
        .registration-filter-panel .form-select {
            background-color: rgba(15, 23, 42, 0.96);
            border-color: rgba(148, 163, 184, 0.28);
            color: #f8fafc;
        }

        .registration-filter-panel .form-control::placeholder {
            color: #64748b;
        }

        .registration-list {
            display: grid;
            gap: 14px;
        }

        .registration-item {
            background: rgba(2, 6, 23, 0.36);
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 16px;
            padding: 18px;
        }

        .registration-item-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 850;
        }

        .registration-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .registration-meta span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 13px;
        }

        .registration-detail {
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            margin-top: 14px;
            padding-top: 14px;
        }

        .registration-empty {
            text-align: center;
            padding: 54px 22px;
            color: #94a3b8;
        }

        .registration-empty i {
            font-size: 48px;
            color: #38bdf8;
            margin-bottom: 14px;
        }

        .registration-hero {
            background: #f6f9fc;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            color: #303640;
        }

        .registration-card,
        .registration-stat,
        .account-info,
        .registration-filter-panel,
        .registration-item,
        .registration-empty {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 8px;
            color: #303640;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
        }

        .registration-stat-value,
        .account-info-value,
        .registration-filter-panel .form-label,
        .registration-item-title {
            color: #303640;
        }

        .registration-stat-label,
        .registration-muted,
        .account-info-label,
        .registration-empty {
            color: #5b6675;
        }

        .registration-filter-panel .form-control,
        .registration-filter-panel .form-select {
            background-color: #ffffff;
            border-color: rgba(15, 23, 42, 0.14);
            color: #303640;
        }

        .registration-meta span {
            background: #f8fafc;
            border-color: rgba(15, 23, 42, 0.08);
            color: #303640;
        }

        .registration-detail {
            border-top-color: rgba(15, 23, 42, 0.08);
        }
    </style>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="registration-hero py-5">
    <div class="container" style="margin-top: 80px;">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <span class="section-badge">Package registrations</span>
                <h1 class="fw-bold mt-3 mb-2">L&#7883;ch s&#7917; &#273;&#259;ng k&yacute; g&oacute;i</h1>
                <p class="text-secondary mb-0">
                    T&#7921; &#273;&#7897;ng hi&#7875;n th&#7883; c&aacute;c g&oacute;i b&#7841;n &#273;&atilde; &#273;&#259;ng k&yacute; theo S&#272;T/email trong account.
                </p>
            </div>
            <a href="<?php echo $base_path; ?>user/package/index.php" class="btn btn-hero-primary" style="width:auto;">
                <i class="bi bi-plus-circle me-1"></i>
                &#272;&#259;ng k&yacute; g&oacute;i m&#7899;i
            </a>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="registration-card p-4">
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="registration-stat">
                        <div class="registration-stat-label">T&#7893;ng &#273;&#259;ng k&yacute;</div>
                        <div class="registration-stat-value"><?php echo $total_registrations; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="registration-stat">
                        <div class="registration-stat-label">&#272;ang x&#7917; l&yacute;</div>
                        <div class="registration-stat-value"><?php echo $processing_count; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="registration-stat">
                        <div class="registration-stat-label">&#272;&atilde; x&#7917; l&yacute;</div>
                        <div class="registration-stat-value"><?php echo $closed_count; ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="registration-stat">
                        <div class="registration-stat-label">&#272;&atilde; thanh to&aacute;n</div>
                        <div class="registration-stat-value"><?php echo $paid_count; ?></div>
                    </div>
                </div>
            </div>

            <div class="account-info mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="account-info-label">H&#7885; t&ecirc;n account</div>
                        <div class="account-info-value"><?php echo $user_name !== '' ? h($user_name) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-info-label">S&#7889; &#273;i&#7879;n tho&#7841;i</div>
                        <div class="account-info-value"><?php echo $user_phone !== '' ? h($user_phone) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="account-info-label">Email</div>
                        <div class="account-info-value"><?php echo $user_email !== '' ? h($user_email) : 'Ch&#432;a c&#7853;p nh&#7853;t'; ?></div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Danh s&aacute;ch &#273;&#259;ng k&yacute;</h3>
                    <p class="registration-muted mb-0">
                        H&#7879; th&#7889;ng t&#7921; l&#7885;c theo S&#272;T/email account, kh&ocirc;ng c&#7847;n nh&#7853;p tay.
                    </p>
                </div>
            </div>

            <?php if ($user_email === '' && $user_phone === ''): ?>
                <div class="registration-empty">
                    <i class="bi bi-person-exclamation"></i>
                    <h4 class="text-white mb-2">Account ch&#432;a c&oacute; S&#272;T/email</h4>
                    <p class="mb-0">H&atilde;y c&#7853;p nh&#7853;t account &#273;&#7875; h&#7879; th&#7889;ng t&#7921; t&igrave;m &#273;&#259;ng k&yacute; g&oacute;i c&#7911;a b&#7841;n.</p>
                </div>
            <?php elseif (!empty($registrations)): ?>
                <form method="GET" class="registration-filter-panel row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label">T&igrave;m ki&#7871;m</label>
                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="T&igrave;m theo g&oacute;i, t&ecirc;n, S&#272;T, email, ghi ch&uacute;..."
                            value="<?php echo h($filter_keyword); ?>"
                        >
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Tr&#7841;ng th&aacute;i x&#7917; l&yacute;</label>
                        <select name="status" class="form-select">
                            <option value="">T&#7845;t c&#7843;</option>
                            <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>&#272;&atilde; &#273;&#259;ng k&yacute;</option>
                            <option value="contacted" <?php echo $filter_status === 'contacted' ? 'selected' : ''; ?>>&#272;&atilde; li&ecirc;n h&#7879;</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>&#272;&atilde; x&#7917; l&yacute;</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label class="form-label">Thanh to&aacute;n</label>
                        <select name="payment" class="form-select">
                            <option value="">T&#7845;t c&#7843;</option>
                            <option value="cash" <?php echo $filter_payment === 'cash' ? 'selected' : ''; ?>>T&#7841;i ph&ograve;ng</option>
                            <option value="pending" <?php echo $filter_payment === 'pending' ? 'selected' : ''; ?>>Ch&#7901; online</option>
                            <option value="paid" <?php echo $filter_payment === 'paid' ? 'selected' : ''; ?>>&#272;&atilde; online</option>
                            <option value="failed" <?php echo $filter_payment === 'failed' ? 'selected' : ''; ?>>Online l&#7895;i</option>
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

                <div class="registration-muted small mb-3">
                    Hi&#7875;n th&#7883; <?php echo count($filtered_registrations); ?> / <?php echo $total_registrations; ?> &#273;&#259;ng k&yacute;
                </div>

                <?php if (!empty($filtered_registrations)): ?>
                <div class="registration-list">
                    <?php foreach ($filtered_registrations as $item): ?>
                        <article class="registration-item">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div>
                                    <div class="registration-item-title">
                                        <?php echo !empty($item['package_name']) ? h($item['package_name']) : 'G&oacute;i t&#7853;p kh&ocirc;ng x&aacute;c &#273;&#7883;nh'; ?>
                                    </div>
                                    <div class="registration-muted">
                                        M&atilde; &#273;&#259;ng k&yacute; #<?php echo str_pad((string) (int) $item['id'], 3, '0', STR_PAD_LEFT); ?>
                                        &middot;
                                        <?php echo !empty($item['created_at']) ? h(date('d/m/Y H:i', strtotime((string) $item['created_at']))) : '-'; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap align-items-start">
                                    <?php echo registrationStatusBadge($item['status'] ?? ''); ?>
                                    <?php echo paymentStatusBadge($item['payment_status'] ?? 'unpaid', isset($item['payment_id']) ? (int) $item['payment_id'] : 0); ?>
                                </div>
                            </div>

                            <div class="registration-meta">
                                <span><i class="bi bi-person"></i><?php echo h($item['full_name'] ?? '-'); ?></span>
                                <span><i class="bi bi-telephone"></i><?php echo h($item['phone'] ?? '-'); ?></span>
                                <span><i class="bi bi-envelope"></i><?php echo h($item['email'] ?: '-'); ?></span>
                                <span><i class="bi bi-credit-card"></i><?php echo paymentMethodText(isset($item['payment_id']) ? (int) $item['payment_id'] : 0); ?></span>
                                <?php if (!empty($item['duration_months'])): ?>
                                    <span><i class="bi bi-calendar3"></i><?php echo (int) $item['duration_months']; ?> th&aacute;ng</span>
                                <?php endif; ?>
                                <?php if (!empty($item['price'])): ?>
                                    <span><i class="bi bi-cash-stack"></i><?php echo number_format((float) $item['price'], 0, ',', '.'); ?> VN&#272;</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($item['date_of_birth']) || !empty($item['address']) || !empty($item['note'])): ?>
                                <div class="registration-detail row g-3">
                                    <?php if (!empty($item['date_of_birth'])): ?>
                                        <div class="col-md-4">
                                            <div class="registration-muted small">Ng&agrave;y sinh</div>
                                            <div><?php echo h(date('d/m/Y', strtotime((string) $item['date_of_birth']))); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['address'])): ?>
                                        <div class="col-md-4">
                                            <div class="registration-muted small">&#272;&#7883;a ch&#7881;</div>
                                            <div><?php echo h($item['address']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['note'])): ?>
                                        <div class="col-md-4">
                                            <div class="registration-muted small">Ghi ch&uacute;</div>
                                            <div><?php echo nl2br(h($item['note'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="registration-empty">
                        <i class="bi bi-search"></i>
                        <h4 class="text-white mb-2">Kh&ocirc;ng c&oacute; k&#7871;t qu&#7843; ph&ugrave; h&#7907;p</h4>
                        <p class="mb-4">Th&#7917; &#273;&#7893;i t&#7915; kh&oacute;a ho&#7863;c b&#7897; l&#7885;c &#273;&#7875; xem l&#7841;i danh s&aacute;ch.</p>
                        <a href="index.php" class="btn btn-hero-primary" style="width:auto;">
                            X&oacute;a b&#7897; l&#7885;c
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="registration-empty">
                    <i class="bi bi-clipboard-x"></i>
                    <h4 class="text-white mb-2">Ch&#432;a c&oacute; &#273;&#259;ng k&yacute; g&oacute;i</h4>
                    <p class="mb-4">Ch&#432;a t&igrave;m th&#7845;y &#273;&#259;ng k&yacute; n&agrave;o kh&#7899;p v&#7899;i S&#272;T/email trong account.</p>
                    <a href="<?php echo $base_path; ?>user/package/index.php" class="btn btn-hero-primary" style="width:auto;">
                        Xem g&oacute;i t&#7853;p
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
