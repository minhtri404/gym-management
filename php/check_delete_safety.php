<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $member_id = 15; // Le Thanh
    $package_id = 7; // Gói Thử Nghiệm 7 Ngày
    
    echo "📝 BẠN CÓ THỂ XÓA GÓI ĐĂNG KÝ KHÔNG?<br><br>";
    
    // 1. Đăng ký lại gói
    $registration_date = date('Y-m-d H:i:s');
    $expiration_date = date('Y-m-d', strtotime('+7 days')); // 7 ngày từ bây giờ
    
    $sql = "INSERT INTO member_packages (member_id, package_id, registration_date, expiration_date, status) 
            VALUES ($member_id, $package_id, '$registration_date', '$expiration_date', 'active')";
    
    if ($conn->query($sql) === TRUE) {
        $registration_id = $conn->insert_id;
        echo "✅ Đã đăng ký lại gói 7 ngày cho Le Thanh<br>";
        echo "- Registration ID: $registration_id<br>";
        echo "- Ngày đăng ký: $registration_date<br>";
        echo "- Hết hạn: $expiration_date<br><br>";
    } else {
        throw new Exception($conn->error);
    }
    
    // 2. Kiểm tra dữ liệu liên quan
    echo "🔍 KIỂM TRA DỮ LIỆU LIÊN QUAN:<br><br>";
    
    // Kiểm tra checkins
    $checkins = $conn->query("SELECT COUNT(*) as count FROM checkins WHERE member_id = $member_id");
    $checkin_count = $checkins->fetch_assoc()['count'];
    echo "• Checkins (Check-in, tập luyện): " . ($checkin_count > 0 ? "❌ Có $checkin_count - XÓA GÓI SẼ MẤT LỊCH SỬ TẬP" : "✅ Không có") . "<br>";
    
    // Kiểm tra trainer bookings
    $bookings = $conn->query("SELECT COUNT(*) as count FROM trainer_bookings WHERE member_id = $member_id");
    $booking_count = $bookings->fetch_assoc()['count'];
    echo "• Trainer Bookings (Đặt HLV): " . ($booking_count > 0 ? "❌ Có $booking_count - XÓA GÓI SẼ MẤT LỊCH ĐẶT" : "✅ Không có") . "<br>";
    
    // Kiểm tra workout plans
    $workouts = $conn->query("SELECT COUNT(*) as count FROM ai_workout_plans WHERE member_id = $member_id");
    $workout_count = $workouts->fetch_assoc()['count'];
    echo "• Workout Plans (Kế hoạch tập): " . ($workout_count > 0 ? "❌ Có $workout_count - XÓA GÓI SẼ MẤT KẾ HOẠCH" : "✅ Không có") . "<br>";
    
    // Kiểm tra meal plans
    $meals = $conn->query("SELECT COUNT(*) as count FROM ai_meal_plans WHERE member_id = $member_id");
    $meal_count = $meals->fetch_assoc()['count'];
    echo "• Meal Plans (Kế hoạch ăn): " . ($meal_count > 0 ? "❌ Có $meal_count - XÓA GÓI SẼ MẤT KẾ HOẠCH" : "✅ Không có") . "<br>";
    
    // Kiểm tra package history
    $history = $conn->query("SELECT COUNT(*) as count FROM member_package_history WHERE member_id = $member_id AND package_id = $package_id");
    $history_count = $history->fetch_assoc()['count'];
    echo "• Package History (Lịch sử gói): " . ($history_count > 0 ? "⚠️ Có $history_count - XÓA GÓI SẼ MẤT LỊCH SỬ" : "✅ Không có") . "<br>";
    
    echo "<br>💡 KẾT LUẬN:<br>";
    if ($checkin_count > 0 || $workout_count > 0 || $meal_count > 0) {
        echo "⚠️ KHÔNG NÊN XÓA GÓI này vì còn dữ liệu liên quan!<br>";
        echo "- Nếu xóa sẽ mất lịch sử tập luyện, kế hoạch tập/ăn<br>";
        echo "- KHUYẾN NGHỊ: Chỉ hủy/đặt lại expiration_date thay vì xóa<br>";
    } else {
        echo "✅ CÓ THỂ XÓA ĐƯỢC vì không còn dữ liệu quan trọng liên quan<br>";
    }
    
    echo "<br>🛠️ CÁC CÁCH XỬ LÝ:<br>";
    echo "1️⃣ XÓA TOÀN BỘ: DELETE FROM member_packages (mất toàn bộ lịch sử)<br>";
    echo "2️⃣ HỦY GÓI: UPDATE member_packages SET status='cancelled' (giữ lịch sử)<br>";
    echo "3️⃣ HẠNG HẠN: UPDATE member_packages SET expiration_date=NOW() (gói hết hạn ngay)<br>";

} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

$conn->close();
?>
