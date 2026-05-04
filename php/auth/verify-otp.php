<?php
session_start();
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (empty($_SESSION['otp_user_id'])) {
    header('Location: ' . $base_path . 'login.php');
    exit;
}

$error = '';
$success = '';

// helper mask email
function mask_email($email) {
    if (empty($email)) return '';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $masked = substr($name, 0, 1) . str_repeat('*', max(0, $len-1));
    } else {
        $masked = substr($name, 0, 1) . str_repeat('*', max(0, $len-2)) . substr($name, -1);
    }
    return $masked . '@' . $domain;
}

$user_id = (int)$_SESSION['otp_user_id'];
$user_email = $_SESSION['otp_user_email'] ?? '';
$maskedEmail = mask_email($user_email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Resend OTP action
    if (isset($_POST['resend'])) {
        // simple rate limit: max 5 OTPs per hour per user
        $stmtLimit = $conn->prepare("SELECT COUNT(*) AS cnt FROM otp_codes WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $stmtLimit->bind_param('i', $user_id);
        $stmtLimit->execute();
        $resLimit = $stmtLimit->get_result()->fetch_assoc();
        $stmtLimit->close();

        $maxPerHour = 5;
        if (($resLimit['cnt'] ?? 0) >= $maxPerHour) {
            $error = 'Bạn đã yêu cầu OTP quá nhiều lần. Vui lòng thử lại sau 1 giờ.';
        } else {
            $otp = rand(100000, 999999);
            $stmtIns = $conn->prepare("INSERT INTO otp_codes (user_id, email, otp_code, expires_at, is_used) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)");
            if ($stmtIns) {
                $stmtIns->bind_param('iss', $user_id, $user_email, $otp);
                $stmtIns->execute();
                $stmtIns->close();
            }
            // send mail
            require_once __DIR__ . '/../../includes/mailer.php';
            try {
                sendOTP($user_email, $otp);
                $success = 'Mã OTP đã được gửi đến ' . htmlspecialchars($maskedEmail);
            } catch (Exception $e) {
                $error = 'Gửi email thất bại. Vui lòng thử lại sau.';
            }
        }

    } else {
        // Verify OTP submit
        $otp_input = trim($_POST['otp'] ?? '');

        $stmt = $conn->prepare("SELECT * FROM otp_codes WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $otp_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($otp = $result->fetch_assoc()) {
                // mark used
                $conn->query("UPDATE otp_codes SET is_used = 1 WHERE id = " . (int)$otp['id']);

                // login the user
                $ust = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1");
                $ust->bind_param('i', $user_id);
                $ust->execute();
                $urow = $ust->get_result()->fetch_assoc();
                $ust->close();

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$urow['id'];
                $_SESSION['user_name'] = $urow['full_name'] ?? '';
                $_SESSION['user_email'] = $urow['email'] ?? '';
                $_SESSION['user_role'] = $urow['role'] ?? '';
                unset($_SESSION['otp_user_id'], $_SESSION['otp_user_email']);

                header('Location: ' . $base_path . 'user/home.php');
                exit;
            } else {
                $error = "OTP không hợp lệ hoặc đã hết hạn";
            }
            $stmt->close();
        } else {
            $error = 'Lỗi hệ thống (không thể kiểm tra OTP).';
        }
    }

}
?>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác thực OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{padding:30px;background:#f8fafc}</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Nhập mã OTP</h4>

                    <p class="text-muted">Chúng tôi đã gửi mã OTP tới: <strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label">Mã OTP</label>
                            <input type="text" name="otp" class="form-control" placeholder="Nhập OTP" required>
                        </div>
                        <div class="d-grid mb-2">
                            <button class="btn btn-primary">Xác nhận</button>
                        </div>
                    </form>

                    <form method="POST" class="mb-0">
                        <input type="hidden" name="resend" value="1">
                        <div class="d-grid">
                            <button id="resendBtn" type="submit" class="btn btn-outline-secondary">Gửi lại mã</button>
                        </div>
                    </form>

                    <div class="mt-3"><a href="<?php echo $base_path; ?>login.php">Trở về đăng nhập</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
<script>
    // disable resend button for short period to avoid accidental double submits
    document.addEventListener('DOMContentLoaded', function(){
        const resendBtn = document.getElementById('resendBtn');
        if (!resendBtn) return;
        resendBtn.addEventListener('click', function(){
            resendBtn.disabled = true;
            setTimeout(function(){ resendBtn.disabled = false; }, 10000);
        });
    });
</script>
</html>
