<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/package-functions.php';
require_once __DIR__ . '/../../includes/recaptcha.php';

$base_path = '../../';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_vn($amount)
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function package_duration_label($months)
{
    $months = (int) $months;

    if ($months <= 0) {
        return 'Linh hoạt';
    }

    if ($months === 1) {
        return '1 tháng';
    }

    return $months . ' tháng';
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

    if ($raw) {
        $package = [
            'id' => $raw['id'] ?? $package_id,
            'name' => $raw['package_name'] ?? 'Gói tập',
            'price' => $raw['price'] ?? 0,
            'description' => $raw['short_description'] ?: ($raw['description'] ?? ''),
            'duration' => $raw['duration_months'] ?? null,
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
$prefill_dob = '';
$prefill_address = '';

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

    $tryPhone = $prefill_phone;
    $tryEmail = $prefill_email;

    if ($tryPhone || $tryEmail) {
        $stmtM = $conn->prepare('SELECT date_of_birth, address FROM members WHERE phone = ? OR email = ? LIMIT 1');
        $stmtM->bind_param('ss', $tryPhone, $tryEmail);
        $stmtM->execute();
        $mrow = $stmtM->get_result()->fetch_assoc();
        $stmtM->close();

        if ($mrow) {
            $prefill_dob = $mrow['date_of_birth'] ?? '';
            $prefill_address = $mrow['address'] ?? '';
        }
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
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="user-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="section-dark package-register-section register-package-page">
    <div class="container">
        <div class="register-page-shell">
            <div class="text-center register-page-heading">
                <span class="section-badge">PACKAGE REGISTRATION</span>
                <h1 class="packages-title">Đăng ký <span class="accent">Gói tập</span></h1>
                <p class="packages-subtitle">Điền đầy đủ thông tin để hoàn tất đăng ký và chọn một trong hai phương thức thanh toán: VNPAY hoặc tại phòng.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success register-alert">
                    Đăng ký gói tập thành công. Phòng gym sẽ liên hệ với bạn sớm để xác nhận.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger register-alert"><?php echo h($error); ?></div>
            <?php endif; ?>

            <div class="register-top-card">
                <div class="register-card-heading">
                    <div class="register-card-icon"><i class="bi bi-tags-fill"></i></div>
                    <div>
                        <h2>Thông tin gói tập</h2>
                        <p>Chi tiết gói tập bạn đã chọn</p>
                    </div>
                </div>

                <?php if ($package): ?>
                    <div class="register-package-summary">
                        <div class="register-package-hero">
                            <div class="register-package-visual">
                                <?php if ($package_image_url !== ''): ?>
                                    <img src="<?php echo h($package_image_url); ?>" alt="<?php echo h($package['name'] ?? 'Ảnh gói tập'); ?>">
                                <?php else: ?>
                                    <i class="bi bi-barbell"></i>
                                <?php endif; ?>
                            </div>

                            <div class="register-package-copy">
                                <div class="register-package-title-row">
                                    <h3><?php echo h($package['name']); ?></h3>
                                    <span class="register-package-badge"><?php echo h(package_registration_badge($package['price'])); ?></span>
                                </div>
                                <p><?php echo h($package['description'] !== '' ? $package['description'] : 'Gói tập phù hợp để bắt đầu và duy trì thói quen luyện tập đều đặn.'); ?></p>

                                <div class="register-package-features">
                                    <div>
                                        <span>Thời hạn</span>
                                        <strong><?php echo h(package_duration_label($package['duration'])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Giá gói</span>
                                        <strong><?php echo h(money_vn($package['price'])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Phù hợp với</span>
                                        <strong><?php echo h($package['suitable_for'] !== '' ? $package['suitable_for'] : 'Người mới bắt đầu'); ?></strong>
                                    </div>
                                    <div>
                                        <span>Hình thức tập</span>
                                        <strong>Không giới hạn giờ hoạt động</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="register-package-price-panel">
                            <span>Giá gói</span>
                            <strong><?php echo h(money_vn($package['price'])); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">Không tìm thấy gói tập. Vui lòng quay lại trang Packages và chọn lại.</div>
                <?php endif; ?>
            </div>

            <div class="register-main-card">
                <div class="register-card-heading">
                    <div class="register-card-icon"><i class="bi bi-clipboard-check-fill"></i></div>
                    <div>
                        <h2>Thông tin đăng ký</h2>
                        <p>Vui lòng điền đầy đủ thông tin để hoàn tất đăng ký</p>
                    </div>
                </div>

                <div class="register-content-grid">
                    <div class="register-form-panel">
                        <form action="<?php echo $base_path; ?>php/package-registrations/submit-registration.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="package_id" value="<?php echo (int) $package_id; ?>">
                            <input type="hidden" name="payment_method" value="cash">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" name="full_name" class="form-control register-input" required maxlength="100" placeholder="Nhập họ và tên" value="<?php echo h($prefill_name); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="text" name="phone" class="form-control register-input" required maxlength="20" placeholder="Nhập số điện thoại" value="<?php echo h($prefill_phone); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control register-input" maxlength="120" placeholder="Nhập email (nếu có)" value="<?php echo h($prefill_email); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" name="date_of_birth" class="form-control register-input" value="<?php echo h($prefill_dob); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Địa chỉ</label>
                                    <input type="text" name="address" class="form-control register-input" maxlength="190" placeholder="Nhập địa chỉ" value="<?php echo h($prefill_address); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mã gói</label>
                                    <input type="text" class="form-control register-input" value="<?php echo $package ? ('#' . $package['id'] . ' - ' . $package['name']) : ''; ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea name="note" class="form-control register-input register-textarea" rows="4" maxlength="300" placeholder="Ví dụ: muốn tập tăng cơ, giảm mỡ, cần tư vấn PT..."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Google reCAPTCHA</label>
                                    <div class="package-captcha-box">
                                        <div class="package-captcha-note mb-3">
                                            Hoàn thành Google reCAPTCHA để xác nhận đây là yêu cầu đăng ký thật và giúp hệ thống hạn chế spam.
                                        </div>
                                        <div class="g-recaptcha" data-sitekey="<?php echo h(get_recaptcha_site_key()); ?>"></div>
                                    </div>
                                </div>
                                <div class="col-12 d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center">
                                    <a href="<?php echo $base_path; ?>user/package/index.php" class="btn register-back-btn">
                                        <i class="bi bi-arrow-left me-2"></i>Quay lại gói tập
                                    </a>
                                    <button type="button" class="btn register-submit-btn" id="openPaymentMethodModal">
                                        <i class="bi bi-send me-2"></i>Gửi đăng ký
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <aside class="register-side-panel">
                        <div class="register-side-card">
                            <h3><i class="bi bi-info-circle me-2"></i>Thông tin nhanh</h3>

                            <div class="register-side-list">
                                <div><span>Gói tập</span><strong><?php echo h($package['name'] ?? 'Chưa chọn'); ?></strong></div>
                                <div><span>Thời hạn</span><strong><?php echo h(package_duration_label($package['duration'] ?? 0)); ?></strong></div>
                                <div><span>Giá</span><strong class="price"><?php echo h(money_vn($package['price'] ?? 0)); ?></strong></div>
                            </div>

                            <div class="register-side-divider"></div>

                            <h4>Phương thức thanh toán</h4>
                            <div class="register-payment-preview">
                                <div class="register-payment-preview-item">
                                    <span class="icon"><i class="bi bi-shop"></i></span>
                                    <span>Tại phòng</span>
                                </div>
                                <div class="register-payment-preview-item">
                                    <span class="icon"><i class="bi bi-credit-card"></i></span>
                                    <span>VNPAY</span>
                                </div>
                            </div>

                            <div class="register-side-divider"></div>

                            <h4>Vì sao chọn chúng tôi?</h4>
                            <ul class="register-why-list">
                                <li><i class="bi bi-check-circle"></i><span>Huấn luyện viên chuyên nghiệp</span></li>
                                <li><i class="bi bi-check-circle"></i><span>Phòng tập hiện đại, sạch sẽ</span></li>
                                <li><i class="bi bi-check-circle"></i><span>Lộ trình cá nhân hóa theo mục tiêu</span></li>
                                <li><i class="bi bi-check-circle"></i><span>Hỗ trợ nhanh sau khi đăng ký</span></li>
                            </ul>
                        </div>
                    </aside>
                </div>

                <div class="register-security-note">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <strong>Thông tin của bạn được bảo mật tuyệt đối.</strong>
                        <span>Đội ngũ của chúng tôi sẽ liên hệ xác nhận trong vòng 24 giờ qua số điện thoại hoặc email bạn đã cung cấp.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content payment-choice-modal">
            <div class="modal-header border-0 align-items-start">
                <div class="payment-modal-heading">
                    <span class="payment-modal-kicker">THANH TOÁN</span>
                    <h2 class="payment-modal-title mb-2" id="paymentMethodModalLabel">Chọn phương thức thanh toán</h2>
                    <p class="payment-modal-subtitle mb-0">Xác nhận cách thanh toán trước khi gửi đăng ký gói tập.</p>
                </div>
                <button type="button" class="btn-close payment-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="payment-summary-box mb-4">
                    <div class="payment-summary-main">
                        <div class="payment-summary-label">Gói đã chọn</div>
                        <div class="payment-summary-name"><?php echo $package ? h($package['name']) : 'Gói tập'; ?></div>
                        <div class="payment-summary-meta">
                            <span><i class="bi bi-calendar-range"></i><?php echo $package && !empty($package['duration']) ? (int) $package['duration'] . ' tháng' : 'Linh hoạt'; ?></span>
                            <span><i class="bi bi-shield-check"></i>Xác nhận nhanh</span>
                        </div>
                    </div>
                    <div class="payment-summary-total">
                        <div class="payment-summary-label">Tổng thanh toán</div>
                        <div class="payment-summary-price"><?php echo $package ? number_format((float) $package['price'], 0, ',', '.') : '0'; ?>đ</div>
                        <div class="payment-summary-note">Chọn 1 trong 2 phương thức: tại phòng hoặc VNPAY</div>
                    </div>
                </div>

                <div class="row g-4 payment-options-grid">
                    <div class="col-md-6">
                        <button type="button" class="payment-option-card payment-option-card-cash w-100" data-payment-method="cash">
                            <span class="payment-option-badge">Tư vấn trực tiếp tại quầy</span>
                            <span class="payment-option-icon payment-option-icon-cash"><i class="bi bi-shop-window"></i></span>
                            <span class="payment-option-title">Thanh toán tại phòng</span>
                            <span class="payment-option-text">Gửi đăng ký trước để giữ chỗ, sau đó thanh toán trực tiếp khi đến phòng gym.</span>
                            <span class="payment-option-footer">
                                <span><i class="bi bi-check2-circle"></i>Không cần thanh toán ngay</span>
                                <span><i class="bi bi-people"></i>Phù hợp nếu cần tư vấn thêm</span>
                            </span>
                            <span class="payment-option-cta">Chá»n phÆ°Æ¡ng thá»©c nÃ y</span>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="payment-option-card payment-option-card-vnpay w-100" data-payment-method="vnpay">
                            <span class="payment-option-badge payment-option-badge-highlight">Thanh toán online ngay</span>
                            <span class="payment-option-icon payment-option-icon-vnpay" aria-hidden="true">
                                <span class="vnpay-mark">
                                    <span class="vnpay-mark-blue"></span>
                                    <span class="vnpay-mark-red"></span>
                                </span>
                                <span class="vnpay-wordmark">VNPAY</span>
                            </span>
                            <span class="payment-option-title">Thanh toán qua VNPAY</span>
                            <span class="payment-option-text">Chuyển sang cổng thanh toán VNPAY an toàn để hoàn tất đăng ký ngay trên hệ thống.</span>
                            <span class="payment-option-footer">
                                <span><i class="bi bi-lightning-charge"></i>Xử lý nhanh</span>
                                <span><i class="bi bi-shield-lock"></i>Bảo mật giao dịch</span>
                            </span>
                            <span class="payment-option-cta payment-option-cta-primary">Thanh toÃ¡n vá»›i VNPAY</span>
                        </button>
                    </div>
                </div>

                <?php if (!$is_logged_in): ?>
                    <div class="payment-login-note mt-4 mb-0">
                        <i class="bi bi-info-circle"></i>
                        <span>Để thanh toán qua VNPAY, bạn sẽ được yêu cầu đăng nhập trước khi chuyển sang cổng thanh toán.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const phoneInput = document.querySelector('input[name="phone"]');
        const emailInput = document.querySelector('input[name="email"]');
        const fullNameInput = document.querySelector('input[name="full_name"]');
        const dobInput = document.querySelector('input[name="date_of_birth"]');
        const addressInput = document.querySelector('input[name="address"]');
        const form = document.querySelector('form[action*="submit-registration.php"]');
        const submitBtn = document.getElementById('openPaymentMethodModal');
        const paymentMethodInput = form.querySelector('input[name="payment_method"]');
        const paymentMethodModalElement = document.getElementById('paymentMethodModal');
        const paymentMethodModal = paymentMethodModalElement ? new bootstrap.Modal(paymentMethodModalElement) : null;
        const paymentMethodButtons = paymentMethodModalElement ? paymentMethodModalElement.querySelectorAll('[data-payment-method]') : [];

        const DEBOUNCE_MS = 600;
        let debounceTimer = null;
        let ongoingFetch = null;
        let paymentSelectionConfirmed = false;

        async function fetchAndFill() {
            if (ongoingFetch) {
                return ongoingFetch;
            }

            ongoingFetch = (async () => {
                const phone = phoneInput.value.trim();
                const email = emailInput.value.trim();

                if (!phone && !email) {
                    ongoingFetch = null;
                    return null;
                }

                showStatus('Đang kiểm tra hồ sơ...', 'info');

                const fd = new FormData();
                fd.append('phone', phone);
                fd.append('email', email);

                try {
                    const resp = await fetch('../../php/members/find-by-contact.php', { method: 'POST', body: fd });
                    const data = await resp.json();

                    if (data && data.success && data.member) {
                        const member = data.member;

                        if ((!fullNameInput.value || fullNameInput.value.trim() === '') && member.full_name) fullNameInput.value = member.full_name;
                        if ((!dobInput.value || dobInput.value.trim() === '') && member.date_of_birth) dobInput.value = member.date_of_birth;
                        if ((!addressInput.value || addressInput.value.trim() === '') && member.address) addressInput.value = member.address;
                        if ((!emailInput.value || emailInput.value.trim() === '') && member.email) emailInput.value = member.email;
                        if ((!phoneInput.value || phoneInput.value.trim() === '') && member.phone) phoneInput.value = member.phone;

                        showStatus('Tự động điền thông tin từ hồ sơ', 'success');
                    } else {
                        showStatus('Không tìm thấy hồ sơ phù hợp', 'info');
                    }

                    ongoingFetch = null;
                    return data;
                } catch (err) {
                    console.error(err);
                    showStatus('Lỗi khi tìm hồ sơ', 'danger');
                    ongoingFetch = null;
                    throw err;
                }
            })();

            return ongoingFetch;
        }

        function scheduleFetch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchAndFill, DEBOUNCE_MS);
        }

        phoneInput.addEventListener('input', scheduleFetch);
        emailInput.addEventListener('input', scheduleFetch);
        phoneInput.addEventListener('blur', fetchAndFill);
        emailInput.addEventListener('blur', fetchAndFill);

        async function prepareBeforeSubmit() {
            const needsFetch =
                (fullNameInput.value.trim() === '' || dobInput.value.trim() === '' || addressInput.value.trim() === '') &&
                (phoneInput.value.trim() !== '' || emailInput.value.trim() !== '');

            if (!needsFetch) {
                return;
            }

            try {
                await fetchAndFill();
            } catch (err) {
                // Allow manual submission if autofill fails.
            }
        }

        submitBtn.addEventListener('click', async function () {
            if (!form.reportValidity()) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Đang kiểm tra';

            try {
                await prepareBeforeSubmit();
                if (paymentMethodModal) {
                    paymentMethodModal.show();
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Gửi đăng ký';
            }
        });

        form.addEventListener('submit', function (event) {
            if (paymentSelectionConfirmed) {
                return;
            }

            event.preventDefault();
            submitBtn.click();
        });

        paymentMethodButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                paymentMethodInput.value = button.getAttribute('data-payment-method') || 'cash';
                paymentSelectionConfirmed = true;
                paymentMethodButtons.forEach(function (item) {
                    item.disabled = true;
                });
                submitBtn.disabled = true;
                form.requestSubmit();
            });
        });

        function showStatus(text, type) {
            let el = document.getElementById('autofill-status');

            if (!el) {
                el = document.createElement('div');
                el.id = 'autofill-status';
                el.className = 'register-autofill-status';
                form.parentNode.insertBefore(el, form.nextSibling);
            }

            el.textContent = text;

            if (type === 'success') {
                el.style.background = '#d1fae5';
                el.style.color = '#065f46';
            } else if (type === 'danger') {
                el.style.background = '#fee2e2';
                el.style.color = '#991b1b';
            } else {
                el.style.background = '#dbeafe';
                el.style.color = '#1e3a8a';
            }
        }
    })();
</script>
</body>
</html>
