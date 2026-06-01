<?php
// Simple Google reCAPTCHA helper
// - Reads keys from environment variables or from the .env file at project root
// - Provides `get_recaptcha_site_key()`, `get_recaptcha_secret_key()` and `verify_recaptcha_response()`

function get_recaptcha_site_key(): string
{
    $key = $_SERVER['RECAPTCHA_SITE_KEY'] ?? $_ENV['RECAPTCHA_SITE_KEY'] ?? getenv('RECAPTCHA_SITE_KEY');
    if (!empty($key)) {
        return trim($key);
    }

    $env_path = __DIR__ . '/../.env';
    if (is_file($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $k = trim($parts[0]);
                $v = trim($parts[1], "\"' ");
                if ($k === 'RECAPTCHA_SITE_KEY') {
                    return $v;
                }
            }
        }
    }

    return '';
}

function get_recaptcha_secret_key(): string
{
    $key = $_SERVER['RECAPTCHA_SECRET_KEY'] ?? $_ENV['RECAPTCHA_SECRET_KEY'] ?? getenv('RECAPTCHA_SECRET_KEY');
    if (!empty($key)) {
        return trim($key);
    }

    $env_path = __DIR__ . '/../.env';
    if (is_file($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $k = trim($parts[0]);
                $v = trim($parts[1], "\"' ");
                if ($k === 'RECAPTCHA_SECRET_KEY') {
                    return $v;
                }
            }
        }
    }

    return '';
}

function verify_recaptcha_response(string $token, ?string $remote_ip = null): array
{
    $token = trim($token);
    if ($token === '') {
        return ['success' => false, 'message' => 'Thiếu token reCAPTCHA.'];
    }

    $secret = get_recaptcha_secret_key();
    if ($secret === '') {
        return ['success' => false, 'message' => 'Secret reCAPTCHA chưa được cấu hình.'];
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $token,
    ];
    if ($remote_ip !== null) {
        $data['remoteip'] = $remote_ip;
    }

    $response = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === '') {
        return ['success' => false, 'message' => 'Không thể kết nối tới server xác thực reCAPTCHA.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Phản hồi reCAPTCHA không hợp lệ.'];
    }

    if (!empty($decoded['success'])) {
        return ['success' => true, 'message' => 'OK'];
    }

    $errorCodes = $decoded['error-codes'] ?? $decoded['error_codes'] ?? $decoded['errors'] ?? null;
    $messages = [];
    if (is_array($errorCodes)) {
        foreach ($errorCodes as $code) {
            switch ($code) {
                case 'missing-input-secret':
                    $messages[] = 'Thiếu secret reCAPTCHA.';
                    break;
                case 'invalid-input-secret':
                    $messages[] = 'Secret reCAPTCHA không hợp lệ.';
                    break;
                case 'missing-input-response':
                    $messages[] = 'Bạn chưa hoàn thành CAPTCHA.';
                    break;
                case 'invalid-input-response':
                    $messages[] = 'Token CAPTCHA không hợp lệ.';
                    break;
                case 'timeout-or-duplicate':
                    $messages[] = 'CAPTCHA đã hết hạn hoặc đã được sử dụng.';
                    break;
                case 'bad-request':
                    $messages[] = 'Yêu cầu xác thực CAPTCHA không hợp lệ.';
                    break;
                case 'sitekey-secret-mismatch':
                    $messages[] = 'Site key và secret không khớp.';
                    break;
                default:
                    $messages[] = str_replace('-', ' ', $code);
                    break;
            }
        }
    }

    $finalMsg = 'Xác thực CAPTCHA thất bại.';
    if (!empty($messages)) {
        $finalMsg = implode(' ', $messages);
    }

    return ['success' => false, 'message' => $finalMsg];
}
