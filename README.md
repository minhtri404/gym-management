# FLEXZONE Gym Management

Hệ thống quản lý phòng gym viết bằng PHP và MySQL, gồm giao diện hội viên, trang quản trị, đăng ký gói tập, check-in QR, đặt lịch huấn luyện viên, kế hoạch tập luyện/dinh dưỡng và tích hợp dịch vụ bên ngoài.

## Chức năng chính

### Hội viên

- Đăng ký, đăng nhập bằng email/mật khẩu và xác thực OTP.
- Đăng nhập Google OAuth.
- Xem gói tập, đăng ký gói và thanh toán VNPAY sandbox.
- Xem gói đang sử dụng và lịch sử đăng ký.
- Check-in bằng QR và xem lịch sử check-in.
- Xem, đặt lịch, theo dõi và đánh giá huấn luyện viên.
- Xem kế hoạch tập luyện và dinh dưỡng.
- Cập nhật hồ sơ và ảnh đại diện.
- Gửi liên hệ và xem lịch sử liên hệ.

### Quản trị

- Dashboard thống kê.
- Quản lý hội viên, gói tập và lịch sử gói.
- Duyệt đăng ký gói và quản lý thanh toán.
- Quản lý check-in, phản hồi buổi tập và QR check-in.
- Quản lý huấn luyện viên, lịch đặt và đánh giá.
- Quản lý kế hoạch tập luyện, dinh dưỡng và nội dung AI.
- Quản lý liên hệ của hội viên.

### Bảo mật

- Xác thực session cho trang quản trị và API.
- Phân quyền admin/user tại endpoint.
- CSRF token cho thao tác ghi dữ liệu.
- Không cho API đọc dữ liệu bằng `user_id` của tài khoản khác.
- Prepared statement cho truy vấn có dữ liệu đầu vào.
- Kiểm tra MIME, kích thước và tên file upload.
- Chữ ký QR có thời hạn và đối chiếu chữ ký/số tiền VNPAY.
- Chặn truy cập trực tiếp `.env`, SQL, log và file cấu hình qua Apache.

## Công nghệ

- PHP 8.3
- MySQL 8.4
- Apache 2.4 với `mod_rewrite`
- Bootstrap 5, Bootstrap Icons và JavaScript thuần
- PHPMailer 7
- Google API Client 2.19
- VNPAY Sandbox
- Gemini API cho kế hoạch AI

## Yêu cầu môi trường

- PHP 8.1 trở lên, khuyến nghị PHP 8.3.
- MySQL 8.0 trở lên.
- Apache bật `mod_rewrite` và cho phép `.htaccess` (`AllowOverride All`).
- Composer 2.
- PHP extensions: `curl`, `fileinfo`, `mbstring`, `mysqli`, `openssl`.
- Laragon được khuyến nghị trên Windows.

Cấu hình local hiện tại của dự án:

| Thành phần | Giá trị |
| --- | --- |
| Website | `http://localhost:8086` |
| MySQL | `127.0.0.1:3307` |
| Database | `gym_management` |
| DB user | `root` |

Thông tin kết nối database đang được khai báo tại `includes/config.php`. Hãy đổi mật khẩu tại file này nếu MySQL trên máy cài đặt không dùng cấu hình local hiện tại.

## Cài đặt

### 1. Cài dependency PHP

```powershell
composer install
```

### 2. Tạo file môi trường

```powershell
Copy-Item .env.example .env
```

Mở `.env` và thay toàn bộ giá trị mẫu bằng thông tin thật. Không commit `.env` lên Git.

### 3. Tạo và import database

Tạo database UTF-8:

```powershell
mysql --no-defaults -h 127.0.0.1 -P 3307 -u root -p -e "CREATE DATABASE gym_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Import schema rỗng được lưu trong Git:

```powershell
mysql --no-defaults -h 127.0.0.1 -P 3307 -u root -p gym_management -e "SOURCE E:/duong-dan/gym-management/database/gym.sql"
```

Hoặc import bản backup đầy đủ bằng đường dẫn tuyệt đối dùng dấu `/`:

```powershell
mysql --no-defaults -h 127.0.0.1 -P 3307 -u root -p gym_management -e "SOURCE E:/duong-dan/gym-management/database/backups/gym_management_2026-07-07.sql"
```

Có thể import cùng file bằng phpMyAdmin nếu không dùng command line.

### 4. Cấu hình Apache

Trỏ `DocumentRoot` đến thư mục gốc dự án và chạy Apache ở cổng `8086`. Với Laragon, chọn thư mục dự án làm document root rồi khởi động Apache/MySQL.

Kiểm tra cấu hình Apache:

```powershell
httpd -t
```

### 5. Chạy ứng dụng

- Trang chủ: `http://localhost:8086/`
- Đăng nhập: `http://localhost:8086/login`
- Khu vực hội viên: `http://localhost:8086/user/dashboard/index`
- Trang quản trị: `http://localhost:8086/admin/dashboard`

Không có mật khẩu mặc định được ghi trong README. Hãy đăng ký tài khoản qua giao diện. Khi cần tạo admin cho môi trường local, cập nhật role của đúng tài khoản trong phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

## Cấu hình dịch vụ ngoài

### Gmail SMTP và OTP

Tài khoản Gmail phải bật xác minh hai bước và dùng App Password, không dùng mật khẩu Gmail thông thường.

```dotenv
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-google-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=FLEXZONE
```

### Google OAuth

Tạo OAuth 2.0 Web Application trên Google Cloud Console và thêm redirect URI chính xác:

```text
http://localhost:8086/php/auth/google-callback.php
```

Sau đó cấu hình:

```dotenv
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8086/php/auth/google-callback.php
```

Google OAuth trên điện thoại hoặc máy khác không thể callback về `localhost` của máy chủ. Trường hợp đó cần domain HTTPS công khai và phải cập nhật redirect URI ở cả Google Cloud Console lẫn `.env`.

### VNPAY Sandbox

```dotenv
VNPAY_TMN_CODE=your-vnpay-terminal-code
VNPAY_HASH_SECRET=your-vnpay-hash-secret
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:8086/php/vnpay/vnpay-return.php
```

Khi triển khai public, đổi `VNPAY_RETURN_URL` sang domain HTTPS của hệ thống và cập nhật cấu hình trên VNPAY.

### QR check-in

Tạo secret ngẫu nhiên dài ít nhất 32 byte:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Đặt kết quả vào:

```dotenv
CHECKIN_QR_TOKEN=your-long-random-secret
```

## Backup và restore database

Schema không chứa dữ liệu cá nhân và được lưu trong Git:

```text
database/gym.sql
```

Bản backup đầy đủ local hiện tại:

```text
database/backups/gym_management_2026-07-07.sql
```

Thư mục `database/backups/` được `.gitignore` vì chứa dữ liệu cá nhân, password hash và dữ liệu nghiệp vụ. Hãy lưu bản backup ở nơi riêng tư.

Tạo backup mới:

```powershell
mysqldump --no-defaults --host=127.0.0.1 --port=3307 --user=root --password --default-character-set=utf8mb4 --single-transaction --routines --triggers --events --hex-blob --set-gtid-purged=OFF --result-file=database/backups/gym_management.sql gym_management
```

Kiểm tra file backup bằng cách restore vào một database tạm trước khi sử dụng cho triển khai hoặc phản biện.

## Kiểm thử nhanh

Lint toàn bộ PHP trên PowerShell:

```powershell
$failed = @()
Get-ChildItem -Recurse -Filter *.php -File |
    Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' } |
    ForEach-Object {
        php -l $_.FullName
        if ($LASTEXITCODE -ne 0) { $failed += $_.FullName }
    }
if ($failed.Count -gt 0) { $failed; exit 1 }
```

Kiểm tra JavaScript chính:

```powershell
node --check js/main.js
node --check js/login.js
node --check js/checkins.js
node --check js/ai.js
```

Kiểm tra HTTP:

```powershell
curl.exe -I http://localhost:8086/
```

Các luồng cần kiểm thử thủ công trước khi demo:

1. Đăng ký, đăng nhập và nhập OTP nhận qua email.
2. Đăng nhập Google và callback về trang chủ.
3. Đăng ký gói, thanh toán VNPAY sandbox và kiểm tra lịch sử.
4. Đặt lịch HLV, cập nhật trạng thái từ admin và xem lịch sử hội viên.
5. Tạo QR check-in, quét bằng điện thoại và checkout.
6. Tạo kế hoạch tập luyện/dinh dưỡng AI.

## Cấu trúc thư mục

```text
admin/       Giao diện quản trị
api/         API JSON có xác thực session
assets/      Tài nguyên dùng chung
css/         CSS cấp ứng dụng
database/    Migration và backup local
includes/    Cấu hình, layout và helper PHP
js/          JavaScript dùng chung
php/         Endpoint xử lý nghiệp vụ
uploads/     File tải lên local, không commit
user/        Giao diện và chức năng hội viên
vendor/      Dependency do Composer quản lý
```

## Lưu ý triển khai

- Luôn dùng HTTPS cho môi trường public.
- Không đưa `.env`, database backup hoặc thư mục upload lên repository công khai.
- Đổi toàn bộ secret khi chuyển máy hoặc nghi ngờ bị lộ.
- Tắt `display_errors` trên production và ghi lỗi vào log riêng.
- Backup database trước khi chạy migration hoặc cập nhật phiên bản.
