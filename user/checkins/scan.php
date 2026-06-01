<?php
include __DIR__ . '/../../includes/config.php';

function qr_env_value($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../../.env';

    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            if (trim($parts[0]) === $key) {
                return trim(trim($parts[1]), "\"'");
            }
        }
    }

    return $default;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$token_from_qr = $_GET['token'] ?? '';
$valid_token = qr_env_value('CHECKIN_QR_TOKEN', 'gym_checkin_2026');

if ($token_from_qr === '' || !hash_equals($valid_token, $token_from_qr)) {
    die('Mã QR không hợp lệ.');
}

/*
|--------------------------------------------------------------------------
| Nếu chưa đăng nhập thì lưu link QR vào session rồi chuyển về login
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$message = '';
$message_type = 'success';
$member = null;
$activePackage = null;
$todayCheckin = null;

/*
|--------------------------------------------------------------------------
| Lấy user
|--------------------------------------------------------------------------
*/
$stmt_user = $conn->prepare("
    SELECT id, full_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user) {
    $message = 'Không tìm thấy tài khoản người dùng.';
    $message_type = 'danger';       
} else {
    /*
    |--------------------------------------------------------------------------
    | Tìm member theo email hoặc số điện thoại
    |--------------------------------------------------------------------------
    */
    $phone = trim((string)($user['phone'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));

    $stmt_member = $conn->prepare("
        SELECT id, full_name, phone, email
        FROM members
        WHERE (phone = ? AND phone IS NOT NULL AND phone <> '')
           OR (email = ? AND email IS NOT NULL AND email <> '')
        LIMIT 1
    ");
    $stmt_member->bind_param("ss", $phone, $email);
    $stmt_member->execute();
    $member = $stmt_member->get_result()->fetch_assoc();
    $stmt_member->close();

    if (!$member) {
        $message = 'Tài khoản của bạn chưa được liên kết với hồ sơ hội viên.';
        $message_type = 'warning';
    } else {
        $member_id = (int)$member['id'];

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra gói active trong member_packages
        |--------------------------------------------------------------------------
        */
        $stmt_package = $conn->prepare("
            SELECT 
                mp.id,
                mp.member_id,
                mp.package_id,
                mp.start_date,
                mp.end_date,
                mp.status,
                p.package_name
            FROM member_packages mp
            JOIN packages p ON p.id = mp.package_id
            WHERE mp.member_id = ?
              AND mp.status = 'active'
              AND mp.start_date <= CURDATE()
              AND mp.end_date >= CURDATE()
            ORDER BY mp.end_date DESC
            LIMIT 1
        ");
        $stmt_package->bind_param("i", $member_id);
        $stmt_package->execute();
        $activePackage = $stmt_package->get_result()->fetch_assoc();
        $stmt_package->close();

        if (!$activePackage) {
            $message = 'Bạn chưa có gói tập đang hoạt động hoặc gói đã hết hạn.';
            $message_type = 'warning';
        } else {
            /*
            |--------------------------------------------------------------------------
            | Kiểm tra hôm nay đã check-in chưa
            |--------------------------------------------------------------------------
            */
            $stmt_today = $conn->prepare("
                SELECT id, checkin_date, checkin_time, checkout_time, status
                FROM checkins
                WHERE member_id = ?
                  AND checkin_date = CURDATE()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt_today->bind_param("i", $member_id);
            $stmt_today->execute();
            $todayCheckin = $stmt_today->get_result()->fetch_assoc();
            $stmt_today->close();

           if (!$todayCheckin) {
    /*
    |--------------------------------------------------------------------------
    | Lần quét đầu tiên trong ngày => CHECK-IN
    |--------------------------------------------------------------------------
    */
    $status = 'checked_in';
    $checkin_method = 'qr';
    $note = 'Check-in qua QR';

    $stmt_insert = $conn->prepare("
        INSERT INTO checkins (
            member_id,
            checkin_date,
            checkin_time,
            status,
            checkin_method,
            note
        )
        VALUES (?, CURDATE(), NOW(), ?, ?, ?)
    ");

    $stmt_insert->bind_param(
        "isss",
        $member_id,
        $status,
        $checkin_method,
        $note
    );

    $stmt_insert->execute();
    $stmt_insert->close();

    $message = 'Check-in thành công. Chúc bạn có buổi tập hiệu quả!';
    $message_type = 'success';

} else {
    /*
    |--------------------------------------------------------------------------
    | Đã check-in hôm nay nhưng chưa có giờ ra => CHECK-OUT
    |--------------------------------------------------------------------------
    */
    if (empty($todayCheckin['checkout_time'])) {
        $status = 'checked_out';

        $stmt_checkout = $conn->prepare("
            UPDATE checkins
            SET checkout_time = NOW(),
                status = ?
            WHERE id = ?
        ");

        $stmt_checkout->bind_param(
            "si",
            $status,
            $todayCheckin['id']
        );

        $stmt_checkout->execute();
        $stmt_checkout->close();

        $message = 'Check-out thành công. Cảm ơn bạn đã tập luyện hôm nay!';
        $message_type = 'success';

    } else {
        /*
        |--------------------------------------------------------------------------
        | Đã có cả giờ vào và giờ ra
        |--------------------------------------------------------------------------
        */
        $message = 'Bạn đã hoàn tất check-in và check-out hôm nay.';
        $message_type = 'info';
    }
}
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả Check-in</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#07111f; color:#fff;">

<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card border-0 shadow-lg" style="max-width:520px; width:100%; border-radius:22px;">
        <div class="card-body p-4 p-md-5 text-center">

            <?php if ($message_type === 'success'): ?>
                <div class="display-1 text-success mb-3">✓</div>
            <?php elseif ($message_type === 'warning'): ?>
                <div class="display-1 text-warning mb-3">!</div>
            <?php elseif ($message_type === 'danger'): ?>
                <div class="display-1 text-danger mb-3">×</div>
            <?php else: ?>
                <div class="display-1 text-info mb-3">i</div>
            <?php endif; ?>

            <h2 class="fw-bold text-dark mb-3">Kết quả Check-in</h2>

            <div class="alert alert-<?php echo h($message_type); ?>">
                <?php echo h($message); ?>
            </div>

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
                    <p class="mb-1"><strong>Ngày bắt đầu:</strong> <?php echo h($activePackage['start_date']); ?></p>
                    <p class="mb-0"><strong>Ngày hết hạn:</strong> <?php echo h($activePackage['end_date']); ?></p>
                </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <a href="../dashboard/index.php" class="btn btn-primary">
                    Về Dashboard hội viên
                </a>

                <a href="index.php" class="btn btn-outline-secondary">
                    Xem lịch sử check-in
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
