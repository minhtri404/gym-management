<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

function google_env_value($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../.env';

    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            if (trim($parts[0]) === $key) {
                return trim(trim($parts[1]), "\"'");
            }
        }
    }

    return $default;
}

function google_runtime_redirect_uri()
{
    $configured = google_env_value('GOOGLE_REDIRECT_URI');

    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectBase = preg_replace('#/php/auth$#', '', rtrim($scriptDir, '/'));

    return ($host !== '') ? $scheme . '://' . $host . $projectBase . '/php/auth/google-callback.php' : '';
}

function getGoogleClient()
{
    $client = new Google\Client();

    $client->setClientId(google_env_value('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(google_env_value('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(google_runtime_redirect_uri());

    $client->addScope('email');
    $client->addScope('profile');

    $client->setAccessType('online');
    $client->setPrompt('select_account');

    return $client;
}
