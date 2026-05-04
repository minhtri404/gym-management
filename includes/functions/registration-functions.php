<?php

function findRegistrationsByKeyword(mysqli $conn, string $keyword): array
{
    $items = [];

    $sql = "
        SELECT 
            pr.id,
            pr.full_name,
            pr.phone,
            pr.email,
            pr.status,
            pr.created_at,
            pr.note,
            p.package_name
        FROM package_registrations pr
        LEFT JOIN packages p ON pr.package_id = p.id
        WHERE pr.phone LIKE ? OR pr.email LIKE ?
        ORDER BY pr.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $search = '%' . $keyword . '%';
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();

    return $items;
}