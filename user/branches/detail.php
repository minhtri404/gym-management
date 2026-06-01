<?php
include __DIR__ . '/../../includes/config.php';

$base_path = '../../';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function branchImageUrl(array $branch, int $index, string $basePath): string
{
    $image = trim((string)($branch['image'] ?? ''));

    if ($image !== '') {
        $paths = [
            'uploads/branches/' . $image,
            'assets/images/branches/' . $image,
            'user/includes/assets/images/branches/' . $image,
        ];

        foreach ($paths as $path) {
            if (is_file(__DIR__ . '/../../' . $path)) {
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

function branchDisplayName(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/^FLEXZONE\s+/i', '', $name);
    return $name !== '' ? $name : 'FLEXZONE';
}

$branchId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($branchId <= 0) {
    header('Location: ' . $base_path . 'user/home.php#clubs');
    exit;
}

$stmt = $conn->prepare("
    SELECT id, name, city, address, map_url, schedule_url, image, description, status
    FROM branches
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");

$stmt->bind_param('i', $branchId);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    header('Location: ' . $base_path . 'user/home.php#clubs');
    exit;
}

$branchName = trim((string)($branch['name'] ?? 'FLEXZONE'));
$branchShortName = branchDisplayName($branchName);
$branchAddress = trim((string)($branch['address'] ?? ''));
$branchCity = trim((string)($branch['city'] ?? ''));
$branchDescription = trim((string)($branch['description'] ?? ''));
$branchMapUrl = trim((string)($branch['map_url'] ?? ''));
$branchScheduleUrl = trim((string)($branch['schedule_url'] ?? ''));
$mainImage = branchImageUrl($branch, max(0, $branchId - 1), $base_path);
$galleryImages = array_values(array_unique([
    $mainImage,
    $base_path . 'assets/images/mohamed-fareed-rbSNsoXk-3A-unsplash.jpg',
    $base_path . 'assets/images/ambitious-studio-rick-barrett-1RNQ11ZODJM-unsplash.jpg',
    $base_path . 'assets/images/brett-jordan-U2q73PfHFpM-unsplash.jpg',
]));
$galleryImages = array_slice($galleryImages, 0, 3);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($branchName); ?> - FLEXZONE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../includes/assets/css/user.css?v=light-1">
</head>
<body class="user-body">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="branch-detail-page">
        <section class="branch-detail-section">
            <div class="container">
                <div class="branch-detail-breadcrumb">
                    <a href="<?php echo $base_path; ?>user/home.php">Home</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?php echo $base_path; ?>user/home.php#clubs">Find a Club</a>
                    <i class="bi bi-chevron-right"></i>
                    <span><?php echo h($branchShortName); ?></span>
                </div>

                <div class="branch-detail-layout">
                    <div class="branch-detail-copy">
                        <span class="branch-detail-kicker">FLEXZONE CLUB</span>

                        <h1>
                            FLEXZONE<br>
                            <span><?php echo h(strtoupper($branchShortName)); ?></span>
                        </h1>

                        <div class="branch-info-list">
                            <div class="branch-info-row">
                                <i class="bi bi-people-fill"></i>
                                <span><?php echo h($branchName); ?></span>
                            </div>

                            <div class="branch-info-row">
                                <i class="bi bi-geo-alt"></i>
                                <span><?php echo h($branchAddress !== '' ? $branchAddress : $branchCity); ?></span>
                            </div>

                            <a class="branch-info-row branch-info-link" href="<?php echo h($branchMapUrl !== '' && $branchMapUrl !== '#' ? $branchMapUrl : 'https://maps.google.com/?q=' . rawurlencode($branchAddress !== '' ? $branchAddress : $branchName)); ?>" target="_blank" rel="noopener">
                                <i class="bi bi-arrow-right"></i>
                                <span>View map</span>
                            </a>

                            <div class="branch-info-row">
                                <i class="bi bi-clock"></i>
                                <span>Open 24/7</span>
                            </div>

                            <a class="branch-info-row branch-info-link" href="<?php echo h($branchScheduleUrl !== '' && $branchScheduleUrl !== '#' ? $branchScheduleUrl : $base_path . 'user/plans/index.php'); ?>">
                                <i class="bi bi-calendar3"></i>
                                <span>Class schedule</span>
                            </a>

                            <a class="branch-info-row branch-info-link" href="<?php echo $base_path; ?>feedback.php">
                                <i class="bi bi-star"></i>
                                <span>Evaluate</span>
                            </a>
                        </div>

                        <?php if ($branchDescription !== ''): ?>
                            <p class="branch-detail-description">
                                <?php echo h($branchDescription); ?>
                            </p>
                        <?php endif; ?>

                        <a href="<?php echo $base_path; ?>contact-form.php" class="branch-join-btn">Join now</a>
                    </div>

                    <div class="branch-gallery" data-branch-gallery>
                        <div class="branch-main-image">
                            <img src="<?php echo h($mainImage); ?>" alt="<?php echo h($branchName); ?>" data-branch-main-image>
                        </div>

                        <div class="branch-thumb-row">
                            <button class="branch-gallery-arrow" type="button" data-gallery-prev aria-label="Previous image">
                                <i class="bi bi-chevron-left"></i>
                            </button>

                            <div class="branch-thumbs">
                                <?php foreach ($galleryImages as $imageIndex => $image): ?>
                                    <button class="branch-thumb <?php echo $imageIndex === 0 ? 'active' : ''; ?>" type="button" data-gallery-image="<?php echo h($image); ?>">
                                        <img src="<?php echo h($image); ?>" alt="<?php echo h($branchName . ' ' . ($imageIndex + 1)); ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <button class="branch-gallery-arrow" type="button" data-gallery-next aria-label="Next image">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const gallery = document.querySelector('[data-branch-gallery]');

            if (!gallery) {
                return;
            }

            const mainImage = gallery.querySelector('[data-branch-main-image]');
            const thumbs = Array.from(gallery.querySelectorAll('.branch-thumb'));
            const previousButton = gallery.querySelector('[data-gallery-prev]');
            const nextButton = gallery.querySelector('[data-gallery-next]');
            let currentIndex = 0;

            const setImage = (index) => {
                currentIndex = (index + thumbs.length) % thumbs.length;
                const nextImage = thumbs[currentIndex].dataset.galleryImage || '';

                if (nextImage !== '') {
                    mainImage.src = nextImage;
                }

                thumbs.forEach((thumb, thumbIndex) => {
                    thumb.classList.toggle('active', thumbIndex === currentIndex);
                });
            };

            thumbs.forEach((thumb, index) => {
                thumb.addEventListener('click', () => setImage(index));
            });

            previousButton?.addEventListener('click', () => setImage(currentIndex - 1));
            nextButton?.addEventListener('click', () => setImage(currentIndex + 1));
        })();
    </script>
</body>
</html>
