<?php

require_once __DIR__ . '/package-image-helper.php';

function getPackageFallbackImages(): array
{
    return [
        '../../assets/images/ambitious-studio-rick-barrett-1RNQ11ZODJM-unsplash.jpg',
        '../../assets/images/brett-jordan-U2q73PfHFpM-unsplash.jpg',
        '../../assets/images/mohamed-fareed-rbSNsoXk-3A-unsplash.jpg',
    ];
}

function getPackageImageUrl(array $package, string $basePath, int $fallbackIndex = 0): string
{
    $fallbacks = getPackageFallbackImages();
    $fallback = $fallbacks[$fallbackIndex % count($fallbacks)];
    return resolve_package_image_url($package['image'] ?? '', $basePath, $fallback);
}

function getActivePackages(mysqli $conn): array
{
    $items = [];

    $sql = "SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, image, status
            FROM packages
            WHERE status = 'active'
            ORDER BY price ASC, duration_months ASC, id ASC";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    return $items;
}

function getPackageById(mysqli $conn, int $id): ?array
{
    $sql = "SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, image, status
            FROM packages
            WHERE id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    return $item ?: null;
}

function formatPriceVn(float $price): string
{
    return number_format($price, 0, ',', '.') . 'đ';
}
