<?php
$page_title = 'Danh sách check-in/check-out';
include __DIR__ . '/../includes/auth-check.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function env_value_qr($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../.env';

    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) === 2 && trim($parts[0]) === $key) {
                return trim(trim($parts[1]), "\"'");
            }
        }
    }

    return $default;
}

function resolve_qr_base_url()
{
    $configured = rtrim(env_value_qr('APP_URL', ''), '/');
    $configuredHost = $configured !== '' ? (parse_url($configured, PHP_URL_HOST) ?: '') : '';

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $httpHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));

    $hostOnly = $httpHost !== '' ? preg_replace('/:\d+$/', '', $httpHost) : $serverName;
    $hostOnly = trim((string)$hostOnly, '[]');

    $isLoopback = in_array(strtolower($hostOnly), ['localhost', '127.0.0.1', '::1'], true);
    if ($httpHost !== '' && !$isLoopback) {
        return $scheme . '://' . $httpHost;
    }

    if ($configured !== '' && !in_array(strtolower($configuredHost), ['localhost', '127.0.0.1', '::1'], true)) {
        return $configured;
    }

    $serverPort = trim((string)($_SERVER['SERVER_PORT'] ?? ''));
    $portSuffix = ($serverPort !== '' && !in_array($serverPort, ['80', '443'], true)) ? ':' . $serverPort : '';
    $detectedIps = gethostbynamel(gethostname()) ?: [];

    foreach ($detectedIps as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !preg_match('/^(127\.|169\.254\.)/', $ip)) {
            return $scheme . '://' . $ip . $portSuffix;
        }
    }

    if ($configured !== '') {
        return $configured;
    }

    return $scheme . '://' . ($httpHost !== '' ? $httpHost : 'localhost:8086');
}

function money_vn($amount)
{
    return number_format((float)$amount, 0, ',', '.') . ' VNĐ';
}

function format_datetime_vn($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime((string)$value));
}

function format_time_vn($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('H:i', strtotime((string)$value));
}

function checkin_duration_text($checkinTime, $checkoutTime)
{
    if (empty($checkinTime) || empty($checkoutTime)) {
        return '-';
    }

    try {
        $start = new DateTime((string)$checkinTime);
        $end = new DateTime((string)$checkoutTime);
        $minutes = max(0, (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60));

        if ($minutes < 60) {
            return $minutes . ' phút';
        }

        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;

        return $remain > 0 ? $hours . ' giờ ' . $remain . ' phút' : $hours . ' giờ';
    } catch (Throwable $exception) {
        return '-';
    }
}

function checkin_status_badge($checkoutTime, $status)
{
    if (!empty($checkoutTime)) {
        return '<span class="badge bg-secondary">Đã check-out</span>';
    }

    if ($status === 'checked_out') {
        return '<span class="badge bg-secondary">Đã check-out</span>';
    }

    return '<span class="badge bg-success">Đang trong phòng</span>';
}

$qrSecret = env_value_qr('CHECKIN_QR_TOKEN', '');
$qrExpiresAt = time() + 300;
$checkinUrl = '';
$qrImage = '';

if ($qrSecret !== '') {
    $qrSignature = hash_hmac('sha256', (string) $qrExpiresAt, $qrSecret);
    $checkinUrl = resolve_qr_base_url() . '/user/checkins/scan?' . http_build_query([
        'expires' => $qrExpiresAt,
        'signature' => $qrSignature,
    ]);
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($checkinUrl);
}

$keyword = trim((string)($_GET['q'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_method = trim((string)($_GET['method'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? 15);

if (!in_array($filter_status, ['', 'in', 'out'], true)) {
    $filter_status = '';
}

if (!in_array($filter_method, ['', 'manual', 'qr'], true)) {
    $filter_method = '';
}

if ($per_page <= 0) {
    $per_page = 15;
}
if ($per_page > 100) {
    $per_page = 100;
}

$where = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $where[] = '(m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ? OR p.package_name LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($date_from !== '') {
    $where[] = 'c.checkin_date >= ?';
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '') {
    $where[] = 'c.checkin_date <= ?';
    $params[] = $date_to;
    $types .= 's';
}

if ($filter_status === 'in') {
    $where[] = 'c.checkout_time IS NULL';
} elseif ($filter_status === 'out') {
    $where[] = 'c.checkout_time IS NOT NULL';
}

if ($filter_method !== '') {
    $where[] = 'c.checkin_method = ?';
    $params[] = $filter_method;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$countSql = "
    SELECT COUNT(*) AS total
    FROM checkins c
    JOIN members m ON m.id = c.member_id
    LEFT JOIN packages p ON p.id = m.package_id
    $whereSql
";

if ($params) {
    $stmtCount = $conn->prepare($countSql);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();
} else {
    $countResult = $conn->query($countSql);
    $total = (int)($countResult?->fetch_assoc()['total'] ?? 0);
}

$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        c.id,
        c.member_id,
        c.checkin_date,
        c.checkin_time,
        c.checkout_time,
        c.status,
        c.checkin_method,
        c.note,
        c.created_at,
        m.full_name,
        m.phone,
        m.email,
        m.status AS member_status,
        m.end_date,
        p.package_name,
        p.price
    FROM checkins c
    JOIN members m ON m.id = c.member_id
    LEFT JOIN packages p ON p.id = m.package_id
    $whereSql
    ORDER BY c.checkin_time DESC, c.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($params) {
    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$checkins = [];
while ($row = $result->fetch_assoc()) {
    $checkins[] = $row;
}
$stmt->close();

$today = date('Y-m-d');
$todayStats = [
    'total' => 0,
    'inside' => 0,
    'checkout' => 0,
    'manual' => 0,
    'qr' => 0,
];

$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN checkout_time IS NULL THEN 1 ELSE 0 END) AS inside_count,
        SUM(CASE WHEN checkout_time IS NOT NULL THEN 1 ELSE 0 END) AS checkout_count,
        SUM(CASE WHEN checkin_method = 'manual' THEN 1 ELSE 0 END) AS manual_count,
        SUM(CASE WHEN checkin_method = 'qr' THEN 1 ELSE 0 END) AS qr_count
    FROM checkins
    WHERE checkin_date = CURDATE()
");

if ($statsResult) {
    $statsRow = $statsResult->fetch_assoc();
    $todayStats = [
        'total' => (int)($statsRow['total'] ?? 0),
        'inside' => (int)($statsRow['inside_count'] ?? 0),
        'checkout' => (int)($statsRow['checkout_count'] ?? 0),
        'manual' => (int)($statsRow['manual_count'] ?? 0),
        'qr' => (int)($statsRow['qr_count'] ?? 0),
    ];
}

$activeMembers = [];
$memberResult = $conn->query("
    SELECT
        m.id,
        m.full_name,
        m.phone,
        m.end_date,
        p.package_name,
        p.price
    FROM members m
    LEFT JOIN packages p ON p.id = m.package_id
    WHERE m.status = 'active'
    ORDER BY m.full_name ASC
");

if ($memberResult) {
    while ($member = $memberResult->fetch_assoc()) {
        $activeMembers[] = $member;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Danh sách check-in/check-out</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .checkin-admin-page {
      background: #f5f7fb;
      min-height: calc(100vh - 64px);
    }

    .checkin-hero {
      background: linear-gradient(135deg, #0f172a 0%, #155e75 58%, #0ea5e9 100%);
      color: #fff;
      border-radius: 16px;
      padding: 22px;
      box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
    }

    .checkin-stat-card {
      border: 0;
      border-radius: 14px;
      box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    }

    .checkin-member-cell {
      min-width: 230px;
    }

    .checkin-note-cell {
      max-width: 260px;
      color: #64748b;
      font-size: 13px;
    }

    .checkin-qr-img {
      width: 180px;
      height: 180px;
      object-fit: contain;
    }

    .checkin-filter-card,
    .checkin-side-card {
      border: 0;
      border-radius: 14px;
      box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    }

    .checkin-method-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #eff6ff;
      color: #075985;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
    }
  </style>
</head>
<body class="dashboard-page">
<div class="d-flex dashboard-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="main-content flex-grow-1">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4 checkin-admin-page">
      <div class="checkin-hero mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <div class="text-white-50 fw-semibold mb-1">Theo dõi ra vào phòng tập</div>
            <h2 class="fw-bold mb-1">Danh sách check-in/check-out</h2>
            <div class="text-white-50">Quản lý lượt vào, giờ ra, phương thức check-in và xử lý check-out cho hội viên.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="?date_from=<?php echo h($today); ?>&date_to=<?php echo h($today); ?>" class="btn btn-light">
              <i class="bi bi-calendar-day me-1"></i>Hôm nay
            </a>
            <a href="?status=in" class="btn btn-outline-light">
              <i class="bi bi-person-walking me-1"></i>Đang trong phòng
            </a>
          </div>
        </div>
      </div>

      <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Check-in thủ công thành công.</div>
      <?php endif; ?>
      <?php if (isset($_GET['checkout']) && $_GET['checkout'] === '1'): ?>
        <div class="alert alert-success"><i class="bi bi-box-arrow-right me-2"></i>Check-out thành công.</div>
      <?php endif; ?>
      <?php if (isset($_GET['duplicate']) && $_GET['duplicate'] === '1'): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Hội viên đã check-in hôm nay.</div>
      <?php endif; ?>
      <?php if (isset($_GET['inactive']) && $_GET['inactive'] === '1'): ?>
        <div class="alert alert-warning"><i class="bi bi-person-x me-2"></i>Hội viên đang không hoạt động.</div>
      <?php endif; ?>
      <?php if (isset($_GET['expired']) && $_GET['expired'] === '1'): ?>
        <div class="alert alert-warning"><i class="bi bi-calendar-x me-2"></i>Gói tập của hội viên đã hết hạn.</div>
      <?php endif; ?>
      <?php if (isset($_GET['not_premium']) && $_GET['not_premium'] === '1'): ?>
        <div class="alert alert-warning"><i class="bi bi-shield-exclamation me-2"></i>Gói tập không đủ điều kiện check-in theo cấu hình hiện tại.</div>
      <?php endif; ?>
      <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Không thể xử lý check-in/check-out. Vui lòng kiểm tra lại dữ liệu.</div>
      <?php endif; ?>
      <?php if (isset($_GET['csrf_error']) && $_GET['csrf_error'] === '1'): ?>
        <div class="alert alert-danger"><i class="bi bi-shield-x me-2"></i>Phiên thao tác đã hết hạn. Vui lòng tải lại trang và thử lại.</div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card checkin-stat-card"><div class="card-body">
            <div class="text-muted small">Check-in hôm nay</div>
            <h3 class="fw-bold mb-0"><?php echo $todayStats['total']; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card checkin-stat-card"><div class="card-body">
            <div class="text-muted small">Đang trong phòng</div>
            <h3 class="fw-bold text-success mb-0"><?php echo $todayStats['inside']; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card checkin-stat-card"><div class="card-body">
            <div class="text-muted small">Đã check-out</div>
            <h3 class="fw-bold text-secondary mb-0"><?php echo $todayStats['checkout']; ?></h3>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card checkin-stat-card"><div class="card-body">
            <div class="text-muted small">QR / Thủ công</div>
            <h3 class="fw-bold text-primary mb-0"><?php echo $todayStats['qr']; ?> / <?php echo $todayStats['manual']; ?></h3>
          </div></div>
        </div>
      </div>

      <div class="row g-4 align-items-start">
        <div class="col-xl-8">
          <div class="card checkin-filter-card mb-4">
            <div class="card-body">
              <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-4">
                  <label class="form-label">Tìm kiếm</label>
                  <input type="text" name="q" class="form-control" placeholder="Tên, SĐT, email, gói tập" value="<?php echo h($keyword); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                  <label class="form-label">Từ ngày</label>
                  <input type="date" name="date_from" class="form-control" value="<?php echo h($date_from); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                  <label class="form-label">Đến ngày</label>
                  <input type="date" name="date_to" class="form-control" value="<?php echo h($date_to); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                  <label class="form-label">Trạng thái</label>
                  <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="in" <?php echo $filter_status === 'in' ? 'selected' : ''; ?>>Đang trong phòng</option>
                    <option value="out" <?php echo $filter_status === 'out' ? 'selected' : ''; ?>>Đã check-out</option>
                  </select>
                </div>
                <div class="col-lg-2 col-md-6">
                  <label class="form-label">Phương thức</label>
                  <select name="method" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="manual" <?php echo $filter_method === 'manual' ? 'selected' : ''; ?>>Thủ công</option>
                    <option value="qr" <?php echo $filter_method === 'qr' ? 'selected' : ''; ?>>QR</option>
                  </select>
                </div>
                <div class="col-lg-2 col-md-4">
                  <label class="form-label">Hiển thị</label>
                  <select name="per_page" class="form-select">
                    <?php foreach ([10, 15, 25, 50, 100] as $option): ?>
                      <option value="<?php echo $option; ?>" <?php echo $per_page === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-lg-4 col-md-8 d-flex gap-2">
                  <button class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i>Tìm / Lọc
                  </button>
                  <a href="checkins.php" class="btn btn-outline-secondary">Đặt lại</a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="card checkin-side-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
              <h5 class="fw-bold mb-0">Check-in thủ công</h5>
            </div>
            <div class="card-body pt-2">
              <form method="POST" action="../php/checkins/add-checkin.php">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="mb-3">
                  <label class="form-label">Hội viên</label>
                  <select name="member_id" class="form-select" required>
                    <option value="">Chọn hội viên</option>
                    <?php foreach ($activeMembers as $member): ?>
                      <option value="<?php echo (int)$member['id']; ?>">
                        <?php echo h($member['full_name']); ?>
                        <?php echo !empty($member['phone']) ? ' - ' . h($member['phone']) : ''; ?>
                        <?php echo !empty($member['package_name']) ? ' - ' . h($member['package_name']) : ''; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Ghi chú</label>
                  <input type="text" name="note" class="form-control" maxlength="255" placeholder="Ví dụ: check-in tại quầy lễ tân">
                </div>
                <button class="btn btn-success w-100">
                  <i class="bi bi-box-arrow-in-right me-1"></i>Ghi nhận check-in
                </button>
              </form>
            </div>
          </div>

          <div class="card checkin-side-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
              <h5 class="fw-bold mb-0">QR check-in</h5>
            </div>
            <div class="card-body text-center pt-2">
              <?php if ($qrSecret === ''): ?>
                <div class="alert alert-warning text-start mb-0">
                  Chưa cấu hình <code>CHECKIN_QR_TOKEN</code> trong file <code>.env</code>.
                </div>
              <?php else: ?>
                <div class="bg-light rounded-3 p-3 mb-3 d-inline-block">
                  <img src="<?php echo h($qrImage); ?>" alt="QR Check-in" class="checkin-qr-img">
                </div>
                <div class="small text-muted mb-2">
                  QR hết hạn lúc <?php echo h(date('H:i:s', $qrExpiresAt)); ?>. Tải lại trang để tạo mã mới.
                </div>
                <div class="small text-muted text-start" style="word-break:break-all;">
                  <?php echo h($checkinUrl); ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-0">Lịch sử check-in/check-out</h5>
            <div class="text-muted small">
              Hiển thị <strong><?php echo $total > 0 ? (($offset + 1) . ' - ' . min($offset + $per_page, $total)) : '0'; ?></strong>
              trong <strong><?php echo $total; ?></strong> lượt.
            </div>
          </div>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Hội viên</th>
                  <th>Gói tập</th>
                  <th>Ngày</th>
                  <th>Giờ vào</th>
                  <th>Giờ ra</th>
                  <th>Thời lượng</th>
                  <th>Phương thức</th>
                  <th>Trạng thái</th>
                  <th>Ghi chú</th>
                  <th class="text-end">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($checkins)): ?>
                  <?php foreach ($checkins as $item): ?>
                    <tr>
                      <td class="checkin-member-cell">
                        <div class="fw-bold"><?php echo h($item['full_name']); ?></div>
                        <div class="small text-muted">
                          <?php echo h($item['phone'] ?: $item['email'] ?: 'Chưa có liên hệ'); ?>
                        </div>
                      </td>
                      <td>
                        <div><?php echo h($item['package_name'] ?: 'Chưa có gói'); ?></div>
                        <?php if ($item['price'] !== null): ?>
                          <div class="small text-muted"><?php echo money_vn($item['price']); ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?php echo !empty($item['checkin_date']) ? h(date('d/m/Y', strtotime((string)$item['checkin_date']))) : '-'; ?></td>
                      <td><?php echo h(format_time_vn($item['checkin_time'])); ?></td>
                      <td><?php echo h(format_time_vn($item['checkout_time'])); ?></td>
                      <td><?php echo h(checkin_duration_text($item['checkin_time'], $item['checkout_time'])); ?></td>
                      <td>
                        <span class="checkin-method-pill">
                          <i class="bi <?php echo ($item['checkin_method'] ?? '') === 'qr' ? 'bi-qr-code-scan' : 'bi-person-check'; ?>"></i>
                          <?php echo h($item['checkin_method'] ?: 'manual'); ?>
                        </span>
                      </td>
                      <td><?php echo checkin_status_badge($item['checkout_time'], (string)$item['status']); ?></td>
                      <td class="checkin-note-cell"><?php echo h($item['note'] ?: '-'); ?></td>
                      <td class="text-end">
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                          <a href="../php/members/view-member.php?id=<?php echo (int)$item['member_id']; ?>" class="btn btn-info btn-sm" title="Xem hội viên">
                            <i class="bi bi-eye"></i>
                          </a>
                          <?php if (empty($item['checkout_time'])): ?>
                            <form
                              method="POST"
                              action="../php/checkins/checkout.php"
                              onsubmit="return confirm('Xác nhận check-out cho hội viên này?');"
                            >
                              <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                              <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right me-1"></i>Check-out
                              </button>
                            </form>
                          <?php else: ?>
                            <span class="text-muted small align-self-center">Đã ra</span>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                      Chưa có dữ liệu check-in/check-out phù hợp.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($total_pages > 1): ?>
          <div class="card-footer bg-white border-0">
            <nav aria-label="Check-in pagination">
              <ul class="pagination justify-content-end mb-0">
                <?php
                  $qp = $_GET;
                  $qp['page'] = max(1, $page - 1);
                  $prevDisabled = $page <= 1 ? ' disabled' : '';
                ?>
                <li class="page-item<?php echo $prevDisabled; ?>">
                  <a class="page-link" href="?<?php echo h(http_build_query($qp)); ?>">&laquo;</a>
                </li>
                <?php
                  $start = max(1, $page - 3);
                  $end = min($total_pages, $page + 3);
                  for ($p = $start; $p <= $end; $p++):
                    $qp = $_GET;
                    $qp['page'] = $p;
                ?>
                  <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                    <a class="page-link" href="?<?php echo h(http_build_query($qp)); ?>"><?php echo $p; ?></a>
                  </li>
                <?php endfor; ?>
                <?php
                  $qp = $_GET;
                  $qp['page'] = min($total_pages, $page + 1);
                  $nextDisabled = $page >= $total_pages ? ' disabled' : '';
                ?>
                <li class="page-item<?php echo $nextDisabled; ?>">
                  <a class="page-link" href="?<?php echo h(http_build_query($qp)); ?>">&raquo;</a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script src="../js/main.js"></script>
</body>
</html>
