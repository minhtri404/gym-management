<?php
require_once __DIR__ . '/../../includes/google-client.php';

$base_path = '../../';

if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Phiên đăng nhập Google không hợp lệ.'));
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Không nhận được mã xác thực Google.'));
    exit;
}

$client = getGoogleClient();

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        header('Location: ' . $base_path . 'login.php?error=' . urlencode('Google Login thất bại.'));
        exit;
    }

    $client->setAccessToken($token);

    $oauth = new Google\Service\Oauth2($client);
    $googleUser = $oauth->userinfo->get();

    $google_id = $googleUser->getId();
    $email = $googleUser->getEmail();
    $full_name = $googleUser->getName();
    $avatar = $googleUser->getPicture();

    if ($email === '') {
        header('Location: ' . $base_path . 'login.php?error=' . urlencode('Không lấy được email từ Google.'));
        exit;
    }

    // Tìm user đã có trong hệ thống theo google_id hoặc email
    $stmt = $conn->prepare("
        SELECT id, full_name, email, role, status
        FROM users
        WHERE google_id = ? OR email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Nếu chưa có tài khoản thì tạo user mới
    if (!$user) {
        $role = 'member';
        $status = 1;

        $stmt = $conn->prepare("
            INSERT INTO users 
            (full_name, email, password, role, status, google_id, avatar)
            VALUES (?, ?, NULL, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssiss", $full_name, $email, $role, $status, $google_id, $avatar);
        $stmt->execute();

        $new_user_id = $stmt->insert_id;
        $stmt->close();

        $user = [
            'id' => $new_user_id,
            'full_name' => $full_name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ];
    } else {
        // Nếu tài khoản cũ trùng email nhưng chưa có google_id thì liên kết Google
        $stmt = $conn->prepare("
            UPDATE users
            SET google_id = ?, avatar = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $google_id, $avatar, $user['id']);
        $stmt->execute();
        $stmt->close();
    }

    if ((int)$user['status'] !== 1) {
        header('Location: ' . $base_path . 'login.php?error=' . urlencode('Tài khoản đã bị khóa.'));
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    unset($_SESSION['google_oauth_state']);

    if (strtolower(trim($user['role'] ?? '')) === 'admin') {
        unset($_SESSION['redirect_after_login'], $_SESSION['post_login_redirect']);
        header('Location: ' . $base_path . 'admin/dashboard.php');
        exit;
    }

    $redirect_after_login = trim($_SESSION['redirect_after_login'] ?? '');
    unset($_SESSION['redirect_after_login']);

    if ($redirect_after_login !== '' && !preg_match('#^(?:https?:)?//#i', $redirect_after_login)) {
        header('Location: ' . $redirect_after_login);
        exit;
    }

    $post_login_redirect = trim($_SESSION['post_login_redirect'] ?? '');
    unset($_SESSION['post_login_redirect']);

    if ($post_login_redirect !== '' && !preg_match('#^(?:https?:)?//#i', $post_login_redirect)) {
        header('Location: ' . $base_path . ltrim($post_login_redirect, '/'));
        exit;
    }

    header('Location: ' . $base_path . 'user/home.php');

    exit;

} catch (Exception $e) {
    error_log('Google login error: ' . $e->getMessage());

    header('Location: ' . $base_path . 'login.php?error=' . urlencode('Lỗi đăng nhập Google.'));
    exit;
}
