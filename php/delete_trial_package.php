<?php
require_once __DIR__ . '/../includes/config.php';

try {
    // Xóa registration gói 7 ngày cho Le Thanh
    $member_id = 15; // ID #015
    $package_id = 7; // Gói Thử Nghiệm 7 Ngày
    
    // Kiểm tra trước
    $check = $conn->query("SELECT * FROM member_packages WHERE member_id = $member_id AND package_id = $package_id");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        echo "📋 Thông tin gói cần xóa:<br>";
        echo "- Hội viên ID: $member_id<br>";
        echo "- Gói ID: $package_id<br>";
        echo "- Ngày đăng ký: " . $row['registration_date'] . "<br>";
        echo "- Ngày hết hạn: " . $row['expiration_date'] . "<br><br>";
    }
    
    // Xóa từ member_package_history nếu có
    $stmt = $conn->prepare('DELETE FROM member_package_history WHERE member_id = ? AND package_id = ?');
    $stmt->bind_param('ii', $member_id, $package_id);
    $stmt->execute();
    $stmt->close();
    
    // Xóa từ member_packages
    $stmt = $conn->prepare('DELETE FROM member_packages WHERE member_id = ? AND package_id = ?');
    $stmt->bind_param('ii', $member_id, $package_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    if ($deleted > 0) {
        echo "✅ Đã xóa gói 7 ngày cho Le Thanh thành công!<br>";
        echo "- Số bản ghi xóa: $deleted<br>";
    } else {
        echo "⚠️ Không tìm thấy gói 7 ngày cho hội viên này<br>";
    }
    
    // Hiển thị gói còn lại
    echo "<br>📦 Các gói còn lại của Le Thanh:<br>";
    $result = $conn->query("SELECT mp.*, p.package_name FROM member_packages mp 
                           JOIN packages p ON mp.package_id = p.id 
                           WHERE mp.member_id = $member_id");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['package_name']} (Hết hạn: {$row['expiration_date']})<br>";
        }
    } else {
        echo "- Không có gói nào<br>";
    }

} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

$conn->close();
?>
