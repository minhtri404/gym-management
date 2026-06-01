<?php
require_once __DIR__ . '/vnpay-config.php';

$base_path = '../../';

$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';

$inputData = [];

foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) === 'vnp_' && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
        $inputData[$key] = $value;
    }
}

ksort($inputData);

$hashData = '';

foreach ($inputData as $key => $value) {
    $hashData .= urlencode($key) . '=' . urlencode($value) . '&';
}

$hashData = rtrim($hashData, '&');

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

if ($secureHash !== $vnp_SecureHash) {
    die('Sai chữ ký VNPAY. Giao dịch không hợp lệ.');
}

$vnp_TxnRef = $_GET['vnp_TxnRef'] ?? '';
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '';
$vnp_BankCode = $_GET['vnp_BankCode'] ?? '';
$vnp_PayDate = $_GET['vnp_PayDate'] ?? '';

if ($vnp_TxnRef === '') {
    die('Thiếu mã giao dịch.');
}

// Tìm payment theo mã giao dịch
$stmt = $conn->prepare("
    SELECT id, registration_id, payment_status
    FROM payments
    WHERE vnp_txn_ref = ?
    LIMIT 1
");
$stmt->bind_param("s", $vnp_TxnRef);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    die('Không tìm thấy giao dịch trong hệ thống.');
}

if ($payment['payment_status'] === 'paid') {
    header('Location: ' . $base_path . 'user/home.php?payment_success=1');
    exit;
}

if ($vnp_ResponseCode === '00') {
    // VNPAY báo thanh toán thành công
    $stmt = $conn->prepare("
        UPDATE payments
        SET payment_status = 'paid',
            vnp_transaction_no = ?,
            vnp_response_code = ?,
            vnp_bank_code = ?,
            vnp_pay_date = ?,
            note = 'VNPAY payment success. Waiting admin confirmation.'
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ssssi",
        $vnp_TransactionNo,
        $vnp_ResponseCode,
        $vnp_BankCode,
        $vnp_PayDate,
        $payment['id']
    );
    $stmt->execute();
    $stmt->close();

    // Cập nhật đơn đăng ký: đã thanh toán, chờ admin xác nhận kích hoạt
    $stmt = $conn->prepare("
        UPDATE package_registrations
        SET payment_status = 'paid'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $payment['registration_id']);
    $stmt->execute();
    $stmt->close();

    header('Location: ' . $base_path . 'user/home.php?payment_success=1');
    exit;
}

// Nếu VNPAY báo thất bại / hủy
$stmt = $conn->prepare("
    UPDATE payments
    SET payment_status = 'failed',
        vnp_response_code = ?,
        note = 'VNPAY payment failed or cancelled.'
    WHERE id = ?
");
$stmt->bind_param("si", $vnp_ResponseCode, $payment['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    UPDATE package_registrations
    SET payment_status = 'failed'
    WHERE id = ?
");
$stmt->bind_param("i", $payment['registration_id']);
$stmt->execute();
$stmt->close();

header('Location: ' . $base_path . 'user/home.php?payment_failed=1');
exit;
