<?php
include __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions/package-functions.php';
require_once __DIR__ . '/../includes/functions/trainer-image-helper.php';
require_once __DIR__ . '/../includes/functions/banner-functions.php';

$base_path = '../';
$homeBanners = get_home_banners($conn, true);
$homePackages = getActivePackages($conn);
$homeTrainers = [];
$homeBranches = [];

$trainerStmt = $conn->prepare("
    SELECT id, full_name, avatar, specialty, experience_years, bio, rating
    FROM trainers
    WHERE status = 'active'
    ORDER BY rating DESC, experience_years DESC, id ASC
    LIMIT 3
");

if ($trainerStmt) {
    $trainerStmt->execute();
    $trainerResult = $trainerStmt->get_result();

    while ($trainerRow = $trainerResult->fetch_assoc()) {
        $homeTrainers[] = $trainerRow;
    }

    $trainerStmt->close();
}

$branchStmt = $conn->prepare("
    SELECT id, name, city, address, map_url, image, description
    FROM branches
    WHERE status = 'active'
    ORDER BY FIELD(city, 'Ho Chi Minh City', 'Da Nang', 'Can Tho', 'Dong Nai'), id ASC
");

if ($branchStmt) {
    $branchStmt->execute();
    $branchResult = $branchStmt->get_result();

    while ($branchRow = $branchResult->fetch_assoc()) {
        $homeBranches[] = $branchRow;
    }

    $branchStmt->close();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function homePackagePrice($price): string
{
    return number_format((float)$price, 0, ',', '.') . '&#273;';
}

function homePackageDuration($months): string
{
    $months = (int)$months;

    if ($months <= 0) {
        return 'Linh ho&#7841;t';
    }

    return $months . ' th&aacute;ng';
}

function homePackageFeatures(array $package): array
{
    $raw = trim((string)($package['benefits'] ?? ''));

    if ($raw === '') {
        $raw = trim((string)($package['short_description'] ?? ''));
    }

    if ($raw === '') {
        $raw = trim((string)($package['description'] ?? ''));
    }

    $items = [];

    if ($raw !== '') {
        $parts = preg_split('/\r\n|\r|\n|;|\|/', strip_tags($raw));

        foreach ($parts as $part) {
            $text = trim($part, " \t\n\r\0\x0B-*.");

            if ($text !== '') {
                $items[] = $text;
            }
        }
    }

    if (count($items) === 0) {
        $duration = (int)($package['duration_months'] ?? 0);
        if ($duration > 0) {
            $items[] = 'Thời hạn ' . $duration . ' tháng';
        }
        $items[] = 'Phù hợp nhiều mục tiêu luyện tập';
        $items[] = 'Hỗ trợ đăng ký và tư vấn tại phòng gym';
    }

    return array_slice(array_values(array_unique($items)), 0, 3);
}

function homePackageImage(array $package, int $index, string $basePath): string
{
    return getPackageImageUrl($package, $basePath, $index);
}

function homeTrainerAvatar(int $trainerId, ?string $avatar, string $basePath): string
{
    return resolve_trainer_avatar_url($trainerId, $avatar ?? '', $basePath);
}

function homeBranchImage(array $branch, int $index, string $basePath): string
{
    $image = trim((string)($branch['image'] ?? ''));

    if ($image !== '') {
        $paths = [
            'uploads/branches/' . $image,
            'assets/images/branches/' . $image,
            'user/includes/assets/images/branches/' . $image,
        ];

        foreach ($paths as $path) {
            if (is_file(__DIR__ . '/../' . $path)) {
                return $basePath . $path;
            }
        }
    }

    $fallbacks = [
        'assets/images/brett-jordan-U2q73PfHFpM-unsplash.jpg',
        'assets/images/mohamed-fareed-rbSNsoXk-3A-unsplash.jpg',
        'assets/images/ambitious-studio-rick-barrett-1RNQ11ZODJM-unsplash.jpg',
        'assets/images/imagebanne.jpg',
    ];

    return $basePath . $fallbacks[$index % count($fallbacks)];
}

function homeBranchCityLabel(string $city): string
{
    $labels = [
        'Ho Chi Minh City' => 'TP. Hồ Chí Minh',
        'Da Nang' => 'Đà Nẵng',
        'Can Tho' => 'Cần Thơ',
        'Dong Nai' => 'Đồng Nai',
    ];

    return $labels[$city] ?? $city;
}

function homeCleanPackageText($value): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
    $value = trim($value);
    $value = preg_replace('/^[\-\*\x{2022}\s]+/u', '', $value);
    return trim((string)$value);
}

function homeSplitPackageBenefits($text): array
{
    $text = trim((string)$text);

    if ($text === '') {
        return [];
    }

    $parts = preg_split('/\r\n|\r|\n|;/u', $text) ?: [];
    $items = [];

    foreach ($parts as $part) {
        $item = homeCleanPackageText($part);

        if ($item !== '') {
            $items[] = $item;
        }
    }

    return $items;
}

function homePackagePricePoints(array $packages): array
{
    $points = [];

    foreach ($packages as $package) {
        $points[] = number_format((float)($package['price'] ?? 0), 2, '.', '');
    }

    $points = array_values(array_unique($points));
    sort($points, SORT_NATURAL);
    return $points;
}

function homePackageTier($price, array $pricePoints): string
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

function homePackageTag(string $tier): array
{
    if ($tier === 'basic') {
        return ['text' => 'Kh&#7903;i &#273;&#7847;u', 'class' => 'basic'];
    }

    if ($tier === 'premium') {
        return ['text' => 'N&acirc;ng cao', 'class' => 'premium'];
    }

    if ($tier === 'vip') {
        return ['text' => 'To&agrave;n di&#7879;n', 'class' => 'vip'];
    }

    return ['text' => 'Ph&#7893; bi&#7871;n', 'class' => 'popular'];
}

function homePackageSummary(array $package, string $tier): string
{
    $shortDescription = homeCleanPackageText($package['short_description'] ?? '');
    $description = homeCleanPackageText($package['description'] ?? '');
    $suitableFor = homeCleanPackageText($package['suitable_for'] ?? '');

    if ($shortDescription !== '') {
        return $shortDescription;
    }

    if ($description !== '') {
        return $description;
    }

    if ($suitableFor !== '') {
        return 'Ph&ugrave; h&#7907;p cho: ' . $suitableFor;
    }

    if ($tier === 'basic') {
        return 'G&oacute;i ph&ugrave; h&#7907;p cho ng&#432;&#7901;i m&#7899;i b&#7855;t &#273;&#7847;u v&agrave; c&#7847;n m&#7913;c chi ph&iacute; h&#7907;p l&yacute;.';
    }

    if ($tier === 'premium') {
        return 'G&oacute;i d&agrave;nh cho ng&#432;&#7901;i t&#7853;p th&#432;&#7901;ng xuy&ecirc;n v&agrave; c&#7847;n nhi&#7873;u ti&#7879;n &iacute;ch h&#417;n.';
    }

    if ($tier === 'vip') {
        return 'G&oacute;i tr&#7843;i nghi&#7879;m &#273;&#7847;y &#273;&#7911; v&#7899;i quy&#7873;n l&#7907;i &#432;u ti&ecirc;n v&agrave; linh ho&#7841;t h&#417;n.';
    }

    return 'G&oacute;i c&acirc;n b&#7857;ng gi&#7919;a chi ph&iacute;, th&#7901;i l&#432;&#7907;ng v&agrave; tr&#7843;i nghi&#7879;m t&#7853;p luy&#7879;n h&#7857;ng ng&agrave;y.';
}

function homeDefaultPackageBenefits(array $package, string $tier): array
{
    $duration = (int)($package['duration_months'] ?? 0);

    $defaults = [
        'basic' => [
            'S&#7917; d&#7909;ng khu t&#7853;p gym ti&ecirc;u chu&#7849;n',
            'Check-in kh&ocirc;ng gi&#7899;i h&#7841;n trong gi&#7901; ho&#7841;t &#273;&#7897;ng',
            'H&#7895; tr&#7907; l&agrave;m quen m&aacute;y t&#7853;p c&#417; b&#7843;n',
            'T&#7911; g&#7917;i &#273;&#7891; d&ugrave;ng chung',
        ],
        'standard' => [
            'S&#7917; d&#7909;ng &#273;&#7847;y &#273;&#7911; khu t&#7853;p v&agrave; m&aacute;y cardio',
            'Tham gia l&#7899;p group c&#417; b&#7843;n',
            'T&#7911; g&#7917;i &#273;&#7891; c&aacute; nh&acirc;n',
            'N&#432;&#7899;c u&#7889;ng mi&#7877;n ph&iacute;',
        ],
        'premium' => [
            'S&#7917; d&#7909;ng to&agrave;n b&#7897; thi&#7871;t b&#7883; v&agrave; khu ch&#7913;c n&#259;ng',
            'Tham gia t&#7845;t c&#7843; l&#7899;p group',
            '&#431;u ti&ecirc;n h&#7895; tr&#7907; t&#7841;i s&agrave;n t&#7853;p',
            'Kh&#259;n t&#7853;p v&agrave; n&#432;&#7899;c u&#7889;ng mi&#7877;n ph&iacute;',
        ],
        'vip' => [
            'S&#7917; d&#7909;ng to&agrave;n b&#7897; khu t&#7853;p kh&ocirc;ng gi&#7899;i h&#7841;n',
            '&#431;u ti&ecirc;n h&#7895; tr&#7907; v&agrave; t&#432; v&#7845;n l&#7897; tr&igrave;nh t&#7853;p luy&#7879;n',
            'Theo d&otilde;i ti&#7871;n &#273;&#7897; luy&#7879;n t&#7853;p &#273;&#7883;nh k&#7923;',
            'Kh&#259;n t&#7853;p, n&#432;&#7899;c u&#7889;ng v&agrave; ti&#7879;n &iacute;ch &#432;u ti&ecirc;n',
        ],
    ];

    $items = $defaults[$tier] ?? $defaults['standard'];

    if ($duration >= 12) {
        $items[] = 'Ti&#7871;t ki&#7879;m chi ph&iacute; h&#417;n khi &#273;&#259;ng k&yacute; d&agrave;i h&#7841;n';
    } elseif ($duration >= 6) {
        $items[] = 'Ph&ugrave; h&#7907;p &#273;&#7875; duy tr&igrave; th&oacute;i quen t&#7853;p luy&#7879;n &#7893;n &#273;&#7883;nh';
    }

    return $items;
}

function homePackageBenefitsPro(array $package, array $pricePoints): array
{
    $customItems = homeSplitPackageBenefits($package['benefits'] ?? '');
    $tier = homePackageTier($package['price'] ?? 0, $pricePoints);
    $fallbackItems = homeDefaultPackageBenefits($package, $tier);
    $items = [];
    $seen = [];

    foreach (array_merge($customItems, $fallbackItems) as $item) {
        $cleanItem = homeCleanPackageText($item);
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($cleanItem, 'UTF-8') : strtolower($cleanItem);

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

$homePackagePricePoints = homePackagePricePoints($homePackages);
$homeBranchCities = [];

foreach ($homeBranches as $branch) {
    $city = trim((string)($branch['city'] ?? ''));

    if ($city !== '' && !in_array($city, $homeBranchCities, true)) {
        $homeBranchCities[] = $city;
    }
}

if (count($homeBranchCities) === 0) {
    $homeBranchCities[] = 'Ho Chi Minh City';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLEXZONE - Gym &amp; Th&#7875; h&igrave;nh</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="includes/assets/css/user.css?v=light-1">
    <link rel="stylesheet" href="includes/assets/css/why-choose.css?v=why-choose-1">
    <link rel="stylesheet" href="includes/assets/css/packages.css?v=package-light-1">
    <link rel="stylesheet" href="includes/assets/css/home-light.css?v=home-light-6">
</head>
<body class="user-body home-page-body">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="hero-section home-hero home-banner-slider" data-home-banner>
        <div class="home-banner-track">
            <?php foreach ($homeBanners as $index => $banner): ?>
                <?php
                $bannerTitle = trim((string)($banner['title'] ?? ''));
                $bannerSubtitle = trim((string)($banner['subtitle'] ?? ''));
                $bannerButtonText = trim((string)($banner['button_text'] ?? ''));
                $bannerButtonLink = trim((string)($banner['button_link'] ?? ''));
                $bannerImage = banner_image_url((string)($banner['image_path'] ?? ''), $base_path);
                ?>
                <article class="home-banner-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-banner-slide aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>">
                    <img src="<?php echo h($bannerImage); ?>" alt="<?php echo h($bannerTitle !== '' ? $bannerTitle : 'Banner FLEXZONE'); ?>" class="home-banner-image">
                    <div class="home-banner-overlay"></div>
                    <div class="container">
                        <div class="hero-content home-banner-content">
                            <div class="hero-kicker">
                                <i class="bi bi-lightning-charge-fill"></i>
                                FLEXZONE
                            </div>

                            <h1 class="hero-title">
                                <?php echo h($bannerTitle !== '' ? $bannerTitle : 'Bắt đầu hành trình hôm nay'); ?>
                            </h1>

                            <?php if ($bannerSubtitle !== ''): ?>
                                <p class="hero-text"><?php echo h($bannerSubtitle); ?></p>
                            <?php endif; ?>

                            <div class="d-flex flex-column flex-sm-row gap-3 hero-actions">
                                <?php if ($bannerButtonText !== '' && $bannerButtonLink !== ''): ?>
                                    <a href="<?php echo h(banner_link_url($bannerButtonLink, $base_path)); ?>" class="btn btn-hero-primary"><?php echo h($bannerButtonText); ?></a>
                                <?php endif; ?>
                                <a href="<?php echo $base_path; ?>user/package/index" class="btn btn-hero-outline">Xem g&oacute;i t&#7853;p</a>
                            </div>

                            <div class="hero-trust-row">
                                <span><i class="bi bi-check-circle-fill"></i> <?php echo count($homePackages); ?>+ g&oacute;i t&#7853;p</span>
                                <span><i class="bi bi-check-circle-fill"></i> Theo d&otilde;i h&#7897;i vi&ecirc;n</span>
                                <span><i class="bi bi-check-circle-fill"></i> H&#7895; tr&#7907; t&#7853;p luy&#7879;n</span>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (count($homeBanners) > 1): ?>
            <button class="home-banner-nav home-banner-prev" type="button" data-banner-prev onclick="event.stopImmediatePropagation(); window.homeBannerPrev && window.homeBannerPrev();" aria-label="Banner tr&#432;&#7899;c">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="home-banner-nav home-banner-next" type="button" data-banner-next onclick="event.stopImmediatePropagation(); window.homeBannerNext && window.homeBannerNext();" aria-label="Banner ti&#7871;p theo">
                <i class="bi bi-chevron-right"></i>
            </button>
            <div class="home-banner-dots" aria-label="Ch&#7885;n banner">
                <?php foreach ($homeBanners as $index => $banner): ?>
                    <button type="button" class="<?php echo $index === 0 ? 'active' : ''; ?>" data-banner-dot="<?php echo (int)$index; ?>" onclick="event.stopImmediatePropagation(); window.homeBannerGo && window.homeBannerGo(<?php echo (int)$index; ?>);" aria-label="Banner <?php echo (int)($index + 1); ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="home-branch-join-section" aria-label="Chọn khu vực tập luyện">
        <div class="container">
            <form class="home-branch-join-card" action="#clubs" method="get">
                <p>FlexZone hiện đang có 15+ chi nhánh trên toàn quốc</p>

                <div class="home-branch-join-control">
                    <select name="city" aria-label="Chọn thành phố">
                        <?php foreach ($homeBranchCities as $city): ?>
                            <option value="<?php echo h($city); ?>">
                                <?php echo h(homeBranchCityLabel($city)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Tham gia</button>
                </div>
            </form>
        </div>
    </section>

    <section class="home-free-trial-section">
        <div class="container">
            <div class="home-free-trial-layout">
                <div class="home-free-trial-image">
                    <img src="<?php echo $base_path; ?>assets/images/mohamed-fareed-rbSNsoXk-3A-unsplash.jpg" alt="7 ngày tập luyện miễn phí tại FLEXZONE">
                </div>

                <div class="home-free-trial-copy">
                    <h2>7 NGÀY TẬP LUYỆN<br>MIỄN PHÍ</h2>
                    <p>
                        FLEXZONE tặng bạn một tuần trải nghiệm miễn phí, với tinh thần
                        “Gym cho mọi người” giúp Hội viên có thể bắt đầu tập luyện một cách tự tin.
                    </p>
                    <a href="<?php echo $base_path; ?>user/package/index" class="home-free-trial-btn">
                        Xem chi tiết
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="why-section">
        <div class="container">
            <div class="why-header">
                <h2>V&igrave; sao ch&#7885;n <span>FLEXZONE</span></h2>
                <p>
                    Khám phá hệ thống thiết bị nhập khẩu, đội ngũ HLV chuyên môn
                    và lộ trình tập luyện cá nhân hóa dành cho hội viên.
                </p>
            </div>

            <div class="why-tabs">
                <div class="why-tab-card active" data-tab="equipment">
                    <div class="why-tab-icon">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <div>
                        <h3>Thiết bị hiện đại</h3>
                        <p>Danh mục máy tập nổi bật từ các thương hiệu quốc tế.</p>
                    </div>
                </div>

                <div class="why-tab-card" data-tab="trainer">
                    <div class="why-tab-icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <h3>HLV đồng hành</h3>
                        <p>Đội ngũ PT theo sát mục tiêu tập luyện của bạn.</p>
                    </div>
                </div>

                <div class="why-tab-card" data-tab="plan">
                    <div class="why-tab-icon">
                        <i class="bi bi-clipboard2-pulse"></i>
                    </div>
                    <div>
                        <h3>Kế hoạch cá nhân</h3>
                        <p>Xây dựng lộ trình tập luyện và dinh dưỡng rõ ràng.</p>
                    </div>
                </div>
            </div>

            <div class="why-detail-layout">
                <div class="why-detail-panel">
                    <h3 class="why-detail-title">Danh sách thiết bị nổi bật</h3>

                    <div class="equipment-tabs">
                        <div class="equipment-tab active" data-filter="cardio">Tim m&#7841;ch</div>
                        <div class="equipment-tab" data-filter="strength">Tăng cơ</div>
                        <div class="equipment-tab" data-filter="functional">Ch&#7913;c n&#259;ng</div>
                    </div>

                    <div class="equipment-grid">
                        <div class="equipment-card" data-category="cardio">
                            <img src="includes/assets/images/equipment/treadmill.jfif"
                                 alt="Máy chạy bộ Life Fitness Integrity+"
                                 class="equipment-img">
                            <div>
                                <h4>Máy chạy bộ<br>Life Fitness Integrity+</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Tim m&#7841;ch b&#7873;n b&#7881; &middot; Nh&#7853;p kh&#7849;u t&#7915; Life Fitness.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="cardio">
                            <img src="includes/assets/images/equipment/technogym-bike.jpg"
                                 alt="Xe đạp Technogym Bike"
                                 class="equipment-img">
                            <div>
                                <h4>Xe đạp<br>Technogym Bike</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Tăng sức bền · Phù hợp khởi động và cardio nhẹ.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="strength">
                            <img src="includes/assets/images/equipment/chest-press.jpg"
                                 alt="Super Incline Chest Press"
                                 class="equipment-img">
                            <div>
                                <h4>Super Incline<br>Chest Press</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Chinh phục vùng ngực trên hiệu quả · Hỗ trợ phát triển cơ ngực.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="strength">
                            <img src="includes/assets/images/equipment/leg-press.jpg"
                                 alt="Leg Press Cybex VR3"
                                 class="equipment-img">
                            <div>
                                <h4>Leg Press<br>Cybex VR3</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Tập chân, mông, đùi · Hỗ trợ tải trọng lớn.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="strength">
                            <img src="includes/assets/images/equipment/chest-press.jpg"
                                 alt="Smith Machine"
                                 class="equipment-img">
                            <div>
                                <h4>Smith Machine<br>Strength Station</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Hỗ trợ squat, bench press, shoulder press · An toàn khi tập nặng.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="functional">
                            <img src="includes/assets/images/equipment/functional-trainer.jpg"
                                 alt="Matrix Functional Trainer"
                                 class="equipment-img">
                            <div>
                                <h4>Matrix<br>Functional Trainer</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Kéo cáp toàn thân · Linh hoạt nhiều nhóm cơ.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="functional">
                            <img src="includes/assets/images/equipment/functional-trainer.jpg"
                                 alt="Cáp kéo đa năng"
                                 class="equipment-img">
                            <div>
                                <h4>Cáp kéo<br>đa năng</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Tập vai, lưng, tay, core · Linh hoạt nhiều góc kéo.</p>
                            </div>
                        </div>

                        <div class="equipment-card" data-category="cardio">
                            <img src="includes/assets/images/equipment/elliptical.jpg"
                                 alt="Precor EFX 863 Elliptical"
                                 class="equipment-img">
                            <div>
                                <h4>Precor EFX 863<br>Elliptical</h4>
                                <div class="equipment-status">Đang hoạt động</div>
                                <p>Tim m&#7841;ch &iacute;t t&aacute;c &#273;&#7897;ng &middot; Ph&ugrave; h&#7907;p gi&#7843;m m&#7905; v&agrave; t&#259;ng s&#7913;c b&#7873;n.</p>
                            </div>
                        </div>
                    </div>

                    <div class="why-button-wrap">
                        <a href="#" class="why-equipment-btn">
                            Xem chi tiết thiết bị
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <aside class="why-side-panel">
                    <h3>Thiết bị & tiêu chuẩn</h3>

                    <div class="why-standard-item">
                        <div class="why-standard-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h4>200+ thiết bị hiện đại</h4>
                            <p>Nhiều dòng máy nhập khẩu, đáp ứng đa dạng mục tiêu tập luyện.</p>
                        </div>
                    </div>

                    <div class="why-standard-item">
                        <div class="why-standard-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <h4>Bảo trì định kỳ</h4>
                            <p>Kiểm tra, vệ sinh và bảo dưỡng thiết bị mỗi tuần.</p>
                        </div>
                    </div>

                    <div class="why-standard-item">
                        <div class="why-standard-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <h4>PT hỗ trợ tại sàn</h4>
                            <p>Luôn có HLV hướng dẫn hội viên khi cần sử dụng máy.</p>
                        </div>
                    </div>

                    <div class="why-standard-item">
                        <div class="why-standard-icon">
                            <i class="bi bi-grid-3x3-gap"></i>
                        </div>
                        <div>
                            <h4>5 khu vực tập luyện</h4>
                            <p>Tim m&#7841;ch, t&#259;ng c&#417;, ch&#7913;c n&#259;ng, l&#7899;p nh&oacute;m v&agrave; ph&#7909;c h&#7891;i.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="home-clubs-section" id="clubs">
        <div class="container">
            <div class="home-clubs-heading">
                <h2>T&igrave;m ph&ograve;ng t&#7853;p</h2>
                <p>FLEXZONE hi&#7879;n c&oacute; <?php echo count($homeBranches); ?> chi nh&aacute;nh &#273;ang ho&#7841;t &#273;&#7897;ng.</p>
            </div>

            <?php if (count($homeBranches) > 0): ?>
                <div class="home-club-filters" aria-label="L&#7885;c chi nh&aacute;nh theo th&agrave;nh ph&#7889;">
                    <button class="home-club-filter active" type="button" data-club-filter="all">T&#7845;t c&#7843;</button>
                    <?php foreach ($homeBranchCities as $city): ?>
                        <button class="home-club-filter" type="button" data-club-filter="<?php echo h($city); ?>">
                            <?php echo h(homeBranchCityLabel($city)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="home-clubs-grid" data-visible-count="4">
                    <?php foreach ($homeBranches as $index => $branch): ?>
                        <?php
                            $branchName = trim((string)($branch['name'] ?? 'FLEXZONE'));
                            $branchCity = trim((string)($branch['city'] ?? ''));
                            $branchAddress = trim((string)($branch['address'] ?? ''));
                            $branchImage = homeBranchImage($branch, (int)$index, $base_path);
                            $branchTitle = strtoupper(homeBranchCityLabel($branchCity !== '' ? $branchCity : $branchName));
                            $branchHref = $base_path . 'user/branches/detail.php?id=' . (int)($branch['id'] ?? 0);
                        ?>
                        <article class="home-club-card" data-club-city="<?php echo h($branchCity); ?>" data-club-index="<?php echo (int)$index; ?>">
                            <div class="home-club-image">
                                <img src="<?php echo h($branchImage); ?>" alt="<?php echo h($branchName); ?>">
                            </div>

                            <div class="home-club-info">
                                <h3><?php echo h($branchTitle); ?></h3>

                                <p class="home-club-address">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <span><?php echo h($branchAddress !== '' ? $branchAddress : $branchName); ?></span>
                                </p>

                                <a class="home-club-more" href="<?php echo h($branchHref); ?>">
                                    Xem th&ecirc;m
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (count($homeBranches) > 4): ?>
                    <div class="home-club-load-wrap">
                        <button class="home-club-load" type="button">Xem th&ecirc;m</button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="home-clubs-empty">
                    <i class="bi bi-buildings"></i>
                    <h3>Ch&#432;a c&oacute; chi nh&aacute;nh</h3>
                    <p>Vui l&ograve;ng th&ecirc;m d&#7919; li&#7879;u chi nh&aacute;nh trong b&#7843;ng <code>branches</code>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section-soft home-workflow-section">
        <div class="container">
            <div class="section-heading-split">
                <div>
                    <span class="section-badge">H&agrave;nh tr&igrave;nh h&#7897;i vi&ecirc;n</span>
                    <h2 class="section-title">Tr&#7843;i nghi&#7879;m h&#7897;i vi&ecirc;n <span class="accent">li&#7873;n m&#7841;ch</span></h2>
                </div>
                <p class="section-text">
                    FLEXZONE k&#7871;t n&#7889;i quy tr&igrave;nh t&#7915; xem g&oacute;i t&#7853;p, &#273;&#259;ng k&yacute; t&#432; v&#7845;n, thanh to&aacute;n, check-in &#273;&#7871;n theo d&otilde;i ti&#7871;n &#273;&#7897; trong khu v&#7921;c h&#7897;i vi&ecirc;n.
                </p>
            </div>

            <div class="workflow-grid">
                <div class="workflow-card">
                    <div class="workflow-icon"><i class="bi bi-box-seam"></i></div>
                    <span>01</span>
                    <h5>Xem v&agrave; ch&#7885;n g&oacute;i t&#7853;p</h5>
                    <p>G&oacute;i t&#7853;p hi&#7875;n th&#7883; theo d&#7919; li&#7879;u admin, gi&uacute;p h&#7897;i vi&ecirc;n n&#7855;m r&otilde; th&#7901;i h&#7841;n, gi&aacute; v&agrave; quy&#7873;n l&#7907;i.</p>
                </div>

                <div class="workflow-card">
                    <div class="workflow-icon"><i class="bi bi-card-checklist"></i></div>
                    <span>02</span>
                    <h5>&#272;&#259;ng k&yacute; t&#432; v&#7845;n</h5>
                    <p>Kh&aacute;ch h&agrave;ng g&#7917;i th&ocirc;ng tin, admin ti&#7871;p nh&#7853;n v&agrave; theo d&otilde;i tr&#7841;ng th&aacute;i &#273;&#259;ng k&yacute; ngay trong h&#7879; th&#7889;ng.</p>
                </div>

                <div class="workflow-card">
                    <div class="workflow-icon"><i class="bi bi-qr-code-scan"></i></div>
                    <span>03</span>
                    <h5>Check-in nhanh</h5>
                    <p>H&#7897;i vi&ecirc;n c&oacute; th&#7875; theo d&otilde;i l&#7883;ch s&#7917; check-in, tr&#7841;ng th&aacute;i g&oacute;i v&agrave; s&#7889; bu&#7893;i t&#7853;p &#273;&atilde; tham gia.</p>
                </div>

                <div class="workflow-card">
                    <div class="workflow-icon"><i class="bi bi-stars"></i></div>
                    <span>04</span>
                    <h5>K&#7871; ho&#7841;ch AI</h5>
                    <p>H&#7879; th&#7889;ng h&#7895; tr&#7907; t&#7841;o g&#7907;i &yacute; l&#7883;ch t&#7853;p v&agrave; th&#7921;c &#273;&#417;n theo m&#7909;c ti&ecirc;u c&aacute; nh&acirc;n.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-dark home-program-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">H&#7895; tr&#7907; t&#7853;p luy&#7879;n</span>
                <h2 class="section-title">H&#7895; tr&#7907; ch&#7871; &#273;&#7897; <span class="accent">t&#7853;p luy&#7879;n &amp; dinh d&#432;&#7905;ng</span></h2>
                <p class="section-text mx-auto">
                    T&#7915; ng&#432;&#7901;i m&#7899;i b&#7855;t &#273;&#7847;u &#273;&#7871;n h&#7897;i vi&ecirc;n c&oacute; m&#7909;c ti&ecirc;u n&acirc;ng cao, FLEXZONE h&#7895; tr&#7907; l&#7897; tr&igrave;nh ph&ugrave; h&#7907;p v&#7899;i th&#7875; tr&#7841;ng v&agrave; l&#7883;ch sinh ho&#7841;t.
                </p>
            </div>

            <div class="program-grid">
                <div class="program-card">
                    <i class="bi bi-trophy"></i>
                    <h5>T&#259;ng c&#417;</h5>
                    <p>L&#7883;ch t&#7853;p s&#7913;c m&#7841;nh, chia nh&oacute;m c&#417;, theo d&otilde;i t&#7843;i t&#7853;p v&agrave; ph&#7909;c h&#7891;i.</p>
                </div>

                <div class="program-card">
                    <i class="bi bi-fire"></i>
                    <h5>Gi&#7843;m m&#7905;</h5>
                    <p>K&#7871;t h&#7907;p cardio, resistance training v&agrave; th&#7921;c &#273;&#417;n ki&#7875;m so&aacute;t n&#259;ng l&#432;&#7907;ng.</p>
                </div>

                <div class="program-card">
                    <i class="bi bi-heart-pulse"></i>
                    <h5>C&#7843;i thi&#7879;n s&#7913;c kh&#7887;e</h5>
                    <p>Ch&#7871; &#273;&#7897; t&#7853;p v&#7915;a s&#7913;c, duy tr&igrave; th&oacute;i quen v&#7853;n &#273;&#7897;ng v&agrave; theo d&otilde;i ti&#7871;n &#273;&#7897; h&#7857;ng tu&#7847;n.</p>
                </div>

                <div class="program-card">
                    <i class="bi bi-cup-hot"></i>
                    <h5>Dinh d&#432;&#7905;ng</h5>
                    <p>G&#7907;i &yacute; b&#7919;a &#259;n theo m&#7909;c ti&ecirc;u, s&#7889; b&#7919;a/ng&agrave;y v&agrave; ghi ch&uacute; s&#7913;c kh&#7887;e c&aacute; nh&acirc;n.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="packages-list-section home-packages-section" id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">G&oacute;i h&#7897;i vi&ecirc;n</span>
                <h2 class="section-title">Ch&#7885;n g&oacute;i t&#7853;p <span class="accent">ph&ugrave; h&#7907;p v&#7899;i b&#7841;n</span></h2>
                <p class="section-text mx-auto">
                    G&oacute;i t&#7853;p &#273;&#432;&#7907;c l&#7845;y tr&#7921;c ti&#7871;p t&#7915; trang qu&#7843;n tr&#7883;, hi&#7875;n th&#7883; &#273;&#7847;y &#273;&#7911; gi&aacute;, th&#7901;i h&#7841;n, quy&#7873;n l&#7907;i v&agrave; n&uacute;t &#273;&#259;ng k&yacute; nhanh.
                </p>
            </div>

            <?php if (count($homePackages) > 0): ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($homePackages as $index => $package): ?>
                        <?php
                            $packageId = (int)($package['id'] ?? 0);
                            $packageName = $package['name'] ?? $package['package_name'] ?? 'G&oacute;i t&#7853;p';
                            $price = $package['price'] ?? $package['package_price'] ?? 0;
                            $duration = $package['duration_months'] ?? $package['duration'] ?? $package['duration_in_months'] ?? 0;
                            $tier = homePackageTier($price, $homePackagePricePoints);
                            $tag = homePackageTag($tier);
                            $summary = homePackageSummary($package, $tier);
                            $benefits = homePackageBenefitsPro($package, $homePackagePricePoints);
                        ?>
                        <div class="col-lg-4 col-md-6 d-flex">
                            <article class="package-card-pro <?php echo $index === 1 ? 'featured' : ''; ?> w-100">
                                <div class="package-image-box">
                                    <img src="<?php echo h(homePackageImage($package, (int)$index, $base_path)); ?>" alt="<?php echo h(html_entity_decode((string)$packageName, ENT_QUOTES, 'UTF-8')); ?>">
                                    <span class="package-tag <?php echo h($tag['class']); ?>">
                                        <?php echo h(html_entity_decode($tag['text'], ENT_QUOTES, 'UTF-8')); ?>
                                    </span>
                                </div>

                                <div class="package-card-content">
                                    <div class="package-name-row">
                                        <h3><?php echo h(html_entity_decode((string)$packageName, ENT_QUOTES, 'UTF-8')); ?></h3>
                                        <div class="package-price-pro">
                                            <?php echo homePackagePrice($price); ?>
                                        </div>
                                    </div>

                                    <div class="package-duration">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Th&#7901;i h&#7841;n: <?php echo h(html_entity_decode(homePackageDuration($duration), ENT_QUOTES, 'UTF-8')); ?></span>
                                    </div>

                                    <p class="package-summary-text">
                                        <?php echo h(html_entity_decode($summary, ENT_QUOTES, 'UTF-8')); ?>
                                    </p>

                                    <ul class="package-benefit-list">
                                        <?php foreach ($benefits as $benefit): ?>
                                            <li>
                                                <i class="bi bi-check2"></i>
                                                <span><?php echo h(html_entity_decode($benefit, ENT_QUOTES, 'UTF-8')); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <div class="package-card-actions">
                                        <a href="<?php echo $base_path; ?>user/package/detail.php?id=<?php echo $packageId; ?>" class="btn-package-outline">
                                            Chi ti&#7871;t g&oacute;i
                                        </a>
                                        <a href="<?php echo $base_path; ?>user/package/register?package_id=<?php echo $packageId; ?>" class="btn-package-primary">
                                            &#272;&#259;ng k&yacute; g&oacute;i n&agrave;y
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="package-trust-strip">
                    <div class="trust-item">
                        <div class="trust-icon"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <strong>D&#7909;ng c&#7909; ch&#7845;t l&#432;&#7907;ng</strong>
                            <span>Thi&#7871;t b&#7883; hi&#7879;n &#273;&#7841;i, an to&agrave;n</span>
                        </div>
                    </div>

                    <div class="trust-item">
                        <div class="trust-icon"><i class="bi bi-person-workspace"></i></div>
                        <div>
                            <strong>HLV chuy&ecirc;n nghi&#7879;p</strong>
                            <span>&#272;&#7891;ng h&agrave;nh c&ugrave;ng h&#7897;i vi&ecirc;n</span>
                        </div>
                    </div>

                    <div class="trust-item">
                        <div class="trust-icon"><i class="bi bi-headset"></i></div>
                        <div>
                            <strong>H&#7895; tr&#7907; t&#7853;n t&acirc;m</strong>
                            <span>T&#432; v&#7845;n g&oacute;i t&#7853;p ph&ugrave; h&#7907;p</span>
                        </div>
                    </div>

                    <div class="trust-item">
                        <div class="trust-icon"><i class="bi bi-calendar2-week"></i></div>
                        <div>
                            <strong>Linh ho&#7841;t th&#7901;i gian</strong>
                            <span>Nhi&#7873;u l&#7921;a ch&#7885;n theo nhu c&#7847;u</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-package-pro text-center">
                    <div class="display-5 mb-3 text-info">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h4 class="mb-3">Ch&#432;a c&oacute; g&oacute;i t&#7853;p</h4>
                    <p class="text-secondary mb-0">
                        Hi&#7879;n ch&#432;a c&oacute; d&#7919; li&#7879;u g&oacute;i t&#7853;p &#273;&#7875; hi&#7875;n th&#7883;. Vui l&ograve;ng th&ecirc;m g&oacute;i t&#7853;p trong trang qu&#7843;n tr&#7883;.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section-dark" id="gallery">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Th&#432; vi&#7879;n <span class="accent">h&igrave;nh &#7843;nh</span></h2>
                <p class="section-text mx-auto">
                    Kh&ocirc;ng gian t&#7853;p luy&#7879;n hi&#7879;n &#273;&#7841;i, s&#7841;ch s&#7869;, thi&#7871;t k&#7871; m&#7841;nh m&#7869; v&agrave; t&#7841;o &#273;&#7897;ng l&#7921;c cho ng&#432;&#7901;i t&#7853;p m&#7895;i ng&agrave;y.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3"><div class="gallery-card"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card two"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card three"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card four"></div></div>
            </div>
        </div>
    </section>

    <section class="section-soft" id="trainers">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">G&#7863;p g&#7905; <span class="accent">hu&#7845;n luy&#7879;n vi&ecirc;n</span></h2>
                <p class="section-text mx-auto">
                    &#272;&#7897;i ng&#361; hu&#7845;n luy&#7879;n vi&ecirc;n h&#7895; tr&#7907; h&#7885;c vi&ecirc;n t&#7915; ng&#432;&#7901;i m&#7899;i b&#7855;t &#273;&#7847;u &#273;&#7871;n ng&#432;&#7901;i c&oacute; m&#7909;c ti&ecirc;u n&acirc;ng cao th&#7875; h&igrave;nh v&agrave; s&#7913;c b&#7873;n.
                </p>
            </div>

            <div class="row g-4">
                <?php if (!empty($homeTrainers)): ?>
                    <?php foreach ($homeTrainers as $trainer): ?>
                        <?php
                            $trainerId = (int) ($trainer['id'] ?? 0);
                            $trainerName = trim((string) ($trainer['full_name'] ?? 'HLV FLEXZONE'));
                            $trainerBio = trim((string) ($trainer['bio'] ?? 'Huấn luyện viên đồng hành cùng hội viên theo mục tiêu tập luyện cụ thể.'));
                            $trainerSpecialty = trim((string) ($trainer['specialty'] ?? 'Huấn luyện cá nhân'));
                            $trainerRating = number_format((float) ($trainer['rating'] ?? 0), 1);
                            $trainerExperience = max(0, (int) ($trainer['experience_years'] ?? 0));
                            $trainerAvatar = homeTrainerAvatar($trainerId, $trainer['avatar'] ?? '', $base_path);
                        ?>
                        <div class="col-lg-4 col-md-6">
                            <article class="home-trainer-card">
                                <div class="home-trainer-avatar">
                                    <img src="<?php echo h($trainerAvatar); ?>" alt="<?php echo h($trainerName); ?>">
                                </div>

                                <div class="home-trainer-body">
                                    <h3 class="home-trainer-name"><?php echo h($trainerName); ?></h3>
                                    <p class="home-trainer-specialty"><?php echo h($trainerSpecialty); ?></p>
                                    <p class="home-trainer-bio"><?php echo h($trainerBio); ?></p>

                                    <div class="home-trainer-stats">
                                        <span><i class="bi bi-briefcase"></i><?php echo $trainerExperience; ?> năm kinh nghiệm</span>
                                        <span><i class="bi bi-star-fill"></i><?php echo h($trainerRating); ?>/5 đánh giá</span>
                                    </div>

                                    <div class="home-trainer-actions">
                                        <a href="<?php echo $base_path; ?>user/trainers/detail.php?id=<?php echo $trainerId; ?>" class="home-trainer-btn home-trainer-btn-outline">Xem hồ sơ</a>
                                        <a href="<?php echo $base_path; ?>user/trainers/my-bookings.php" class="home-trainer-btn home-trainer-btn-primary">Đặt HLV</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="info-card text-center">
                            <h3 class="card-title-user mb-3">Đội ngũ HLV đang được cập nhật</h3>
                            <p class="card-text-user mb-4">Bạn vẫn có thể xem danh sách huấn luyện viên và gửi yêu cầu tư vấn trực tiếp.</p>
                            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                                <a href="<?php echo $base_path; ?>user/trainers/index.php" class="btn btn-outline-light">Xem danh sách HLV</a>
                                <a href="<?php echo $base_path; ?>user/trainers/my-bookings.php" class="btn btn-hero-primary">Đặt HLV</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/assets/js/why-choose.js?v=why-choose-2"></script>
    <script>
        (() => {
            const banner = document.querySelector('[data-home-banner]');
            if (!banner) {
                return;
            }

            const track = banner.querySelector('.home-banner-track');
            const slides = Array.from(banner.querySelectorAll('[data-banner-slide]'));
            const dots = Array.from(banner.querySelectorAll('[data-banner-dot]'));
            const prev = banner.querySelector('[data-banner-prev]');
            const next = banner.querySelector('[data-banner-next]');
            let activeIndex = 0;
            let timer = null;

            const showSlide = (index) => {
                if (slides.length === 0) {
                    return;
                }

                activeIndex = (index + slides.length) % slides.length;

                if (track) {
                    track.style.transform = `translateX(-${activeIndex * 100}%)`;
                }

                slides.forEach((slide, slideIndex) => {
                    const isActive = slideIndex === activeIndex;
                    slide.classList.toggle('active', isActive);
                    slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                });

                dots.forEach((dot, dotIndex) => {
                    dot.classList.toggle('active', dotIndex === activeIndex);
                });
            };

            const startTimer = () => {
                if (slides.length <= 1) {
                    return;
                }

                window.clearInterval(timer);
                timer = window.setInterval(() => showSlide(activeIndex + 1), 5000);
            };

            window.homeBannerGo = (index) => {
                showSlide(Number(index || 0));
                startTimer();
            };

            window.homeBannerPrev = () => {
                showSlide(activeIndex - 1);
                startTimer();
            };

            window.homeBannerNext = () => {
                showSlide(activeIndex + 1);
                startTimer();
            };

            dots.forEach((dot) => {
                dot.addEventListener('click', () => {
                    showSlide(Number(dot.dataset.bannerDot || 0));
                    startTimer();
                });
            });

            if (prev) {
                prev.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.homeBannerPrev();
                });
            }

            if (next) {
                next.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.homeBannerNext();
                });
            }

            showSlide(0);
            startTimer();
        })();

        (() => {
            const grid = document.querySelector('.home-clubs-grid');
            const joinForm = document.querySelector('.home-branch-join-card');
            const joinCitySelect = joinForm ? joinForm.querySelector('select[name="city"]') : null;

            if (!grid) {
                return;
            }

            const cards = Array.from(grid.querySelectorAll('.home-club-card'));
            const filters = Array.from(document.querySelectorAll('.home-club-filter'));
            const loadButton = document.querySelector('.home-club-load');
            const pageSize = Number(grid.dataset.visibleCount || 4);
            let activeCity = 'all';
            let visibleCount = pageSize;

            const applyClubState = () => {
                let matchedCount = 0;
                let shownCount = 0;

                cards.forEach((card) => {
                    const city = card.dataset.clubCity || '';
                    const matches = activeCity === 'all' || city === activeCity;
                    const shouldShow = matches && shownCount < visibleCount;

                    if (matches) {
                        matchedCount += 1;
                    }

                    if (shouldShow) {
                        shownCount += 1;
                    }

                    card.hidden = !shouldShow;
                });

                if (loadButton) {
                    loadButton.hidden = matchedCount <= visibleCount;
                }
            };

            filters.forEach((filter) => {
                filter.addEventListener('click', () => {
                    activeCity = filter.dataset.clubFilter || 'all';
                    visibleCount = pageSize;

                    filters.forEach((item) => item.classList.toggle('active', item === filter));
                    applyClubState();
                });
            });

            if (loadButton) {
                loadButton.addEventListener('click', () => {
                    visibleCount += pageSize;
                    applyClubState();
                });
            }

            if (joinForm && joinCitySelect) {
                joinForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const selectedCity = joinCitySelect.value || 'all';
                    const targetFilter = filters.find((filter) => (filter.dataset.clubFilter || '') === selectedCity);

                    if (targetFilter) {
                        activeCity = selectedCity;
                        visibleCount = pageSize;
                        filters.forEach((item) => item.classList.toggle('active', item === targetFilter));
                        applyClubState();
                    }

                    document.getElementById('clubs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            applyClubState();
        })();
    </script>
</body>
</html>
