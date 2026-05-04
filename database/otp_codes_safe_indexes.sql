-- otp_codes_safe_indexes.sql
-- Tác dụng: thêm INDEX nếu chưa tồn tại và tạo event cleanup (idempotent)
-- Chạy an toàn (không thay đổi cấu trúc cột NOT NULL)

-- Kiểm tra index hiện có (tùy chọn, bạn có thể bỏ qua)
SHOW INDEX FROM otp_codes;

-- Tạo index idx_otp_user nếu chưa có
SET @cnt := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'otp_codes'
    AND INDEX_NAME = 'idx_otp_user'
);
SET @sql := IF(@cnt = 0, 'CREATE INDEX idx_otp_user ON otp_codes (user_id);', 'SELECT "idx_otp_user_already_exists";');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tạo index idx_otp_user_code_used nếu chưa có
SET @cnt2 := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'otp_codes'
    AND INDEX_NAME = 'idx_otp_user_code_used'
);
SET @sql2 := IF(@cnt2 = 0, 'CREATE INDEX idx_otp_user_code_used ON otp_codes (user_id, otp_code, is_used);', 'SELECT "idx_otp_user_code_used_already_exists";');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Tạo event dọn dẹp OTP đã hết hạn/đã dùng (idempotent)
CREATE EVENT IF NOT EXISTS ev_cleanup_otp_codes
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM otp_codes WHERE expires_at < NOW() AND is_used = 1;

-- Xong

-- Lưu ý:
-- - Nếu bạn muốn xóa index cũ trước khi tạo lại: DROP INDEX idx_otp_user ON otp_codes;
-- - Kiểm tra NULL trong cột trước khi chạy phần ALTER TABLE từ file migration ban đầu.
