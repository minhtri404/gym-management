<?php
require_once __DIR__ . '/../includes/config.php';

try {
    // Tạo gói 7 ngày
    $sql_insert = "INSERT INTO packages (package_name, price, duration_months, description, package_type, duration_days, trial_once_per_user, status, created_at)
    VALUES ('Gói Thử Nghiệm 7 Ngày', 0, 0, 'Gói thử nghiệm miễn phí 7 ngày', 'free_trial', 7, 1, 'active', NOW())";
    
    if ($conn->query($sql_insert) === TRUE) {
        $last_id = $conn->insert_id;
        echo "✅ Gói 7 ngày đã được tạo thành công!<br>";
        echo "   - ID: $last_id<br>";
        echo "   - Tên: Gói Thử Nghiệm 7 Ngày<br>";
        echo "   - Loại: Thử nghiệm miễn phí<br>";
        echo "   - Thời gian: 7 ngày<br>";
        echo "   - Giá: 0 VND<br>";
        echo "   - Chỉ dùng 1 lần/user: Có<br>";
    } else {
        throw new Exception("Lỗi khi tạo gói: " . $conn->error);
    }

    // Hiển thị các gói hiện có
    echo "<br>📋 Danh sách các gói hiện có:<br>";
    $result = $conn->query("SELECT id, package_name, price, duration_months, package_type, duration_days FROM packages ORDER BY id DESC LIMIT 10");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $type = ($row['package_type'] === 'free_trial') ? '🎁 Thử nghiệm' : '💰 Trả phí';
            echo "- ID {$row['id']}: {$row['package_name']} - $type - Giá: {$row['price']} VND<br>";
        }
    }

} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

$conn->close();
?>
