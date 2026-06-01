<?php
require_once __DIR__ . '/vnpay-config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$registration_id = (int)($_GET['registration_id'] ?? 0);

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
        p.package_name AS package_name,
        p.price
    FROM package_registrations pr
    JOIN packages p ON pr.package_id = p.id
    WHERE pr.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $registration_id);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registration) {
    die('Không tìm thấy đơn đăng ký.');
}

if ($registration['payment_status'] === 'paid') {
    die('Đơn này đã thanh toán.');
}

$amount = (float)$registration['price'];
$vnp_TxnRef = time() . '_' . $registration_id;

// Tạo payment pending
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

// Gắn payment_id vào registration
$stmt = $conn->prepare("
    UPDATE package_registrations
    SET payment_id = ?,
        payment_status = 'pending'
    WHERE id = ?
");
$stmt->bind_param("ii", $payment_id, $registration_id);
$stmt->execute();
$stmt->close();

// Tạo URL thanh toán VNPAY
$vnp_OrderInfo = 'Thanh toan goi tap ' . $registration['package_name'];
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
