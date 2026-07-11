<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function get_mail_config_value($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../.env';
    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                if (trim($parts[0]) !== $key) {
                    continue;
                }

                return trim(trim($parts[1]), "\"'");
            }
        }
    }

    return $default;
}

function sendOTP($toEmail, $otp)
{
    $mailHost = get_mail_config_value('MAIL_HOST', 'smtp.gmail.com');
    $mailPort = (int)get_mail_config_value('MAIL_PORT', '587');
    $mailUsername = get_mail_config_value('MAIL_USERNAME');
    $mailPassword = get_mail_config_value('MAIL_PASSWORD');
    $mailEncryption = strtolower(get_mail_config_value('MAIL_ENCRYPTION', 'tls'));
    $mailFromAddress = get_mail_config_value('MAIL_FROM_ADDRESS', $mailUsername);
    $mailFromName = get_mail_config_value('MAIL_FROM_NAME', 'Gym System');

    if ($mailUsername === '' || $mailPassword === '' || $mailFromAddress === '') {
        throw new Exception('MAIL_USERNAME, MAIL_PASSWORD hoặc MAIL_FROM_ADDRESS chưa được cấu hình.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $mailHost;
    $mail->SMTPAuth = true;
    $mail->CharSet = 'UTF-8';

    $mail->Username = $mailUsername;
    $mail->Password = $mailPassword;

    $mail->SMTPSecure = $mailEncryption;
    $mail->Port = $mailPort;

    $mail->setFrom($mailFromAddress, $mailFromName);
    $mail->addAddress($toEmail);

    $mail->Subject = 'Mã OTP đăng nhập';
    $mail->Body = "Mã OTP của bạn là: $otp (có hiệu lực 5 phút)";

    $mail->send();
}
function sendPasswordResetOTP($toEmail, $otp)
{
    $mailHost = get_mail_config_value('MAIL_HOST', 'smtp.gmail.com');
    $mailPort = (int)get_mail_config_value('MAIL_PORT', '587');
    $mailUsername = get_mail_config_value('MAIL_USERNAME');
    $mailPassword = get_mail_config_value('MAIL_PASSWORD');
    $mailEncryption = strtolower(get_mail_config_value('MAIL_ENCRYPTION', 'tls'));
    $mailFromAddress = get_mail_config_value('MAIL_FROM_ADDRESS', $mailUsername);
    $mailFromName = get_mail_config_value('MAIL_FROM_NAME', 'Gym System');

    if ($mailUsername === '' || $mailPassword === '' || $mailFromAddress === '') {
        throw new Exception('MAIL_USERNAME, MAIL_PASSWORD hoặc MAIL_FROM_ADDRESS chưa được cấu hình.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $mailHost;
    $mail->SMTPAuth = true;
    $mail->CharSet = 'UTF-8';

    $mail->Username = $mailUsername;
    $mail->Password = $mailPassword;

    $mail->SMTPSecure = $mailEncryption;
    $mail->Port = $mailPort;

    $mail->setFrom($mailFromAddress, $mailFromName);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = 'OTP dat lai mat khau - Gym Management';
    $mail->Body = "
        <h3>Đặt lại mật khẩu</h3>
        <p>Mã OTP của bạn là:</p>
        <h2 style='letter-spacing:4px;'>$otp</h2>
        <p>Mã này có hiệu lực trong 5 phút.</p>
        <p>Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này.</p>
    ";

    $mail->send();
}

function sendPackageRegistrationOTP($toEmail, $otp)
{
    $mailHost = get_mail_config_value('MAIL_HOST', 'smtp.gmail.com');
    $mailPort = (int)get_mail_config_value('MAIL_PORT', '587');
    $mailUsername = get_mail_config_value('MAIL_USERNAME');
    $mailPassword = get_mail_config_value('MAIL_PASSWORD');
    $mailEncryption = strtolower(get_mail_config_value('MAIL_ENCRYPTION', 'tls'));
    $mailFromAddress = get_mail_config_value('MAIL_FROM_ADDRESS', $mailUsername);
    $mailFromName = get_mail_config_value('MAIL_FROM_NAME', 'FLEXZONE');

    if ($mailUsername === '' || $mailPassword === '' || $mailFromAddress === '') {
        throw new Exception('MAIL_USERNAME, MAIL_PASSWORD hoặc MAIL_FROM_ADDRESS chưa được cấu hình.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $mailHost;
    $mail->SMTPAuth = true;
    $mail->CharSet = 'UTF-8';

    $mail->Username = $mailUsername;
    $mail->Password = $mailPassword;

    $mail->SMTPSecure = $mailEncryption;
    $mail->Port = $mailPort;

    $mail->setFrom($mailFromAddress, $mailFromName);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = 'OTP xác nhận đăng ký gói tập - FLEXZONE';
    $mail->Body = "
        <div style='font-family:Arial,sans-serif;color:#111827;line-height:1.6'>
            <h2 style='margin:0 0 12px;color:#0ea5e9'>FLEXZONE</h2>
            <p>Mã OTP xác nhận đăng ký gói tập của bạn là:</p>
            <div style='font-size:32px;font-weight:800;letter-spacing:8px;color:#0f172a;margin:18px 0'>$otp</div>
            <p>Mã này có hiệu lực trong 5 phút. Nếu bạn không thực hiện đăng ký, vui lòng bỏ qua email này.</p>
        </div>
    ";
    $mail->AltBody = "Mã OTP xác nhận đăng ký gói tập FLEXZONE của bạn là: $otp. Mã có hiệu lực trong 5 phút.";

    $mail->send();
}
