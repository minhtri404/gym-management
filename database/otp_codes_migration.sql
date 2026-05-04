-- otp_codes_migration.sql
-- Mục đích: chuẩn hóa cấu trúc bảng `otp_codes`, thêm index và tùy chọn FK / cleanup event.
-- Chạy thủ công (ví dụ: phpMyAdmin hoặc mysql CLI) SAU KHI bạn đã kiểm tra dữ liệu.
-- Luôn backup database trước khi thực hiện:
--   mysqldump -u root -p gym_management > gym_management_backup.sql


-- 1) Kiểm tra các hàng có NULL (không chạy thay đổi tự động trước khi bạn review):
SELECT COUNT(*) AS null_user_id FROM otp_codes WHERE user_id IS NULL;
SELECT COUNT(*) AS null_email FROM otp_codes WHERE email IS NULL;
SELECT COUNT(*) AS null_otp_code FROM otp_codes WHERE otp_code IS NULL;
SELECT COUNT(*) AS null_expires_at FROM otp_codes WHERE expires_at IS NULL;

-- Xem các hàng mẫu nếu có NULL
SELECT * FROM otp_codes
WHERE user_id IS NULL OR email IS NULL OR otp_code IS NULL OR expires_at IS NULL
LIMIT 100;

-- 2) Nếu cần sửa NULLs thì sửa thủ công, ví dụ (CHỈ chạy sau khi đã REVIEW):
-- UPDATE otp_codes SET user_id = 0 WHERE user_id IS NULL;
-- UPDATE otp_codes SET email = '' WHERE email IS NULL;
-- UPDATE otp_codes SET otp_code = '' WHERE otp_code IS NULL;
-- UPDATE otp_codes SET expires_at = NOW() WHERE expires_at IS NULL;

-- 3) Thực hiện thay đổi cấu trúc (chạy sau khi bạn đảm bảo không còn NULL không mong muốn):
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE otp_codes
  MODIFY COLUMN user_id INT NOT NULL,
  MODIFY COLUMN email VARCHAR(255) NOT NULL,
  MODIFY COLUMN otp_code VARCHAR(10) NOT NULL,
  MODIFY COLUMN expires_at DATETIME NOT NULL,
  MODIFY COLUMN is_used TINYINT(1) NOT NULL DEFAULT 0;

-- 4) Thêm index để tra cứu nhanh
CREATE INDEX idx_otp_user ON otp_codes (user_id);
CREATE INDEX idx_otp_user_code_used ON otp_codes (user_id, otp_code, is_used);

-- 5) (TÙY CHỌN) Thêm ràng buộc khoá ngoại nếu bạn chắc chắn tất cả user_id hợp lệ
-- Chỉ bật nếu bảng `users` dùng InnoDB và mọi user_id hiện có tồn tại trong `users(id)`:
-- ALTER TABLE otp_codes
--   ADD CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- 6) (TÙY CHỌN) Tạo event dọn OTP đã dùng/đã hết hạn hàng ngày
-- Lưu ý: cần bật event scheduler (`SET GLOBAL event_scheduler = ON;`) và có quyền tạo event.
CREATE EVENT IF NOT EXISTS ev_cleanup_otp_codes
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM otp_codes WHERE expires_at < NOW() AND is_used = 1;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- Kết thúc

-- Ghi chú:
-- - Các lệnh ALTER ... MODIFY sẽ lỗi nếu còn giá trị NULL trong các cột được chuyển sang NOT NULL.
-- - Nếu bạn muốn, tôi có thể tạo một phiên bản chỉ chứa INDEX/EVEN tạo và để phần MODIFY comment lại để bạn chạy an toàn.
-- - Sau khi chạy, kiểm tra hoạt động của login/verify OTP.
