<?php
require_once __DIR__ . '/../includes/config.php';

$member_id = 15; // Le Thanh

echo "🔍 Kiểm tra gói của Le Thanh (ID #015):<br><br>";

$result = $conn->query("SELECT mp.*, p.package_name, p.package_type FROM member_packages mp 
                       JOIN packages p ON mp.package_id = p.id 
                       WHERE mp.member_id = $member_id
                       ORDER BY mp.id DESC");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $type = ($row['package_type'] === 'free_trial') ? '🎁 Thử nghiệm' : '💰 Trả phí';
        echo "- {$row['package_name']} | $type | ID: {$row['id']}<br>";
        echo "  Đăng ký: {$row['registration_date']} | Hết hạn: {$row['expiration_date']}<br>";
        echo "  Status: {$row['status']}<br><br>";
    }
} else {
    echo "✅ Không có gói nào<br>";
}

$conn->close();
?>
