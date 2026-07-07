<?php

function apiSendHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
}

function apiSuccess(string $message, $data = null, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    apiSendHeaders();

    $response = array_merge([
        'success' => true,
        'message' => $message,
    ], $extra);

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError(string $message, int $statusCode = 400, $error = null, array $extra = []): void
{
    http_response_code($statusCode);
    apiSendHeaders();

    $response = array_merge([
        'success' => false,
        'message' => $message,
    ], $extra);

    if ($error !== null) {
        $response['error'] = $error;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiServerError(string $message, Throwable $exception): void
{
    error_log($message . ': ' . $exception->getMessage());
    apiError($message, 500);
}
