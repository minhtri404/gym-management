<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

include __DIR__ . '/../../includes/functions/package-functions.php';

$packages = getActivePackages($conn);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_vn($amount)
{
    return number_format((float)$amount, 0, ',', '.') . 'đ';
}

function package_price_text($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        return 'Miễn phí';
    }

    return money_vn($package['price'] ?? $package['package_price'] ?? 0);
}

function package_duration_text($package)
{
    if (is_array($package) && (($package['package_type'] ?? 'paid') === 'free_trial')) {
        $days = (int)($package['duration_days'] ?? 7);
        return 'Dùng thử: ' . $days . ' ngày';
    }

    $duration = is_array($package)
        ? ($package['duration_months'] ?? $package['duration'] ?? $package['duration_in_months'] ?? 0)
        : $package;

    $duration = (int)$duration;

    if ($duration <= 0) {
        return 'Thời hạn linh hoạt';
    }

    return 'Thời hạn: ' . $duration . ' tháng';
}

function package_button_text($package)
{
    if (($package['package_type'] ?? 'paid') === 'free_trial') {
        $days = (int)($package['duration_days'] ?? 7);
        return 'Dùng thử ' . $days . ' ngày';
    }

    return 'Đăng ký gói này';
}

function clean_package_text($value)
{
    $value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
    $value = trim($value);
    $value = preg_replace('/^[\-\*\x{2022}\s]+/u', '', $value);
    return trim((string)$value);
}

function split_package_benefits($text)
{
    $text = trim((string)$text);

    if ($text === '') {
        return [];
    }

    $parts = preg_split('/\r\n|\r|\n|;/u', $text) ?: [];
    $items = [];

    foreach ($parts as $part) {
        $item = clean_package_text($part);

        if ($item !== '') {
            $items[] = $item;
        }
    }

    return $items;
}

function package_price_points($packages)
{
    $points = [];

    foreach ($packages as $package) {
        $points[] = number_format((float)($package['price'] ?? 0), 2, '.', '');
    }

    $points = array_values(array_unique($points));
    sort($points, SORT_NATURAL);
    return $points;
}

function package_tier($price, $pricePoints)
{
    $priceKey = number_format((float)$price, 2, '.', '');
    $index = array_search($priceKey, $pricePoints, true);
    $count = count($pricePoints);

    if ($count <= 1 || $index === false) {
        return 'standard';
    }

    if ($index === 0) {
        return 'basic';
    }

    if ($count >= 4 && $index === $count - 1) {
        return 'vip';
    }

    if ($index === $count - 1 || ($count >= 4 && $index === $count - 2)) {
        return 'premium';
    }

    return 'standard';
}

function package_tag($tier)
{
    if ($tier === 'basic') {
        return ['text' => 'Khởi đầu', 'class' => 'basic'];
    }

    if ($tier === 'premium') {
        return ['text' => 'Nâng cao', 'class' => 'premium'];
    }

    if ($tier === 'vip') {
        return ['text' => 'Toàn diện', 'class' => 'vip'];
    }

    return ['text' => 'Phổ biến', 'class' => 'popular'];
}

function package_summary($package, $tier)
{
    $shortDescription = clean_package_text($package['short_description'] ?? '');
    $description = clean_package_text($package['description'] ?? '');
    $suitableFor = clean_package_text($package['suitable_for'] ?? '');

    if ($shortDescription !== '') {
        return $shortDescription;
    }

    if ($description !== '') {
        return $description;
    }

    if ($suitableFor !== '') {
        return 'Phù hợp cho: ' . $suitableFor;
    }

    if ($tier === 'basic') {
        return 'Gói phù hợp cho người mới bắt đầu và cần mức chi phí hợp lý.';
    }

    if ($tier === 'premium') {
        return 'Gói dành cho người tập thường xuyên và cần nhiều tiện ích hơn.';
    }

    if ($tier === 'vip') {
        return 'Gói trải nghiệm đầy đủ với quyền lợi ưu tiên và linh hoạt hơn.';
    }

    return 'Gói cân bằng giữa chi phí, thời lượng và trải nghiệm tập luyện hằng ngày.';
}

function build_default_package_benefits($package, $tier)
{
    $duration = (int)($package['duration_months'] ?? 0);

    $defaults = [
        'trial' => [
            'Trải nghiệm khu tập trước khi đăng ký dài hạn',
            'Sử dụng khu tập gym tiêu chuẩn',
            'Phù hợp để làm quen không gian và thiết bị',
            'Tư vấn gói tập phù hợp sau thời gian dùng thử',
        ],
        'basic' => [
            'Sử dụng khu tập gym tiêu chuẩn',
            'Check-in không giới hạn trong giờ hoạt động',
            'Hỗ trợ làm quen máy tập cơ bản',
            'Tủ gửi đồ dùng chung',
        ],
        'standard' => [
            'Sử dụng đầy đủ khu tập và máy cardio',
            'Tham gia lớp group cơ bản',
            'Tủ gửi đồ cá nhân',
            'Nước uống miễn phí',
        ],
        'premium' => [
            'Sử dụng toàn bộ thiết bị và khu chức năng',
            'Tham gia tất cả lớp group',
            'Ưu tiên hỗ trợ tại sàn tập',
            'Khăn tập và nước uống miễn phí',
        ],
        'vip' => [
            'Sử dụng toàn bộ khu tập không giới hạn',
            'Ưu tiên hỗ trợ và tư vấn lộ trình tập luyện',
            'Theo dõi tiến độ luyện tập định kỳ',
            'Khăn tập, nước uống và tiện ích ưu tiên',
        ],
    ];

    $items = $defaults[$tier] ?? $defaults['standard'];

    if ($duration >= 12) {
        $items[] = 'Tiết kiệm chi phí hơn khi đăng ký dài hạn';
    } elseif ($duration >= 6) {
        $items[] = 'Phù hợp để duy trì thói quen tập luyện ổn định';
    }

    return $items;
}

function package_benefits($package, $pricePoints)
{
    $customItems = split_package_benefits($package['benefits'] ?? '');
    $isTrial = (($package['package_type'] ?? 'paid') === 'free_trial');
    $fallbackItems = build_default_package_benefits(
        $package,
        $isTrial ? 'trial' : package_tier($package['price'] ?? 0, $pricePoints)
    );

    $items = [];
    $seen = [];

    foreach (array_merge($customItems, $fallbackItems) as $item) {
        $cleanItem = clean_package_text($item);
        $normalized = mb_strtolower($cleanItem, 'UTF-8');

        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;
        $items[] = $cleanItem;

        if (count($items) >= 5) {
            break;
        }
    }

    return $items;
}

$price_points = package_price_points($packages);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gói tập - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="../includes/assets/css/packages.css?v=package-light-1">
</head>

<body class="user-body">
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="packages-page">

    <section class="packages-hero-new">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="packages-eyebrow">
                        <i class="bi bi-gem me-1"></i>
                        Gói hội viên
                    </div>

                    <h1>Chọn gói tập phù hợp với bạn</h1>

                    <p>
                        Chọn gói tập phù hợp với mục tiêu, ngân sách và thời gian của bạn.
                        Tất cả gói tập đều mang đến trải nghiệm tập luyện chất lượng tại FLEXZONE.
                    </p>
                </div>

                <div class="col-lg-5">
                    <div class="packages-hero-features">
                        <div class="hero-feature-box">
                            <div class="hero-feature-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <strong>Linh hoạt thời gian</strong>
                                <span>Nhiều lựa chọn gói</span>
                            </div>
                        </div>

                        <div class="hero-feature-box">
                            <div class="hero-feature-icon">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div>
                                <strong>HLV hỗ trợ</strong>
                                <span>Tư vấn tận tâm</span>
                            </div>
                        </div>

                        <div class="hero-feature-box">
                            <div class="hero-feature-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <strong>Thiết bị an toàn</strong>
                                <span>Không gian hiện đại</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="packages-list-section">
        <div class="container">
            <div class="row g-4 justify-content-center">

                <?php if (!empty($packages)): ?>
                    <?php foreach ($packages as $index => $pkg): ?>
                        <?php
                        $package_id = (int)($pkg['id'] ?? 0);
                        $package_name = $pkg['name'] ?? $pkg['package_name'] ?? 'Gói tập';
                        $price = $pkg['price'] ?? $pkg['package_price'] ?? 0;
                        $duration = $pkg['duration_months'] ?? $pkg['duration'] ?? $pkg['duration_in_months'] ?? null;
                        $description = $pkg['description'] ?? $pkg['note'] ?? $pkg['details'] ?? '';

                        $status = $pkg['status'] ?? 'active';
                        $is_active = true;

                        if (is_string($status)) {
                            $is_active = strtolower($status) !== 'inactive';
                        } elseif (is_numeric($status)) {
                            $is_active = (int)$status === 1;
                        }

                        $is_trial = (($pkg['package_type'] ?? 'paid') === 'free_trial');

                        $tier = $is_trial ? 'trial' : package_tier($price, $price_points);
                        $tag = $is_trial
                            ? ['text' => 'Dùng thử', 'class' => 'basic']
                            : package_tag($tier);

                        $summary = package_summary($pkg, $tier);
                        $benefits = package_benefits($pkg, $price_points);
                        ?>

                        <?php if ($is_active): ?>
                            <div class="col-lg-4 col-md-6 d-flex">
                                <article class="package-card-pro <?php echo $index === 1 ? 'featured' : ''; ?> w-100">

                                    <div class="package-image-box">
                                        <img src="<?php echo h(getPackageImageUrl($pkg, $base_path, $index)); ?>" alt="<?php echo h($package_name); ?>">
                                        <span class="package-tag <?php echo h($tag['class']); ?>">
                                            <?php echo h($tag['text']); ?>
                                        </span>
                                    </div>

                                    <div class="package-card-content">

                                        <div class="package-name-row">
                                            <h3><?php echo h($package_name); ?></h3>
                                            <div class="package-price-pro">
                                                <?php echo h(package_price_text($pkg)); ?>
                                            </div>
                                        </div>

                                        <div class="package-duration">
                                            <i class="bi bi-calendar3"></i>
                                            <span><?php echo h(package_duration_text($pkg)); ?></span>
                                        </div>

                                        <p class="package-summary-text">
                                            <?php echo h($summary); ?>
                                        </p>

                                        <ul class="package-benefit-list">
                                            <?php foreach ($benefits as $benefit): ?>
                                                <li>
                                                    <i class="bi bi-check2"></i>
                                                    <span><?php echo h($benefit); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <div class="package-card-actions">
                                            <a href="<?php echo $base_path; ?>user/package/detail.php?id=<?php echo $package_id; ?>"
                                               class="btn-package-outline">
                                                Chi tiết gói
                                            </a>

                                            <a href="<?php echo $base_path; ?>user/package/register?package_id=<?php echo $package_id; ?>"
                                               class="btn-package-primary">
                                                <?php echo h(package_button_text($pkg)); ?>
                                            </a>
                                        </div>

                                    </div>
                                </article>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-package-pro text-center">
                            <div class="display-5 mb-3 text-info">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h4 class="mb-3">Chưa có gói tập</h4>
                            <p class="text-secondary mb-0">
                                Hiện chưa có dữ liệu gói tập để hiển thị. Vui lòng thêm gói tập trong trang quản trị.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div class="package-trust-strip">
                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <strong>Dụng cụ chất lượng</strong>
                        <span>Thiết bị hiện đại, an toàn</span>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <strong>HLV chuyên nghiệp</strong>
                        <span>Đồng hành cùng hội viên</span>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-headset"></i>
                    </div>
                    <div>
                        <strong>Hỗ trợ 24/7</strong>
                        <span>Luôn sẵn sàng hỗ trợ</span>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">
                        <i class="bi bi-calendar2-week"></i>
                    </div>
                    <div>
                        <strong>Linh hoạt thời gian</strong>
                        <span>Nhiều gói tập lựa chọn</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
