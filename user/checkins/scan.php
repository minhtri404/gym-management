<?php

require_once __DIR__ . '/../../includes/config.php';

function qr_env_value(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    $envPath = __DIR__ . '/../../.env';
    if (!is_file($envPath)) {
        return $default;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $default;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2 && trim($parts[0]) === $key) {
            return trim(trim($parts[1]), "\"'");
        }
    }

    return $default;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function qr_signature_is_valid(string $secret, int $expires, string $signature): bool
{
    $now = time();

    if ($secret === '' || $expires <= $now || $expires > $now + 600) {
        return false;
    }

    if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) {
        return false;
    }

    $expected = hash_hmac('sha256', (string) $expires, $secret);
    return hash_equals($expected, strtolower($signature));
}

$flash = $_SESSION['qr_checkin_flash'] ?? null;
unset($_SESSION['qr_checkin_flash']);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$expires = (int) ($_POST['expires'] ?? ($_GET['expires'] ?? 0));
$signature = trim((string) ($_POST['signature'] ?? ($_GET['signature'] ?? '')));
$qrSecret = qr_env_value('CHECKIN_QR_TOKEN', '');
$signatureValid = qr_signature_is_valid($qrSecret, $expires, $signature);

if (empty($_SESSION['user_id'])) {
    if ($requestMethod === 'GET' && $signatureValid) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/user/checkins/scan';
    }

    header('Location: ../../login.php');
    exit;
}

$message = '';
$messageType = 'info';
$member = null;
$activePackage = null;
$todayCheckin = null;
$action = '';
$actionLabel = '';

if (is_array($flash)) {
    $message = (string) ($flash['message'] ?? '');
    $messageType = (string) ($flash['type'] ?? 'success');
}

if ($qrSecret === '') {
    http_response_code(503);
    $message = 'Hệ thống QR chưa được cấu hình. Vui lòng liên hệ quản trị viên.';
    $messageType = 'danger';
} elseif (!$signatureValid && !is_array($flash)) {
    $message = 'Mã QR không hợp lệ hoặc đã hết hạn. Vui lòng quét mã mới tại quầy.';
    $messageType = 'danger';
}

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $message = 'Không tìm thấy tài khoản người dùng.';
    $messageType = 'danger';
} else {
    $phone = trim((string) ($user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    $stmt = $conn->prepare('
        SELECT id, full_name, phone, email
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> "")
           OR (email = ? AND email IS NOT NULL AND email <> "")
        LIMIT 1
    ');
    $stmt->bind_param('ss', $phone, $email);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        $message = 'Tài khoản chưa được liên kết với hồ sơ hội viên.';
        $messageType = 'warning';
    } else {
        $memberId = (int) $member['id'];

        $stmt = $conn->prepare('
            SELECT mp.id, mp.package_id, mp.start_date, mp.end_date, mp.status, p.package_name
            FROM member_packages mp
            JOIN packages p ON p.id = mp.package_id
            WHERE mp.member_id = ?
              AND mp.status = "active"
              AND mp.start_date <= CURDATE()
              AND mp.end_date >= CURDATE()
            ORDER BY mp.end_date DESC
            LIMIT 1
        ');
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $activePackage = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$activePackage) {
            $message = 'Bạn chưa có gói tập đang hoạt động hoặc gói đã hết hạn.';
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare('
                SELECT id, checkin_date, checkin_time, checkout_time, status
                FROM checkins
                WHERE member_id = ?
                  AND checkin_date = CURDATE()
                ORDER BY id DESC
                LIMIT 1
            ');
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $todayCheckin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$todayCheckin) {
                $action = 'checkin';
                $actionLabel = 'Xác nhận check-in';
            } elseif (empty($todayCheckin['checkout_time'])) {
                $action = 'checkout';
                $actionLabel = 'Xác nhận check-out';
            } elseif (!is_array($flash)) {
                $message = 'Bạn đã hoàn tất check-in và check-out hôm nay.';
                $messageType = 'info';
            }
        }
    }
}

if ($requestMethod === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrfToken === ''
        || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)
        || !$signatureValid
    ) {
        http_response_code(419);
        $message = 'Phiên xác nhận không hợp lệ hoặc mã QR đã hết hạn.';
        $messageType = 'danger';
        $action = '';
    } elseif (!$member || !$activePackage || $action === '') {
        $message = $message !== '' ? $message : 'Không thể thực hiện check-in/check-out.';
        $messageType = $messageType !== '' ? $messageType : 'warning';
    } elseif ($action === 'checkin') {
        $status = 'checked_in';
        $method = 'qr';
        $note = 'Check-in qua QR có thời hạn';

        $stmt = $conn->prepare('
            INSERT INTO checkins (member_id, checkin_date, checkin_time, status, checkin_method, note)
            VALUES (?, CURDATE(), NOW(), ?, ?, ?)
        ');
        $memberId = (int) $member['id'];
        $stmt->bind_param('isss', $memberId, $status, $method, $note);
        $stmt->execute();
        $stmt->close();

        $_SESSION['qr_checkin_flash'] = [
            'message' => 'Check-in thành công. Chúc bạn có buổi tập hiệu quả!',
            'type' => 'success',
        ];
        header('Location: scan.php?result=1');
        exit;
    } elseif ($action === 'checkout') {
        $status = 'checked_out';
        $checkinId = (int) $todayCheckin['id'];

        $stmt = $conn->prepare('
            UPDATE checkins
            SET checkout_time = NOW(), status = ?
            WHERE id = ? AND checkout_time IS NULL
        ');
        $stmt->bind_param('si', $status, $checkinId);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        $_SESSION['qr_checkin_flash'] = [
            'message' => $updated
                ? 'Check-out thành công. Cảm ơn bạn đã tập luyện hôm nay!'
                : 'Lượt check-in này đã được check-out trước đó.',
            'type' => $updated ? 'success' : 'info',
        ];
        header('Location: scan.php?result=1');
        exit;
    }
}

$canConfirm = $requestMethod === 'GET'
    && $signatureValid
    && $member
    && $activePackage
    && $action !== ''
    && !is_array($flash);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận check-in - FLEXZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#07111f; color:#fff;">
<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <div class="card border-0 shadow-lg" style="max-width:520px; width:100%; border-radius:22px;">
        <div class="card-body p-4 p-md-5 text-center">
            <h2 class="fw-bold text-dark mb-3">Check-in FLEXZONE</h2>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo h($messageType); ?>">
                    <?php echo h($message); ?>
                </div>
            <?php elseif ($canConfirm): ?>
                <div class="alert alert-info">
                    Kiểm tra thông tin và xác nhận thao tác bên dưới.
                </div>
            <?php endif; ?>

            <?php if ($member): ?>
                <div class="text-start text-dark bg-light rounded-4 p-3 mb-3">
                    <p class="mb-1"><strong>Hội viên:</strong> <?php echo h($member['full_name']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo h($member['email']); ?></p>
                    <p class="mb-0"><strong>SĐT:</strong> <?php echo h($member['phone']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($activePackage): ?>
                <div class="text-start text-dark bg-light rounded-4 p-3 mb-3">
                    <p class="mb-1"><strong>Gói tập:</strong> <?php echo h($activePackage['package_name']); ?></p>
                    <p class="mb-1"><strong>Bắt đầu:</strong> <?php echo h($activePackage['start_date']); ?></p>
                    <p class="mb-0"><strong>Hết hạn:</strong> <?php echo h($activePackage['end_date']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($canConfirm): ?>
                <form method="POST" action="scan.php" class="d-grid mb-2">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="expires" value="<?php echo $expires; ?>">
                    <input type="hidden" name="signature" value="<?php echo h($signature); ?>">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?php echo h($actionLabel); ?>
                    </button>
                </form>
                <div class="small text-muted mb-3">
                    Mã xác nhận hết hạn lúc <?php echo h(date('H:i:s', $expires)); ?>.
                </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <a href="../dashboard/index.php" class="btn btn-outline-primary">Về khu vực hội viên</a>
                <a href="index.php" class="btn btn-outline-secondary">Xem lịch sử check-in</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
