<?php

require_once __DIR__ . '/response.php';

function apiRequireAuth(mysqli $conn, array $allowedRoles = []): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        apiError('Bạn cần đăng nhập để sử dụng API.', 401);
    }

    try {
        $stmt = $conn->prepare('
            SELECT id, full_name, email, phone, role, status, avatar, created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        apiServerError('Không thể xác thực phiên đăng nhập.', $e);
    }

    if (!$user) {
        apiClearAuthSession();
        apiError('Phiên đăng nhập không còn hợp lệ.', 401);
    }

    if ((int) ($user['status'] ?? 0) !== 1) {
        apiClearAuthSession();
        apiError('Tài khoản đã bị khóa hoặc chưa kích hoạt.', 403);
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $normalizedRoles = array_map(
        static fn ($item) => strtolower(trim((string) $item)),
        $allowedRoles
    );

    if ($normalizedRoles !== [] && !in_array($role, $normalizedRoles, true)) {
        apiError('Bạn không có quyền sử dụng chức năng này.', 403);
    }

    $_SESSION['user_name'] = $user['full_name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? '';

    return $user;
}

function apiRequireCsrf(): void
{
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $requestToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        apiError('CSRF token không hợp lệ.', 419);
    }
}

function apiRejectForeignUserId(int $currentUserId, string $source = 'REQUEST'): void
{
    $providedUserId = 0;

    if ($source === 'GET') {
        $providedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    } elseif ($source === 'POST') {
        $providedUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    } else {
        $providedUserId = isset($_REQUEST['user_id']) ? (int) $_REQUEST['user_id'] : 0;
    }

    if ($providedUserId > 0 && $providedUserId !== $currentUserId) {
        apiError('Bạn không được truy cập dữ liệu của tài khoản khác.', 403);
    }
}

function apiClearAuthSession(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['user_role']
    );
}
