<?php

if (!function_exists('ensure_home_banners_table')) {
    function ensure_home_banners_table(mysqli $conn): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS home_banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                subtitle TEXT NULL,
                button_text VARCHAR(80) NULL,
                button_link VARCHAR(255) NULL,
                image_path VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active', 'hidden') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        return (bool) $conn->query($sql);
    }
}

if (!function_exists('default_home_banners')) {
    function default_home_banners(): array
    {
        return [
            [
                'title' => 'Bắt đầu hành trình hôm nay',
                'subtitle' => 'Không gian tập luyện hiện đại, huấn luyện viên đồng hành và lộ trình rõ ràng cho từng mục tiêu.',
                'button_text' => 'Xem gói tập',
                'button_link' => 'user/package/index',
                'image_path' => 'assets/images/banners/98ae8a05-e6d5-48ae-af7c-f3f9c2b51e7f.png',
                'sort_order' => 1,
            ],
            [
                'title' => 'Tập luyện chủ động hơn',
                'subtitle' => 'Theo dõi gói tập, check-in và lịch sử luyện tập ngay trong khu vực hội viên.',
                'button_text' => 'Khu vực hội viên',
                'button_link' => 'user/dashboard/index',
                'image_path' => 'assets/images/banners/bfce157c-2601-4a6c-af69-b27a75c9592e.png',
                'sort_order' => 2,
            ],
            [
                'title' => 'Nâng cấp vóc dáng cùng FLEXZONE',
                'subtitle' => 'Chọn gói phù hợp, đặt lịch HLV và nhận hỗ trợ cá nhân hóa theo mục tiêu của bạn.',
                'button_text' => 'Đăng ký tư vấn',
                'button_link' => 'contact-form.php',
                'image_path' => 'assets/images/banners/68bfcf94-7992-412c-a9a2-187dde0aeb1c.png',
                'sort_order' => 3,
            ],
        ];
    }
}

if (!function_exists('seed_home_banners_if_empty')) {
    function seed_home_banners_if_empty(mysqli $conn): void
    {
        if (!ensure_home_banners_table($conn)) {
            return;
        }

        $result = $conn->query('SELECT COUNT(*) AS total FROM home_banners');
        $total = $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;

        if ($total > 0) {
            return;
        }

        $stmt = $conn->prepare('
            INSERT INTO home_banners (title, subtitle, button_text, button_link, image_path, sort_order, status)
            VALUES (?, ?, ?, ?, ?, ?, "active")
        ');

        if (!$stmt) {
            return;
        }

        foreach (default_home_banners() as $banner) {
            $title = $banner['title'];
            $subtitle = $banner['subtitle'];
            $buttonText = $banner['button_text'];
            $buttonLink = $banner['button_link'];
            $imagePath = $banner['image_path'];
            $sortOrder = (int) $banner['sort_order'];

            $stmt->bind_param('sssssi', $title, $subtitle, $buttonText, $buttonLink, $imagePath, $sortOrder);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('get_home_banners')) {
    function get_home_banners(mysqli $conn, bool $activeOnly = true): array
    {
        seed_home_banners_if_empty($conn);

        $where = $activeOnly ? "WHERE status = 'active'" : '';
        $sql = "
            SELECT id, title, subtitle, button_text, button_link, image_path, sort_order, status, created_at, updated_at
            FROM home_banners
            $where
            ORDER BY sort_order ASC, id ASC
        ";

        $result = $conn->query($sql);
        $banners = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $banners[] = $row;
            }
        }

        if ($activeOnly && count($banners) === 0) {
            return default_home_banners();
        }

        return $banners;
    }
}

if (!function_exists('banner_image_url')) {
    function banner_image_url(string $imagePath, string $basePath = ''): string
    {
        $imagePath = trim($imagePath);

        if ($imagePath === '') {
            $imagePath = 'assets/images/imagebanne.jpg';
        }

        if (preg_match('#^https?://#i', $imagePath)) {
            return $imagePath;
        }

        return rtrim($basePath, '/') . '/' . ltrim($imagePath, '/');
    }
}

if (!function_exists('banner_link_url')) {
    function banner_link_url(string $link, string $basePath = ''): string
    {
        $link = trim($link);

        if ($link === '') {
            return '#';
        }

        if (preg_match('#^(https?://|mailto:|tel:|#)#i', $link)) {
            return $link;
        }

        return rtrim($basePath, '/') . '/' . ltrim($link, '/');
    }
}
