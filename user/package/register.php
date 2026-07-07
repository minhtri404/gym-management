<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/package-functions.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_vn($amount)
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function package_duration_text($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        $days = (int)($package['duration_days'] ?? 7);
        return 'Dùng thử: ' . $days . ' ngày';
    }

    $duration = (int)($package['duration_months'] ?? 0);

    if ($duration <= 0) {
        return 'Thời hạn linh hoạt';
    }

    return 'Thời hạn: ' . $duration . ' tháng';
}

function package_duration_label($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        $days = (int)($package['duration_days'] ?? 7);
        return $days . ' ngày dùng thử';
    }

    $duration = (int)($package['duration_months'] ?? $package['duration'] ?? 0);

    if ($duration <= 0) {
        return 'Linh hoạt';
    }

    return $duration . ' tháng';
}

function package_price_text($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        return 'Miễn phí';
    }

    return money_vn($package['price'] ?? 0);
}

function package_button_text($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        return 'Dùng thử 7 ngày';
    }

    return 'Đăng ký gói này';
}
function is_free_trial_package($package)
{
    return (($package['package_type'] ?? 'paid') === 'free_trial');
}
function package_registration_badge($price)
{
    $price = (float) $price;

    if ($price <= 400000) {
        return 'Khởi đầu';
    }

    if ($price <= 1000000) {
        return 'Phổ biến';
    }

    return 'Nâng cao';
}

$package_id = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
$package = null;

if ($package_id > 0) {
    $raw = getPackageById($conn, $package_id);

    if ($raw && ($raw['status'] ?? '') === 'active') {
        $package = [
            'id' => $raw['id'] ?? $package_id,
            'name' => $raw['package_name'] ?? 'Gói tập',
            'price' => $raw['price'] ?? 0,
            'description' => $raw['short_description'] ?: ($raw['description'] ?? ''),
            'duration' => $raw['duration_months'] ?? null,
            'duration_months' => $raw['duration_months'] ?? null,
            'duration_days' => $raw['duration_days'] ?? null,
            'package_type' => $raw['package_type'] ?? 'paid',
            'trial_once_per_user' => $raw['trial_once_per_user'] ?? 0,
            'status' => $raw['status'] ?? 'active',
            'suitable_for' => $raw['suitable_for'] ?? '',
            'image' => $raw['image'] ?? '',
        ];
    }
}

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');
$is_logged_in = !empty($_SESSION['user_id']);
$package_image_url = $package ? getPackageImageUrl($package, $base_path, max(0, ((int) ($package['id'] ?? 1)) - 1)) : '';

$prefill_name = '';
$prefill_phone = '';
$prefill_email = '';

if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $stmtU = $conn->prepare('SELECT full_name, email, phone FROM users WHERE id = ? LIMIT 1');
    $stmtU->bind_param('i', $uid);
    $stmtU->execute();
    $urow = $stmtU->get_result()->fetch_assoc();
    $stmtU->close();

    if ($urow) {
        $prefill_name = $urow['full_name'] ?? '';
        $prefill_email = $urow['email'] ?? '';
        $prefill_phone = $urow['phone'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký gói tập - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=package-wizard-1">
</head>

<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="package-wizard-page">
        <div class="container">
            <div class="package-wizard-shell">
                <div class="wizard-brand-row">
                    <a href="<?php echo $base_path; ?>user/home" class="wizard-logo">
                        <span class="wizard-logo-the">THE</span>
                        <span class="wizard-logo-main">FLEXZONE</span>
                        <span class="wizard-logo-sub">new way to fit</span>
                    </a>

                    <div class="wizard-steps" aria-label="Tiến trình đăng ký">
                        <button class="wizard-step-dot active" type="button" data-step-jump="1">1</button>
                        <span></span>
                        <button class="wizard-step-dot" type="button" data-step-jump="2">2</button>
                        <span></span>
                        <button class="wizard-step-dot" type="button" data-step-jump="3">3</button>
                        <span></span>
                        <button class="wizard-step-dot" type="button" data-step-jump="4">4</button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success wizard-alert">
                        Đăng ký gói tập thành công. FLEXZONE sẽ liên hệ xác nhận trong thời gian sớm nhất.
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger wizard-alert"><?php echo h($error); ?></div>
                <?php endif; ?>

                <?php if (!$package): ?>
                    <section class="wizard-card">
                        <h1>Không tìm thấy gói tập</h1>
                        <p>Vui lòng quay lại trang Packages và chọn lại gói tập bạn muốn đăng ký.</p>
                        <a href="<?php echo $base_path; ?>user/package/index" class="wizard-primary-btn">Chọn gói tập</a>
                    </section>
                <?php else: ?>
                    <section class="wizard-panel is-active" data-step-panel="1">
                        <div class="wizard-package-grid">
                            <div class="wizard-package-media">
                                <?php if ($package_image_url !== ''): ?>
                                    <img src="<?php echo h($package_image_url); ?>" alt="<?php echo h($package['name']); ?>">
                                <?php else: ?>
                                    <div class="wizard-media-fallback"><i class="bi bi-building"></i></div>
                                <?php endif; ?>
                                <button class="wizard-float-btn" type="button" data-next-step="2">Tiếp tục</button>
                            </div>

                            <aside class="wizard-package-aside">
                                <div class="wizard-side-image">
                                    <?php if ($package_image_url !== ''): ?>
                                        <img src="<?php echo h($package_image_url); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="wizard-side-image">
                                    <?php if ($package_image_url !== ''): ?>
                                        <img src="<?php echo h($package_image_url); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                            </aside>
                        </div>

                        <div class="wizard-package-meta">
                            <div>
                                <h1><?php echo h($package['name']); ?></h1>
                                <p class="wizard-location">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    Tất cả chi nhánh FLEXZONE
                                </p>
                            </div>
                            <div class="wizard-price-block">
                                <span>Tham gia ngay, chỉ từ</span>
                                <strong><?php echo h(package_price_text($package)); ?></strong>
                                <em>/ <?php echo h(package_duration_label($package)); ?></em>
                            </div>
                            <div class="wizard-open-block">
                                <span>Mở cửa:</span>
                                <strong>24/7</strong>
                            </div>
                        </div>

                        <div class="wizard-package-note">
                            <span><?php echo h(package_registration_badge($package['price'])); ?></span>
                            <?php echo h($package['description'] !== '' ? $package['description'] : 'Gói tập phù hợp để bắt đầu và duy trì thói quen luyện tập đều đặn.'); ?>
                        </div>
                    </section>

                    <section class="wizard-panel" data-step-panel="2">
                        <div class="wizard-form-card">
                            <h1>Số điện thoại của bạn?</h1>
                            <p>FLEXZONE dùng số này để liên hệ tư vấn và đối chiếu thông tin hội viên.</p>

                            <label class="wizard-phone-input">
                                <span>🇻🇳</span>
                                <input
                                    type="tel"
                                    id="wizardPhone"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    maxlength="11"
                                    placeholder="Nhập số điện thoại"
                                    value="<?php echo h($prefill_phone); ?>">
                            </label>

                            <div class="wizard-action-row">
                                <button class="wizard-secondary-btn" type="button" data-prev-step="1">Quay lại</button>
                                <button class="wizard-primary-btn" type="button" data-next-step="3">Tiếp tục</button>
                            </div>
                        </div>
                    </section>

                    <section class="wizard-panel" data-step-panel="3">
                        <div class="wizard-form-card">
                            <h1>Email nhận mã OTP</h1>
                            <p>Mã xác thực 4 chữ số sẽ được gửi đến email này.</p>

                            <input
                                type="email"
                                id="wizardEmail"
                                class="wizard-text-input"
                                autocomplete="email"
                                maxlength="120"
                                placeholder="Nhập email"
                                value="<?php echo h($prefill_email); ?>">

                            <div class="wizard-action-row">
                                <button class="wizard-secondary-btn" type="button" data-prev-step="2">Quay lại</button>
                                <button class="wizard-primary-btn" type="button" id="sendPackageOtpBtn">
                                    Gửi OTP
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="wizard-panel" data-step-panel="4">
                        <div class="wizard-checkout-grid">
                            <div class="wizard-form-card">
                                <h1>Nhập mã OTP</h1>
                                <p>
                                    Nhập mã gồm 4 chữ số được gửi đến
                                    <strong id="otpEmailLabel"><?php echo h($prefill_email !== '' ? $prefill_email : 'email của bạn'); ?></strong>.
                                </p>

                                <div class="wizard-otp-inputs">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                        <input type="text" inputmode="numeric" maxlength="1" class="wizard-otp-digit" data-otp-index="<?php echo $i; ?>">
                                    <?php endfor; ?>
                                </div>

                                <div class="wizard-action-row">
                                    <button class="wizard-secondary-btn" type="button" data-prev-step="3">Quay lại</button>
                                    <button class="wizard-primary-btn" type="button" id="verifyPackageOtpBtn">
                                        Xác nhận OTP
                                    </button>
                                </div>

                                <button class="wizard-link-btn" type="button" id="resendPackageOtpBtn">Gửi lại mã</button>
                            </div>

                            <form class="wizard-payment-card" id="packageWizardForm" action="<?php echo $base_path; ?>php/package-registrations/submit-registration.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="package_id" value="<?php echo (int) $package_id; ?>">
                                <input type="hidden" name="full_name" id="wizardFullName" value="<?php echo h($prefill_name); ?>">
                                <input type="hidden" name="phone" id="wizardPhoneHidden" value="<?php echo h($prefill_phone); ?>">
                                <input type="hidden" name="email" id="wizardEmailHidden" value="<?php echo h($prefill_email); ?>">
                                <input
                                    type="hidden"
                                    name="payment_method"
                                    id="wizardPaymentMethod"
                                    value="<?php echo is_free_trial_package($package) ? 'free_trial' : 'vnpay'; ?>">
                                <h2>Chi tiết thanh toán</h2>
                                <div class="wizard-payment-lines">
                                    <div><span>Club</span><strong>Tất cả chi nhánh</strong></div>
                                    <div>
                                        <span><?php echo h(package_duration_label($package)); ?></span>
                                        <strong><?php echo h(package_price_text($package)); ?></strong>
                                    </div>

                                    <div>
                                        <span>Phí hội viên</span>
                                        <strong>Miễn phí</strong>
                                    </div>

                                    <div class="total">
                                        <span>TỔNG</span>
                                        <strong><?php echo h(package_price_text($package)); ?></strong>
                                    </div>
                                </div>

                                <label class="wizard-name-field">
                                    <span>Họ và tên</span>
                                    <input type="text" id="wizardNameInput" maxlength="100" placeholder="Nhập họ tên để lưu đăng ký" value="<?php echo h($prefill_name); ?>">
                                </label>

                                <p class="wizard-terms">
                                    Bằng cách nhấp vào Xác nhận, bạn đồng ý với điều khoản sử dụng của FLEXZONE.
                                </p>

                                <?php if (is_free_trial_package($package)): ?>
                                    <h3>Phương thức thanh toán</h3>
                                    <div class="wizard-payment-methods">
                                        <button type="button" class="wizard-method-card selected" data-payment-method="free_trial">
                                            <i class="bi bi-gift"></i>
                                            Gói dùng thử miễn phí
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <h3>Chọn phương thức thanh toán</h3>
                                    <div class="wizard-payment-methods">
                                        <button type="button" class="wizard-method-card selected" data-payment-method="vnpay">
                                            <i class="bi bi-qr-code"></i>
                                            INTERNET BANKING - QR
                                        </button>
                                        <button type="button" class="wizard-method-card" data-payment-method="cash">
                                            <i class="bi bi-shop"></i>
                                            Thanh toán tại phòng
                                        </button>
                                    </div>
                                <?php endif; ?>

                           <?php if (!$is_logged_in && !is_free_trial_package($package)): ?>
    <div class="wizard-login-note">
        <i class="bi bi-info-circle"></i>
        Nếu chọn VNPAY, bạn cần đăng nhập trước khi chuyển sang cổng thanh toán.
    </div>
<?php endif; ?>

<?php if (!$is_logged_in && is_free_trial_package($package)): ?>
    <div class="wizard-login-note">
        <i class="bi bi-info-circle"></i>
        Bạn cần đăng nhập để nhận gói dùng thử 7 ngày.
    </div>
<?php endif; ?>

                                <div class="wizard-payment-actions">
                                    <button class="wizard-secondary-btn" type="button" data-prev-step="3">Quay lại</button>
                                    <button class="wizard-primary-btn" type="submit" id="finalSubmitBtn" disabled>Xác nhận</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <div class="wizard-status" id="wizardStatus" aria-live="polite"></div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
            const packageId = <?php echo (int) $package_id; ?>;
            const panels = Array.from(document.querySelectorAll('[data-step-panel]'));
            const dots = Array.from(document.querySelectorAll('.wizard-step-dot'));
            const statusBox = document.getElementById('wizardStatus');
            const phoneInput = document.getElementById('wizardPhone');
            const emailInput = document.getElementById('wizardEmail');
            const emailLabel = document.getElementById('otpEmailLabel');
            const phoneHidden = document.getElementById('wizardPhoneHidden');
            const emailHidden = document.getElementById('wizardEmailHidden');
            const nameHidden = document.getElementById('wizardFullName');
            const nameInput = document.getElementById('wizardNameInput');
            const sendOtpBtn = document.getElementById('sendPackageOtpBtn');
            const resendOtpBtn = document.getElementById('resendPackageOtpBtn');
            const verifyOtpBtn = document.getElementById('verifyPackageOtpBtn');
            const otpInputs = Array.from(document.querySelectorAll('.wizard-otp-digit'));
            const paymentMethodInput = document.getElementById('wizardPaymentMethod');
            const methodCards = Array.from(document.querySelectorAll('.wizard-method-card'));
            const finalSubmitBtn = document.getElementById('finalSubmitBtn');
            const form = document.getElementById('packageWizardForm');

            let currentStep = 1;
            let otpVerified = false;

            function setStatus(message, type = 'info') {
                if (!statusBox) return;
                statusBox.textContent = message || '';
                statusBox.dataset.type = type;
                statusBox.classList.toggle('is-visible', Boolean(message));
            }

            function cleanPhone() {
                return (phoneInput ? phoneInput.value : '').replace(/\D+/g, '');
            }

            function cleanEmail() {
                return (emailInput ? emailInput.value : '').trim();
            }

            function isValidPhone(phone) {
                return /^0\d{9,10}$/.test(phone);
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function showStep(step) {
                currentStep = step;
                panels.forEach(function(panel) {
                    panel.classList.toggle('is-active', Number(panel.dataset.stepPanel) === step);
                });
                dots.forEach(function(dot) {
                    const dotStep = Number(dot.dataset.stepJump);
                    dot.classList.toggle('active', dotStep === step);
                    dot.classList.toggle('done', dotStep < step);
                });
                setStatus('');
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function syncHiddenFields() {
                const phone = cleanPhone();
                const email = cleanEmail();
                if (phoneHidden) phoneHidden.value = phone;
                if (emailHidden) emailHidden.value = email;
                if (emailLabel && email) emailLabel.textContent = email;
                if (nameHidden && nameInput) {
                    nameHidden.value = nameInput.value.trim();
                }
            }

            async function sendOtp(button) {
                const phone = cleanPhone();
                const email = cleanEmail();

                if (!isValidPhone(phone)) {
                    setStatus('Vui lòng nhập số điện thoại Việt Nam hợp lệ, ví dụ 0364818531.', 'danger');
                    showStep(2);
                    return false;
                }

                if (!isValidEmail(email)) {
                    setStatus('Vui lòng nhập email hợp lệ để nhận mã OTP.', 'danger');
                    return false;
                }

                syncHiddenFields();
                otpVerified = false;
                if (finalSubmitBtn) finalSubmitBtn.disabled = true;

                const originalText = button ? button.textContent : '';
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Đang gửi...';
                }

                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('package_id', String(packageId));
                fd.append('phone', phone);
                fd.append('email', email);

                try {
                    const response = await fetch('../../php/package-registrations/send-otp.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const data = await response.json();

                    if (!data.success) {
                        setStatus(data.message || 'Không gửi được OTP. Vui lòng thử lại.', 'danger');
                        return false;
                    }

                    setStatus(data.message || 'OTP đã được gửi đến email của bạn.', 'success');
                    showStep(4);
                    otpInputs.forEach(function(input) {
                        input.value = '';
                    });
                    if (otpInputs[0]) otpInputs[0].focus();
                    return true;
                } catch (error) {
                    console.error(error);
                    setStatus('Lỗi kết nối khi gửi OTP. Vui lòng thử lại.', 'danger');
                    return false;
                } finally {
                    if (button) {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                }
            }

            function getOtpCode() {
                return otpInputs.map(function(input) {
                    return input.value.replace(/\D+/g, '');
                }).join('');
            }

            async function verifyOtp() {
                const code = getOtpCode();
                const phone = cleanPhone();
                const email = cleanEmail();

                if (!/^\d{4}$/.test(code)) {
                    setStatus('Vui lòng nhập đủ 4 chữ số OTP.', 'danger');
                    return;
                }

                syncHiddenFields();
                verifyOtpBtn.disabled = true;
                verifyOtpBtn.textContent = 'Đang xác nhận...';

                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('package_id', String(packageId));
                fd.append('phone', phone);
                fd.append('email', email);
                fd.append('otp', code);

                try {
                    const response = await fetch('../../php/package-registrations/verify-otp.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const data = await response.json();

                    if (!data.success) {
                        setStatus(data.message || 'OTP không hợp lệ hoặc đã hết hạn.', 'danger');
                        return;
                    }

                    otpVerified = true;
                    if (finalSubmitBtn) finalSubmitBtn.disabled = false;
                    setStatus('Xác thực OTP thành công. Bạn có thể xác nhận đăng ký.', 'success');
                } catch (error) {
                    console.error(error);
                    setStatus('Lỗi kết nối khi xác nhận OTP. Vui lòng thử lại.', 'danger');
                } finally {
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.textContent = 'Xác nhận OTP';
                }
            }

            document.querySelectorAll('[data-next-step]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const next = Number(button.dataset.nextStep);
                    if (next === 3 && !isValidPhone(cleanPhone())) {
                        setStatus('Vui lòng nhập số điện thoại Việt Nam hợp lệ trước khi tiếp tục.', 'danger');
                        return;
                    }
                    syncHiddenFields();
                    showStep(next);
                });
            });

            document.querySelectorAll('[data-prev-step]').forEach(function(button) {
                button.addEventListener('click', function() {
                    showStep(Number(button.dataset.prevStep));
                });
            });

            dots.forEach(function(dot) {
                dot.addEventListener('click', function() {
                    const target = Number(dot.dataset.stepJump);
                    if (target > currentStep + 1) return;
                    showStep(target);
                });
            });

            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    phoneInput.value = cleanPhone().slice(0, 11);
                    syncHiddenFields();
                });
            }

            if (emailInput) {
                emailInput.addEventListener('input', syncHiddenFields);
            }

            if (nameInput) {
                nameInput.addEventListener('input', syncHiddenFields);
            }

            if (sendOtpBtn) {
                sendOtpBtn.addEventListener('click', function() {
                    sendOtp(sendOtpBtn);
                });
            }

            if (resendOtpBtn) {
                resendOtpBtn.addEventListener('click', function() {
                    sendOtp(resendOtpBtn);
                });
            }

            otpInputs.forEach(function(input, index) {
                input.addEventListener('input', function() {
                    input.value = input.value.replace(/\D+/g, '').slice(0, 1);
                    if (input.value && otpInputs[index + 1]) {
                        otpInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Backspace' && !input.value && otpInputs[index - 1]) {
                        otpInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function(event) {
                    event.preventDefault();
                    const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D+/g, '').slice(0, 4);
                    pasted.split('').forEach(function(digit, offset) {
                        if (otpInputs[offset]) otpInputs[offset].value = digit;
                    });
                    if (otpInputs[Math.min(pasted.length, 3)]) {
                        otpInputs[Math.min(pasted.length, 3)].focus();
                    }
                });
            });

            if (verifyOtpBtn) {
                verifyOtpBtn.addEventListener('click', verifyOtp);
            }

            methodCards.forEach(function(card) {
                card.addEventListener('click', function() {
                    methodCards.forEach(function(item) {
                        item.classList.remove('selected');
                    });
                    card.classList.add('selected');
                    if (paymentMethodInput) {
                        paymentMethodInput.value = card.dataset.paymentMethod || 'cash';
                    }
                });
            });

            if (form) {
                form.addEventListener('submit', function(event) {
                    syncHiddenFields();

                    if (!otpVerified) {
                        event.preventDefault();
                        setStatus('Vui lòng xác nhận OTP trước khi hoàn tất đăng ký.', 'danger');
                        return;
                    }

                    if (nameInput && nameInput.value.trim() === '') {
                        nameInput.value = 'Khách hàng ' + cleanPhone();
                        syncHiddenFields();
                    }

                    finalSubmitBtn.disabled = true;
                    finalSubmitBtn.textContent = 'Đang xử lý...';
                });
            }
        })();
    </script>
</body>

</html>
