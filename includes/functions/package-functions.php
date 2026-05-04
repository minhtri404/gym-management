<?php

function getActivePackages(mysqli $conn): array
{
    $items = [];

    $sql = "SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, status
            FROM packages
            WHERE status = 'active'
            ORDER BY id DESC";

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
    $sql = "SELECT id, package_name, duration_months, price, description, short_description, detail_content, benefits, suitable_for, status
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