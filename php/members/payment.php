<?php
$page_title = 'Thu tiền hội viên';
include __DIR__ . '/../../includes/auth-check.php';

$base_path = '../../admin/';
$root_base_path = '../../';

$member_id = isset($_GET['id']) ? (int) ($_GET['id'] ?? 0) : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? (int) ($_POST['member_id'] ?? 0) : 0;
}

if ($member_id <= 0) {
    header('Location: ' . $base_path . 'members.php');
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money_vn($amount)
{
    return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
}

function parse_vnd_amount($value)
{
    $digits = preg_replace('/\D+/', '', (string) $value);
    return $digits === '' ? 0 : (float) $digits;
}

$stmt_member = $conn->prepare('
    SELECT
        m.id,
        m.full_name,
        m.phone,
        m.email,
        p.package_name,
        p.package_type
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id = ?
    LIMIT 1
');
$stmt_member->bind_param('i', $member_id);
$stmt_member->execute();
$member = $stmt_member->get_result()->fetch_assoc();
$stmt_member->close();

if (!$member) {
    header('Location: ' . $base_path . 'members.php');
    exit;
}

$stmt_history = $conn->prepare('
    SELECT
        h.id,
        h.price,
        h.paid_amount,
        h.remaining_amount,
        h.start_date,
        h.end_date,
        h.note,
        p.package_type
    FROM member_package_history h
    LEFT JOIN packages p ON h.package_id = p.id
    WHERE h.member_id = ?
      AND h.status = "active"
      AND h.remaining_amount > 0
    ORDER BY h.id DESC
    LIMIT 1
');
$stmt_history->bind_param('i', $member_id);
$stmt_history->execute();
$history = $stmt_history->get_result()->fetch_assoc();
$stmt_history->close();

if (!$history || ($history['package_type'] ?? '') === 'free_trial') {
    header('Location: ' . $root_base_path . 'php/members/view-member.php?id=' . $member_id);
    exit;
}

$error = '';
$payment_amount = '';
$remaining_amount = (float) ($history['remaining_amount'] ?? 0);
$paid_amount = (float) ($history['paid_amount'] ?? 0);
$price_amount = (float) ($history['price'] ?? 0);
$total_amount = $price_amount > 0 ? $price_amount : ($paid_amount + $remaining_amount);
$paid_percent = $total_amount > 0 ? min(100, max(0, ($paid_amount / $total_amount) * 100)) : 0;
$default_payment_amount = $remaining_amount;
$payment_methods = [
    'cash' => 'Tiền mặt',
    'transfer' => 'Chuyển khoản',
    'card' => 'Quẹt thẻ',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'CSRF token không hợp lệ.';
    }

    $payment_amount = trim((string) ($_POST['payment_amount'] ?? ''));
    $amount = parse_vnd_amount($payment_amount);
    $payment_method = trim((string) ($_POST['payment_method'] ?? 'cash'));
    $payment_note_extra = trim((string) ($_POST['payment_note'] ?? ''));

    if (!array_key_exists($payment_method, $payment_methods)) {
        $payment_method = 'cash';
    }

    if ($error === '' && $amount <= 0) {
        $error = 'Vui lòng nhập số tiền thu hợp lệ.';
    }

    if ($error === '' && $amount > $remaining_amount) {
        $error = 'Số tiền thu không được lớn hơn số tiền còn nợ.';
    }

    if ($error === '') {
        $history_id = (int) $history['id'];
        $new_paid_amount = $paid_amount + $amount;
        $new_remaining_amount = max(0, $remaining_amount - $amount);
        $note_prefix = trim((string) ($history['note'] ?? ''));
        $admin_name = trim((string) ($_SESSION['admin_full_name'] ?? $_SESSION['user_name'] ?? 'Admin'));
        $payment_note = '[' . date('Y-m-d H:i') . '] Thu tiền: ' . money_vn($amount)
            . ' | Phương thức: ' . $payment_methods[$payment_method]
            . ' | Thu bởi: ' . $admin_name;

        if ($payment_note_extra !== '') {
            $payment_note .= ' | Ghi chú: ' . $payment_note_extra;
        }

        $new_note = $note_prefix !== '' ? $note_prefix . "\n" . $payment_note : $payment_note;

        $conn->begin_transaction();

        try {
            $stmt_update = $conn->prepare('
                UPDATE member_package_history
                SET paid_amount = ?,
                    remaining_amount = ?,
                    note = ?
                WHERE id = ?
            ');
            $stmt_update->bind_param('ddsi', $new_paid_amount, $new_remaining_amount, $new_note, $history_id);

            if (!$stmt_update->execute()) {
                throw new RuntimeException($stmt_update->error);
            }

            $stmt_update->close();
            $conn->commit();

            header('Location: ' . $root_base_path . 'php/members/view-member.php?id=' . $member_id . '&payment=success');
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Không thể cập nhật thanh toán: ' . $exception->getMessage();
        }
    }
}

$initial_amount = $payment_amount !== '' ? parse_vnd_amount($payment_amount) : $default_payment_amount;
$quick_amounts = [
    'Thu đủ' => $remaining_amount,
    '50%' => max(0, floor($remaining_amount / 2 / 1000) * 1000),
    '100K' => min($remaining_amount, 100000),
    '200K' => min($remaining_amount, 200000),
    '500K' => min($remaining_amount, 500000),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($page_title); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $root_base_path; ?>css/style.css">
  <style>
    .payment-page {
      background: #f5f7fb;
      min-height: calc(100vh - 64px);
    }

    .payment-shell {
      max-width: 1180px;
    }

    .payment-hero {
      background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 58%, #0ea5e9 100%);
      border-radius: 18px;
      color: #fff;
      padding: 24px;
      box-shadow: 0 18px 42px rgba(15, 23, 42, 0.18);
    }

    .payment-hero h2 {
      font-weight: 850;
      letter-spacing: -0.03em;
    }

    .payment-hero .btn {
      border: 0;
      font-weight: 700;
    }

    .payment-kpi-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
      margin-top: 16px;
    }

    .payment-kpi {
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 14px;
      padding: 15px;
    }

    .payment-kpi span {
      display: block;
      color: rgba(255, 255, 255, 0.76);
      font-size: 13px;
      margin-bottom: 5px;
    }

    .payment-kpi strong {
      display: block;
      font-size: 20px;
      line-height: 1.2;
    }

    .payment-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) 390px;
      gap: 20px;
      margin-top: 20px;
    }

    .payment-panel {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 16px;
      box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
      padding: 22px;
    }

    .payment-panel-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
    }

    .payment-panel-title h5 {
      font-weight: 850;
      margin: 0;
    }

    .payment-member-card {
      display: grid;
      grid-template-columns: 56px 1fr;
      gap: 14px;
      padding: 16px;
      border-radius: 14px;
      background: #f8fafc;
      border: 1px solid rgba(15, 23, 42, 0.08);
      margin-bottom: 18px;
    }

    .payment-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: #dbeafe;
      color: #1d4ed8;
      font-weight: 900;
      font-size: 22px;
    }

    .payment-detail-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .payment-detail {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      padding: 13px;
      background: #fff;
    }

    .payment-detail span,
    .payment-form-help {
      color: #64748b;
      font-size: 13px;
    }

    .payment-detail strong {
      display: block;
      color: #0f172a;
      margin-top: 3px;
    }

    .payment-amount-input {
      position: relative;
    }

    .payment-amount-input .form-control {
      height: 58px;
      border-radius: 14px;
      font-size: 24px;
      font-weight: 850;
      padding-right: 72px;
      border-color: rgba(14, 165, 233, 0.35);
      background: #f8fbff;
    }

    .payment-amount-input .currency {
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-weight: 800;
    }

    .payment-quick-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 9px;
      margin-top: 12px;
    }

    .payment-quick-actions button {
      border: 1px solid rgba(14, 165, 233, 0.25);
      background: #eff6ff;
      color: #075985;
      border-radius: 999px;
      padding: 8px 13px;
      font-weight: 800;
    }

    .payment-summary-line {
      display: flex;
      justify-content: space-between;
      gap: 18px;
      padding: 12px 0;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }

    .payment-summary-line:last-child {
      border-bottom: 0;
    }

    .payment-summary-line span {
      color: #64748b;
    }

    .payment-summary-line strong {
      text-align: right;
      color: #0f172a;
    }

    .payment-progress {
      height: 12px;
      background: #e2e8f0;
      border-radius: 999px;
      overflow: hidden;
      margin: 14px 0 8px;
    }

    .payment-progress span {
      display: block;
      height: 100%;
      width: <?php echo h((string) $paid_percent); ?>%;
      background: linear-gradient(90deg, #16a34a, #22c55e);
      border-radius: inherit;
    }

    .payment-submit {
      height: 52px;
      border-radius: 14px;
      font-weight: 850;
    }

    @media (max-width: 992px) {
      .payment-layout,
      .payment-kpi-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 576px) {
      .payment-detail-grid {
        grid-template-columns: 1fr;
      }

      .payment-hero {
        border-radius: 12px;
      }
    }
  </style>
</head>
<body class="dashboard-page">
  <div class="d-flex dashboard-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
      <?php include __DIR__ . '/../../includes/navbar.php'; ?>

      <div class="container-fluid p-4 payment-page">
        <div class="payment-shell mx-auto">
          <div class="payment-hero">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="text-white-50 fw-semibold mb-1">Thanh toán công nợ hội viên</div>
                <h2 class="mb-1">Thu tiền hội viên</h2>
                <div class="text-white-50">Kiểm tra số dư, nhập số tiền thực thu và lưu lịch sử thanh toán.</div>
              </div>
              <a href="<?php echo $root_base_path; ?>php/members/view-member.php?id=<?php echo (int) $member_id; ?>" class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i>Quay lại hồ sơ
              </a>
            </div>

            <div class="payment-kpi-grid">
              <div class="payment-kpi">
                <span>Tổng gói tập</span>
                <strong><?php echo money_vn($total_amount); ?></strong>
              </div>
              <div class="payment-kpi">
                <span>Đã thu</span>
                <strong><?php echo money_vn($paid_amount); ?></strong>
              </div>
              <div class="payment-kpi">
                <span>Còn nợ</span>
                <strong><?php echo money_vn($remaining_amount); ?></strong>
              </div>
            </div>
          </div>

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger mt-3 mb-0">
              <i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?>
            </div>
          <?php endif; ?>

          <div class="payment-layout">
            <div class="payment-panel">
              <div class="payment-panel-title">
                <h5>Thông tin thanh toán</h5>
                <span class="badge text-bg-danger">Còn nợ</span>
              </div>

              <div class="payment-member-card">
                <div class="payment-avatar">
                  <?php echo h(function_exists('mb_substr') ? mb_substr((string) $member['full_name'], 0, 1, 'UTF-8') : substr((string) $member['full_name'], 0, 1)); ?>
                </div>
                <div>
                  <h5 class="fw-bold mb-1"><?php echo h($member['full_name']); ?></h5>
                  <div class="text-muted small">
                    <i class="bi bi-telephone me-1"></i><?php echo h($member['phone'] ?? 'Chưa có SĐT'); ?>
                    <?php if (!empty($member['email'])): ?>
                      <span class="mx-2">•</span><i class="bi bi-envelope me-1"></i><?php echo h($member['email']); ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="payment-detail-grid">
                <div class="payment-detail">
                  <span>Gói tập</span>
                  <strong><?php echo h($member['package_name'] ?: 'Không xác định'); ?></strong>
                </div>
                <div class="payment-detail">
                  <span>Thời hạn</span>
                  <strong>
                    <?php echo !empty($history['start_date']) ? h(date('d/m/Y', strtotime((string) $history['start_date']))) : '-'; ?>
                    -
                    <?php echo !empty($history['end_date']) ? h(date('d/m/Y', strtotime((string) $history['end_date']))) : '-'; ?>
                  </strong>
                </div>
              </div>

              <form method="POST" action="" id="paymentForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="member_id" value="<?php echo (int) $member_id; ?>">
                <input type="hidden" name="payment_amount" id="paymentAmountRaw" value="<?php echo h((string) $initial_amount); ?>">

                <div class="mb-3">
                  <label class="form-label fw-bold">Số tiền thu</label>
                  <div class="payment-amount-input">
                    <input
                      type="text"
                      id="paymentAmountDisplay"
                      class="form-control"
                      inputmode="numeric"
                      autocomplete="off"
                      aria-describedby="paymentAmountHelp"
                      value="<?php echo h(money_vn($initial_amount)); ?>">
                    <span class="currency">VNĐ</span>
                  </div>
                  <div id="paymentAmountHelp" class="payment-form-help mt-2">
                    Có thể nhập dạng 300000 hoặc 300.000. Hệ thống chỉ lưu số tiền thực thu.
                  </div>

                  <div class="payment-quick-actions" aria-label="Chọn nhanh số tiền">
                    <?php foreach ($quick_amounts as $label => $value): ?>
                      <?php if ($value > 0): ?>
                        <button type="button" data-amount="<?php echo h((string) $value); ?>"><?php echo h($label); ?></button>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Phương thức</label>
                    <select name="payment_method" class="form-select">
                      <?php foreach ($payment_methods as $key => $label): ?>
                        <option value="<?php echo h($key); ?>"><?php echo h($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">Ghi chú nội bộ</label>
                    <input type="text" name="payment_note" class="form-control" maxlength="180" placeholder="Ví dụ: thu tại quầy, mã CK...">
                  </div>
                </div>

                <button type="submit" class="btn btn-danger w-100 mt-4 payment-submit">
                  <i class="bi bi-cash-coin me-2"></i>Xác nhận thu tiền
                </button>
              </form>
            </div>

            <aside class="payment-panel">
              <div class="payment-panel-title">
                <h5>Đối soát</h5>
                <i class="bi bi-receipt text-primary fs-4"></i>
              </div>

              <div class="payment-summary-line">
                <span>Số nợ hiện tại</span>
                <strong><?php echo money_vn($remaining_amount); ?></strong>
              </div>
              <div class="payment-summary-line">
                <span>Số tiền thu</span>
                <strong id="previewCollect"><?php echo money_vn($initial_amount); ?></strong>
              </div>
              <div class="payment-summary-line">
                <span>Còn lại sau thu</span>
                <strong id="previewRemaining"><?php echo money_vn(max(0, $remaining_amount - $initial_amount)); ?></strong>
              </div>
              <div class="payment-summary-line">
                <span>Trạng thái sau thu</span>
                <strong id="previewStatus"><?php echo ($remaining_amount - $initial_amount) <= 0 ? 'Đã thanh toán đủ' : 'Còn công nợ'; ?></strong>
              </div>

              <div class="mt-4">
                <div class="d-flex justify-content-between small text-muted">
                  <span>Tiến độ đã thu</span>
                  <span><?php echo h(number_format($paid_percent, 0)); ?>%</span>
                </div>
                <div class="payment-progress"><span></span></div>
                <div class="small text-muted">
                  Sau khi xác nhận, số tiền sẽ cộng vào lịch sử gói tập đang hoạt động.
                </div>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?php echo $root_base_path; ?>js/main.js"></script>
  <script>
    (function () {
      const remainingAmount = <?php echo json_encode((int) $remaining_amount); ?>;
      const rawInput = document.getElementById('paymentAmountRaw');
      const displayInput = document.getElementById('paymentAmountDisplay');
      const previewCollect = document.getElementById('previewCollect');
      const previewRemaining = document.getElementById('previewRemaining');
      const previewStatus = document.getElementById('previewStatus');
      const form = document.getElementById('paymentForm');
      const formatter = new Intl.NumberFormat('vi-VN');

      function parseAmount(value) {
        const digits = String(value || '').replace(/\D+/g, '');
        return digits ? Number(digits) : 0;
      }

      function formatAmount(value) {
        return formatter.format(Math.max(0, Number(value || 0)));
      }

      function syncAmount(nextValue) {
        const amount = Math.min(parseAmount(nextValue), remainingAmount);
        rawInput.value = String(amount);
        displayInput.value = amount > 0 ? formatAmount(amount) : '';
        previewCollect.textContent = formatAmount(amount) + ' VNĐ';
        previewRemaining.textContent = formatAmount(Math.max(0, remainingAmount - amount)) + ' VNĐ';
        previewStatus.textContent = remainingAmount - amount <= 0 ? 'Đã thanh toán đủ' : 'Còn công nợ';
      }

      displayInput.addEventListener('input', function () {
        rawInput.value = String(parseAmount(displayInput.value));
        displayInput.classList.remove('is-invalid');
      });

      displayInput.addEventListener('blur', function () {
        syncAmount(displayInput.value);
      });

      document.querySelectorAll('[data-amount]').forEach(function (button) {
        button.addEventListener('click', function () {
          syncAmount(button.dataset.amount);
          displayInput.focus();
        });
      });

      form.addEventListener('submit', function (event) {
        const amount = parseAmount(displayInput.value || rawInput.value);

        if (amount <= 0 || amount > remainingAmount) {
          event.preventDefault();
          displayInput.focus();
          displayInput.classList.add('is-invalid');
          return;
        }

        displayInput.classList.remove('is-invalid');
        rawInput.value = String(amount);
      });

      syncAmount(rawInput.value);
    })();
  </script>
</body>
</html>
