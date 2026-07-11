<?php
require_once __DIR__ . '/vnpay-config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$registration_id = (int)($_POST['registration_id'] ?? $_GET['registration_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

if ($registration_id <= 0) {
    die('Thiếu mã đăng ký gói.');
}

// Lấy thông tin đơn đăng ký + gói tập
$stmt = $conn->prepare("
    SELECT 
        pr.id AS registration_id,
        pr.package_id,
        pr.status,
        pr.payment_status,
        pr.payment_id,
        p.package_name AS package_name,
        p.price,
        linked_payment.payment_status AS linked_payment_status,
        linked_payment.vnp_txn_ref AS linked_txn_ref
    FROM package_registrations pr
    JOIN packages p ON pr.package_id = p.id
    LEFT JOIN payments linked_payment ON linked_payment.id = pr.payment_id
    WHERE pr.id = ?
      AND pr.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $registration_id, $user_id);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registration) {
    http_response_code(404);
    die('Không tìm thấy đơn đăng ký thuộc tài khoản của bạn.');
}

if ($registration['payment_status'] === 'paid') {
    die('Đơn này đã thanh toán.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $packageName = htmlspecialchars((string) $registration['package_name'], ENT_QUOTES, 'UTF-8');
    $formattedAmount = number_format((float) $registration['price'], 0, ',', '.');
    $csrfToken = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Xác nhận thanh toán VNPAY</title>
        <style>
            body { margin: 0; font-family: Arial, sans-serif; background: #07111f; color: #f8fafc; }
            main { width: min(520px, calc(100% - 32px)); margin: 10vh auto; padding: 32px; box-sizing: border-box; background: #101b2f; border: 1px solid #26344d; border-radius: 8px; }
            h1 { margin: 0 0 20px; font-size: 26px; }
            .row { display: flex; justify-content: space-between; gap: 20px; padding: 12px 0; border-bottom: 1px solid #26344d; }
            .amount { color: #2bb6f6; font-weight: 700; }
            .actions { display: flex; gap: 12px; margin-top: 24px; }
            button, a { min-height: 44px; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; border-radius: 6px; text-decoration: none; font-weight: 700; }
            button { border: 0; background: #22b5f6; color: #04111f; cursor: pointer; }
            a { border: 1px solid #526078; color: #f8fafc; }
        </style>
    </head>
    <body>
    <main>
        <h1>Xác nhận thanh toán</h1>
        <div class="row"><span>Gói tập</span><strong><?php echo $packageName; ?></strong></div>
        <div class="row"><span>Số tiền</span><span class="amount"><?php echo $formattedAmount; ?>đ</span></div>
        <form method="post" class="actions">
            <input type="hidden" name="registration_id" value="<?php echo $registration_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <button type="submit">Thanh toán qua VNPAY</button>
            <a href="../../user/package/index.php">Quay lại</a>
        </form>
    </main>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(403);
    die('Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.');
}

$amount = (float)$registration['price'];
$vnp_TxnRef = (string) ($registration['linked_txn_ref'] ?? '');
$payment_id = (int) ($registration['payment_id'] ?? 0);

if ($payment_id <= 0 || $registration['linked_payment_status'] !== 'pending' || $vnp_TxnRef === '') {
    $vnp_TxnRef = time() . '_' . $registration_id . '_' . bin2hex(random_bytes(4));

    $stmt = $conn->prepare("
        INSERT INTO payments (
            registration_id,
            package_id,
            amount,
            payment_method,
            payment_status,
            vnp_txn_ref
        )
        VALUES (?, ?, ?, 'vnpay', 'pending', ?)
    ");
    $stmt->bind_param(
        "iids",
        $registration['registration_id'],
        $registration['package_id'],
        $amount,
        $vnp_TxnRef
    );
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE package_registrations
        SET payment_id = ?,
            payment_status = 'pending'
        WHERE id = ?
          AND user_id = ?
    ");
    $stmt->bind_param("iii", $payment_id, $registration_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Tạo URL thanh toán VNPAY
$vnp_OrderInfo = 'Thanh toán gói tập ' . $registration['package_name'];
$vnp_OrderType = 'billpayment';
$vnp_Amount = $amount * 100;
$vnp_Locale = 'vn';
$vnp_BankCode = '';
$vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$inputData = [
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
];

ksort($inputData);

$query = "";
$hashdata = "";

foreach ($inputData as $key => $value) {
    $hashdata .= urlencode($key) . '=' . urlencode($value) . '&';
    $query .= urlencode($key) . '=' . urlencode($value) . '&';
}

$hashdata = rtrim($hashdata, '&');
$query = rtrim($query, '&');

$vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

$vnp_Url_Payment = $vnp_Url . '?' . $query . '&vnp_SecureHash=' . $vnpSecureHash;

header('Location: ' . $vnp_Url_Payment);
exit;       
