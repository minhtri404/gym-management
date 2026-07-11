<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$host = '127.0.0.1';
$port = 3307;
$dbname = 'gym_management';
$username = 'root';
$password = '123';

$session_token = $_SESSION['csrf_token'] ?? '';
if ($session_token === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $conn = new mysqli($host, $username, $password, $dbname, $port);
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($scriptName, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'success' => false,
            'message' => 'Dịch vụ database tạm thời không khả dụng.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    die('Dịch vụ database tạm thời không khả dụng.');
}

$conn->set_charset('utf8mb4');

$openai_api_key = $_SERVER['OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
$openai_api_key = trim($openai_api_key);
$gemini_api_key = $_SERVER['GEMINI_API_KEY'] ?? $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '';
$gemini_api_key = trim($gemini_api_key);

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

            $key = trim($parts[0]);
            $value = trim(trim($parts[1]), "\"'");

            if ($openai_api_key === '' && $key === 'OPENAI_API_KEY') {
                $openai_api_key = $value;
            }

            if ($gemini_api_key === '' && $key === 'GEMINI_API_KEY') {
                $gemini_api_key = $value;
            }
        }
    }
}

$openai_api_key_missing = $openai_api_key === '';
$gemini_api_key_missing = $gemini_api_key === '';
