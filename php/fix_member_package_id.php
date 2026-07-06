<?php
require_once __DIR__ . '/../includes/config.php';

$member_id = 15; // Le Thanh

echo "🔧 Cập nhật bảng members:<br><br>";

// Cập nhật package_id trong bảng members thành NULL
$sql = "UPDATE members SET package_id = NULL WHERE id = $member_id";

if ($conn->query($sql) === TRUE) {
    echo "✅ Đã xóa gói khỏi bảng members<br>";
    echo "- Hội viên ID: $member_id<br>";
    echo "- Cột package_id: NULL<br><br>";
} else {
    echo "❌ Lỗi: " . $conn->error . "<br>";
}

// Kiểm tra
$result = $conn->query("SELECT id, full_name, package_id FROM members WHERE id = $member_id");
$row = $result->fetch_assoc();
echo "📋 Kiểm tra:<br>";
echo "- ID: {$row['id']}<br>";
echo "- Tên: {$row['full_name']}<br>";
echo "- Package ID: " . ($row['package_id'] ?? 'NULL') . "<br>";

$conn->close();
?>
