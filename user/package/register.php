<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/package-functions.php';

$base_path = '../../';

$package_id = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
$package = null;

if ($package_id > 0) {
    $raw = getPackageById($conn, $package_id);

    if ($raw) {
        $package = [];
        $package['id'] = $raw['id'] ?? $package_id;
        $package['name'] = $raw['package_name'] ?? 'Gói tập';
        $package['price'] = $raw['price'] ?? 0;
        $package['description'] = $raw['short_description'] ?: ($raw['description'] ?? '');
        $package['duration'] = $raw['duration_months'] ?? null;
        $package['status'] = $raw['status'] ?? 'active';
    }
}

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');

// Prefill values if user is logged in or if there's an existing member linked
$prefill_name = '';
$prefill_phone = '';
$prefill_email = '';
$prefill_dob = '';
$prefill_address = '';

if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $stmtU = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmtU->bind_param('i', $uid);
    $stmtU->execute();
    $urow = $stmtU->get_result()->fetch_assoc();
    $stmtU->close();
    if ($urow) {
        $prefill_name = $urow['full_name'] ?? '';
        $prefill_email = $urow['email'] ?? '';
        $prefill_phone = $urow['phone'] ?? '';
    }

    // Try to find matching members record for more fields (date_of_birth, address)
    $tryPhone = $prefill_phone;
    $tryEmail = $prefill_email;
    if ($tryPhone || $tryEmail) {
        $stmtM = $conn->prepare("SELECT date_of_birth, address FROM members WHERE phone = ? OR email = ? LIMIT 1");
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
    <link rel="stylesheet" href="../includes/assets/css/user.css">
</head>
<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <section class="section-dark package-register-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-5">
                        <span class="section-badge">PACKAGE REGISTRATION</span>
                        <h1 class="packages-title">Đăng ký <span class="accent">Gói tập</span></h1>
                        <p class="packages-subtitle">Điền thông tin để phòng gym liên hệ tư vấn và xác nhận gói tập phù hợp cho bạn.</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">Đăng ký gói tập thành công. Phòng gym sẽ liên hệ với bạn sớm.</div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="package-card package-card-modern mb-4">
                        <h3 class="package-name mb-3">Thông tin gói tập</h3>

                        <?php if ($package): ?>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-box"></i><span><strong>Gói:</strong> <?php echo htmlspecialchars($package['name']); ?></span></div></div>
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-cash-stack"></i><span><strong>Giá:</strong> <?php echo number_format((float) $package['price'], 0, ',', '.'); ?>đ</span></div></div>
                                <div class="col-md-6"><div class="package-meta-item"><i class="bi bi-calendar-range"></i><span><strong>Thời hạn:</strong> <?php echo htmlspecialchars($package['duration'] ?? 'Linh hoạt'); ?></span></div></div>
                                <div class="col-12"><p class="package-description mb-0"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p></div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Không tìm thấy gói tập. Vui lòng quay lại trang Packages và chọn lại.</div>
                        <?php endif; ?>
                    </div>

                    <div class="package-card package-card-modern">
                        <h3 class="package-name mb-4">Thông tin đăng ký</h3>
                       <form action="<?php echo $base_path; ?>php/package-registrations/submit-registration.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="package_id" value="<?php echo (int) $package_id; ?>">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Họ và tên</label><input type="text" name="full_name" class="form-control" required maxlength="100" placeholder="Nhập họ và tên" value="<?php echo htmlspecialchars($prefill_name); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Số điện thoại</label><input type="text" name="phone" class="form-control" required maxlength="20" placeholder="Nhập số điện thoại" value="<?php echo htmlspecialchars($prefill_phone); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="120" placeholder="Nhập email (nếu có)" value="<?php echo htmlspecialchars($prefill_email); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($prefill_dob); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Địa chỉ</label><input type="text" name="address" class="form-control" maxlength="190" placeholder="Nhập địa chỉ" value="<?php echo htmlspecialchars($prefill_address); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Mã gói</label><input type="text" class="form-control" value="<?php echo $package ? ('#' . $package['id'] . ' - ' . $package['name']) : ''; ?>" readonly></div>
                                <div class="col-12"><label class="form-label text-white">Ghi chú</label><textarea name="note" class="form-control" rows="4" placeholder="Ví dụ: muốn tập tăng cơ, giảm mỡ, cần tư vấn PT..."></textarea></div>
                                <div class="col-12 d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-hero-primary px-4"><i class="bi bi-send me-2"></i>Gửi đăng ký</button>
                                    <a href="<?php echo $base_path; ?>user/package/index.php" class="btn btn-user-outline px-4">Quay lại gói tập</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            const phoneInput = document.querySelector('input[name="phone"]');
            const emailInput = document.querySelector('input[name="email"]');
            const fullNameInput = document.querySelector('input[name="full_name"]');
            const dobInput = document.querySelector('input[name="date_of_birth"]');
            const addressInput = document.querySelector('input[name="address"]');
            const form = document.querySelector('form[action*="submit-registration.php"]');
            const submitBtn = form.querySelector('button[type="submit"]');

            const DEBOUNCE_MS = 600;
            let debounceTimer = null;
            let ongoingFetch = null;

            async function fetchAndFill() {
                if (ongoingFetch) return ongoingFetch;
                ongoingFetch = (async () => {
                    const phone = phoneInput.value.trim();
                    const email = emailInput.value.trim();
                    if (!phone && !email) { ongoingFetch = null; return null; }

                    showStatus('Đang kiểm tra hồ sơ...', 'info');

                    const fd = new FormData();
                    fd.append('phone', phone);
                    fd.append('email', email);
                    try {
                        const resp = await fetch('../../php/members/find-by-contact.php', { method: 'POST', body: fd });
                        const data = await resp.json();
                        if (data && data.success && data.member) {
                            const m = data.member;
                            if ((!fullNameInput.value || fullNameInput.value.trim() === '') && m.full_name) fullNameInput.value = m.full_name;
                            if ((!dobInput.value || dobInput.value.trim() === '') && m.date_of_birth) dobInput.value = m.date_of_birth;
                            if ((!addressInput.value || addressInput.value.trim() === '') && m.address) addressInput.value = m.address;
                            if ((!emailInput.value || emailInput.value.trim() === '') && m.email) emailInput.value = m.email;
                            if ((!phoneInput.value || phoneInput.value.trim() === '') && m.phone) phoneInput.value = m.phone;
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

            form.addEventListener('submit', async function(e){
                const needsFetch = (fullNameInput.value.trim() === '' || dobInput.value.trim() === '' || addressInput.value.trim() === '') && (phoneInput.value.trim() !== '' || emailInput.value.trim() !== '');
                if (!needsFetch) return; // allow normal submit
                e.preventDefault();
                submitBtn.disabled = true;
                try {
                    await fetchAndFill();
                } catch (err) {
                    // ignore, still ask user
                } finally {
                    submitBtn.disabled = false;
                }

                const confirmed = window.confirm('Xác nhận gửi đăng ký gói tập? Hãy kiểm tra lại thông tin trước khi gửi.');
                if (confirmed) {
                    form.submit();
                }
            });

            function showStatus(text, type) {
                let el = document.getElementById('autofill-status');
                if (!el) {
                    el = document.createElement('div'); el.id = 'autofill-status';
                    el.style.marginTop = '10px'; el.style.padding = '8px 12px'; el.style.borderRadius = '6px';
                    el.style.display = 'inline-block'; el.style.fontSize = '14px';
                    form.parentNode.insertBefore(el, form.nextSibling);
                }
                el.textContent = text;
                if (type === 'success') { el.style.background = '#d1fae5'; el.style.color = '#065f46'; }
                else if (type === 'danger') { el.style.background = '#fee2e2'; el.style.color = '#991b1b'; }
                else { el.style.background = '#e2e8f0'; el.style.color = '#0f172a'; }
                setTimeout(()=>{ if (el) el.style.opacity = '1'; }, 10);
            }
        })();
    </script>
</body>
</html>
