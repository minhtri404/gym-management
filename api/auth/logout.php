<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

apiRequireAuth($conn);
apiRequireCsrf();
apiClearAuthSession();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();
apiSuccess('Đăng xuất thành công.');
