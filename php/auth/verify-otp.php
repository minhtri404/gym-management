<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

if (empty($_SESSION['otp_user_id'])) {
    header('Location: ' . $base_path . 'login.php');
    exit;
}

$error = '';
$success = '';

function mask_email($email)
{
    if (empty($email)) {
        return '';
    }

    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }

    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);

    if ($len <= 2) {
        $masked = substr($name, 0, 1) . str_repeat('*', max(0, $len - 1));
    } else {
        $masked = substr($name, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($name, -1);
    }

    return $masked . '@' . $domain;
}

function get_otp_remaining_seconds(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare("
        SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
        FROM otp_codes
        WHERE user_id = ?
          AND is_used = 0
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return 300;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $seconds = (int)($row['seconds_left'] ?? 0);
    return $seconds > 0 ? $seconds : 300;
}

$user_id = (int)$_SESSION['otp_user_id'];
$user_email = $_SESSION['otp_user_email'] ?? '';
$maskedEmail = mask_email($user_email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        http_response_code(403);
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif (isset($_POST['resend'])) {
        $stmtLimit = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM otp_codes
            WHERE user_id = ?
              AND created_at > (NOW() - INTERVAL 1 HOUR)
        ");
        $stmtLimit->bind_param('i', $user_id);
        $stmtLimit->execute();
        $resLimit = $stmtLimit->get_result()->fetch_assoc();
        $stmtLimit->close();

        $maxPerHour = 5;
        if ((int)($resLimit['cnt'] ?? 0) >= $maxPerHour) {
            $error = 'Bạn đã yêu cầu OTP quá nhiều lần. Vui lòng thử lại sau 1 giờ.';
        } else {
            $otp = (string) rand(100000, 999999);
            $stmtIns = $conn->prepare("
                INSERT INTO otp_codes (user_id, email, otp_code, expires_at, is_used)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
            ");

            if ($stmtIns) {
                $stmtIns->bind_param('iss', $user_id, $user_email, $otp);
                $stmtIns->execute();
                $stmtIns->close();
            }

            require_once __DIR__ . '/../../includes/mailer.php';

            try {
                sendOTP($user_email, $otp);
                $success = 'Mã OTP đã được gửi đến ' . htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8');
            } catch (Exception $e) {
                $error = 'Gửi email thất bại. Vui lòng thử lại sau.';
            }
        }
    } else {
        $otp_input = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? ''));

        if (strlen($otp_input) !== 6) {
            $error = 'Vui lòng nhập đầy đủ 6 chữ số OTP.';
        } else {
            $stmt = $conn->prepare("
                SELECT *
                FROM otp_codes
                WHERE user_id = ?
                  AND otp_code = ?
                  AND is_used = 0
                  AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('is', $user_id, $otp_input);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($otp = $result->fetch_assoc()) {
                    $conn->query('UPDATE otp_codes SET is_used = 1 WHERE id = ' . (int)$otp['id']);

                    $ust = $conn->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
                    $ust->bind_param('i', $user_id);
                    $ust->execute();
                    $urow = $ust->get_result()->fetch_assoc();
                    $ust->close();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$urow['id'];
                    $_SESSION['user_name'] = $urow['full_name'] ?? '';
                    $_SESSION['user_email'] = $urow['email'] ?? '';
                    $_SESSION['user_role'] = $urow['role'] ?? '';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    unset($_SESSION['otp_user_id'], $_SESSION['otp_user_email']);

                    if (strtolower(trim($_SESSION['user_role'] ?? '')) === 'admin') {
                        unset($_SESSION['redirect_after_login'], $_SESSION['post_login_redirect']);
                        header('Location: ' . $base_path . 'user/home.php');
                        exit;
                    }

                    $redirect_after_login = trim($_SESSION['redirect_after_login'] ?? '');
                    unset($_SESSION['redirect_after_login']);

                    if ($redirect_after_login !== '' && !preg_match('#^(?:https?:)?//#i', $redirect_after_login)) {
                        header('Location: ' . $redirect_after_login);
                        exit;
                    }

                    $post_login_redirect = trim($_SESSION['post_login_redirect'] ?? '');
                    unset($_SESSION['post_login_redirect']);

                    if ($post_login_redirect !== '' && !preg_match('#^(?:https?:)?//#i', $post_login_redirect)) {
                        header('Location: ' . $base_path . ltrim($post_login_redirect, '/'));
                        exit;
                    }

                    header('Location: ' . $base_path . 'user/home.php');
                    exit;
                } else {
                    $error = 'OTP không hợp lệ hoặc đã hết hạn.';
                }

                $stmt->close();
            } else {
                $error = 'Lỗi hệ thống. Không thể kiểm tra OTP.';
            }
        }
    }
}

$otpRemainingSeconds = get_otp_remaining_seconds($conn, $user_id);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác thực OTP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --otp-bg: #eef7ff;
            --otp-surface: rgba(255, 255, 255, 0.88);
            --otp-card: rgba(255, 255, 255, 0.94);
            --otp-border: rgba(14, 116, 144, 0.12);
            --otp-text: #0f172a;
            --otp-muted: #64748b;
            --otp-primary: #0ea5e9;
            --otp-primary-dark: #0369a1;
            --otp-soft: #e0f2fe;
            --otp-shadow: 0 24px 80px rgba(14, 116, 144, 0.16);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            color: var(--otp-text);
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 24%),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.14), transparent 20%),
                linear-gradient(180deg, #f8fcff 0%, #edf7ff 100%);
        }

        .otp-shell {
            min-height: 100vh;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .otp-stage {
            width: min(1320px, 100%);
            min-height: calc(100vh - 4rem);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(246, 251, 255, 0.92)),
                radial-gradient(circle at bottom left, rgba(125, 211, 252, 0.18), transparent 26%);
            border: 1px solid rgba(125, 211, 252, 0.28);
            border-radius: 34px;
            box-shadow: var(--otp-shadow);
            position: relative;
            overflow: hidden;
            padding: 2rem 2.2rem 2.6rem;
        }

        .otp-stage::before,
        .otp-stage::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .otp-stage::before {
            width: 340px;
            height: 340px;
            top: -120px;
            right: -60px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.22), transparent 65%);
        }

        .otp-stage::after {
            width: 260px;
            height: 260px;
            bottom: -120px;
            left: -80px;
            background: radial-gradient(circle, rgba(125, 211, 252, 0.18), transparent 65%);
        }

        .otp-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        .otp-brand {
            display: inline-flex;
            align-items: center;
            color: var(--otp-text);
            font-weight: 800;
            font-size: 1.8rem;
            letter-spacing: 0;
            text-decoration: none;
        }

        .otp-help {
            color: var(--otp-muted);
            font-size: 1rem;
        }

        .otp-help a {
            color: var(--otp-primary-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .otp-main {
            min-height: calc(100vh - 12rem);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .otp-card {
            width: min(700px, 100%);
            background: var(--otp-card);
            border: 1px solid rgba(186, 230, 253, 0.68);
            border-radius: 30px;
            box-shadow: 0 26px 70px rgba(14, 116, 144, 0.12);
            padding: 2.6rem 2.4rem 2.2rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .otp-art {
            width: 152px;
            height: 152px;
            margin: 0 auto 1.4rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.96);
            border: 10px solid rgba(207, 250, 254, 0.78);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 18px 38px rgba(14, 165, 233, 0.12);
            overflow: hidden;
        }

        .otp-art-icon {
            width: 112px;
            height: 82px;
            object-fit: contain;
            display: block;
        }

        .otp-title {
            font-size: clamp(2.1rem, 4vw, 3.2rem);
            font-weight: 800;
            letter-spacing: 0;
            margin: 0 0 0.65rem;
            color: #111827;
        }

        .otp-subtitle {
            max-width: 450px;
            margin: 0 auto 1.85rem;
            color: var(--otp-muted);
            font-size: 1.08rem;
            line-height: 1.75;
        }

        .otp-email {
            color: var(--otp-text);
            font-weight: 700;
        }

        .otp-change-email {
            color: var(--otp-primary-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .otp-alert {
            border-radius: 18px;
            padding: 0.95rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.96rem;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .otp-alert-success {
            background: rgba(220, 252, 231, 0.95);
            border-color: rgba(34, 197, 94, 0.18);
            color: #166534;
        }

        .otp-alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.14);
            color: #b91c1c;
        }

        .otp-form {
            margin-top: 1.6rem;
        }

        .otp-inputs {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.9rem;
            max-width: 540px;
            margin: 0 auto 1.4rem;
        }

        .otp-digit {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 18px;
            border: 1.5px solid rgba(148, 163, 184, 0.26);
            background: #ffffff;
            text-align: center;
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--otp-text);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .otp-digit::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        .otp-digit:focus {
            outline: none;
            border-color: rgba(14, 165, 233, 0.88);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
            transform: translateY(-1px);
        }

        .otp-meta {
            color: var(--otp-muted);
            font-size: 1rem;
            margin-bottom: 1.65rem;
        }

        .otp-countdown {
            color: var(--otp-primary-dark);
            font-weight: 700;
        }

        .otp-submit {
            width: 100%;
            max-width: 540px;
            border: 0;
            border-radius: 16px;
            padding: 1rem 1.2rem;
            background: linear-gradient(135deg, #38bdf8, #0ea5e9 48%, #0284c7);
            color: #ffffff;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            box-shadow: 0 18px 36px rgba(14, 165, 233, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .otp-submit:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
        }

        .otp-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.65rem auto 1.15rem;
            max-width: 540px;
            color: #94a3b8;
            font-size: 0.98rem;
        }

        .otp-divider::before,
        .otp-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(148, 163, 184, 0.22);
        }

        .otp-resend-row {
            color: var(--otp-muted);
            font-size: 1rem;
        }

        .otp-resend-btn {
            border: 0;
            background: none;
            color: var(--otp-primary-dark);
            font-weight: 700;
            padding: 0;
            text-decoration: none;
        }

        .otp-resend-btn:disabled {
            color: #94a3b8;
            cursor: not-allowed;
        }

        .otp-back {
            margin-top: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--otp-muted);
            font-weight: 600;
            text-decoration: none;
        }

        @media (max-width: 991px) {
            .otp-shell {
                padding: 1rem;
            }

            .otp-stage {
                min-height: auto;
                padding: 1.3rem 1rem 1.8rem;
                border-radius: 26px;
            }

            .otp-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .otp-main {
                min-height: auto;
                padding-top: 1.4rem;
            }

            .otp-card {
                padding: 2rem 1.25rem 1.8rem;
                border-radius: 24px;
            }
        }

        @media (max-width: 576px) {
            .otp-brand {
                font-size: 1.5rem;
            }

            .otp-title {
                font-size: 2rem;
            }

            .otp-subtitle,
            .otp-meta,
            .otp-resend-row {
                font-size: 0.96rem;
            }

            .otp-inputs {
                gap: 0.55rem;
            }

            .otp-digit {
                border-radius: 14px;
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>
    <div class="otp-shell">
        <section class="otp-stage">
            <div class="otp-topbar">
                <a href="<?php echo $base_path; ?>login.php" class="otp-brand">
                    <span>FLEXZONE</span>
                </a>

                <div class="otp-help">
                    Cần hỗ trợ?
                    <a href="mailto:support@flexzone.local">Liên hệ chúng tôi</a>
                </div>
            </div>

            <div class="otp-main">
                <div class="otp-card">
                    <div class="otp-art">
                        <img src="<?php echo $base_path; ?>assets/images/1.png" class="otp-art-icon" alt="OTP">
                    </div>

                    <h1 class="otp-title">Nhập mã OTP</h1>
                    <p class="otp-subtitle">
                        Chúng tôi đã gửi mã OTP đến email
                        <span class="otp-email"><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></span>
                        <a class="otp-change-email" href="<?php echo $base_path; ?>login.php">(Đổi email)</a>
                    </p>

                    <?php if ($success !== ''): ?>
                        <div class="otp-alert otp-alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="otp-alert otp-alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="otp-form" id="otpForm" novalidate>
                        <input type="hidden" name="otp" id="otpHidden" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="otp-inputs">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="1"
                                    class="otp-digit"
                                    data-otp-index="<?php echo $i; ?>"
                                    placeholder="-"
                                    autocomplete="one-time-code"
                                >
                            <?php endfor; ?>
                        </div>

                        <div class="otp-meta">
                            Mã có hiệu lực trong
                            <span class="otp-countdown" id="otpCountdown" data-seconds="<?php echo (int)$otpRemainingSeconds; ?>">
                                05:00
                            </span>
                        </div>

                        <button type="submit" class="otp-submit">Xác nhận</button>
                    </form>

                    <div class="otp-divider">hoặc</div>

                    <form method="POST" class="mb-0" id="resendForm">
                        <input type="hidden" name="resend" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="otp-resend-row">
                            Chưa nhận được mã?
                            <button id="resendBtn" type="submit" class="otp-resend-btn">
                                Gửi lại mã
                            </button>
                            <span id="resendCountdownWrap" style="display:none;">(<span id="resendCountdown">60</span>s)</span>
                        </div>
                    </form>

                    <a href="<?php echo $base_path; ?>login.php" class="otp-back">
                        <i class="bi bi-arrow-left"></i>
                        Quay lại đăng nhập
                    </a>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('otpForm');
            const hiddenInput = document.getElementById('otpHidden');
            const digitInputs = Array.from(document.querySelectorAll('.otp-digit'));
            const resendBtn = document.getElementById('resendBtn');
            const resendCountdown = document.getElementById('resendCountdown');
            const resendCountdownWrap = document.getElementById('resendCountdownWrap');
            const otpCountdown = document.getElementById('otpCountdown');

            function updateHiddenOtp() {
                hiddenInput.value = digitInputs.map((input) => input.value.trim()).join('');
            }

            function formatSeconds(totalSeconds) {
                const safeSeconds = Math.max(0, totalSeconds);
                const minutes = Math.floor(safeSeconds / 60);
                const seconds = safeSeconds % 60;
                return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            }

            digitInputs.forEach((input, index) => {
                input.addEventListener('input', function (event) {
                    const value = event.target.value.replace(/\D/g, '');
                    event.target.value = value.slice(-1);
                    updateHiddenOtp();

                    if (event.target.value !== '' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                        digitInputs[index + 1].select();
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && event.target.value === '' && index > 0) {
                        digitInputs[index - 1].focus();
                        digitInputs[index - 1].select();
                    }

                    if (event.key === 'ArrowLeft' && index > 0) {
                        digitInputs[index - 1].focus();
                    }

                    if (event.key === 'ArrowRight' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                    }
                });

                input.addEventListener('paste', function (event) {
                    const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (pasted.length === 0) {
                        return;
                    }

                    event.preventDefault();
                    const chars = pasted.slice(0, digitInputs.length).split('');
                    digitInputs.forEach((field, fieldIndex) => {
                        field.value = chars[fieldIndex] ?? '';
                    });
                    updateHiddenOtp();

                    const targetIndex = Math.min(chars.length, digitInputs.length - 1);
                    digitInputs[targetIndex].focus();
                });
            });

            form.addEventListener('submit', function (event) {
                updateHiddenOtp();

                if (hiddenInput.value.length !== 6) {
                    event.preventDefault();
                    digitInputs.find((input) => input.value.trim() === '')?.focus();
                }
            });

            if (digitInputs.length > 0) {
                digitInputs[0].focus();
            }

            if (otpCountdown) {
                let remaining = parseInt(otpCountdown.dataset.seconds || '300', 10);
                otpCountdown.textContent = formatSeconds(remaining);

                window.setInterval(function () {
                    remaining = Math.max(0, remaining - 1);
                    otpCountdown.textContent = formatSeconds(remaining);
                }, 1000);
            }

            if (resendBtn && resendCountdown && resendCountdownWrap) {
                resendBtn.addEventListener('click', function () {
                    let remaining = 60;
                    resendBtn.disabled = true;
                    resendCountdownWrap.style.display = 'inline';
                    resendCountdown.textContent = String(remaining);

                    const timer = window.setInterval(function () {
                        remaining -= 1;
                        resendCountdown.textContent = String(remaining);

                        if (remaining <= 0) {
                            window.clearInterval(timer);
                            resendBtn.disabled = false;
                            resendCountdownWrap.style.display = 'none';
                        }
                    }, 1000);
                });
            }
        });
    </script>
</body>
</html>
