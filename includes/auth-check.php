<?php
include __DIR__ . '/config.php';

if (!isset($_SESSION['admin_id'])) {
    $project_root = realpath(__DIR__ . '/..');
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $base_uri = '';

    if ($doc_root && $project_root && strpos($project_root, $doc_root) === 0) {
        $base_uri = str_replace('\\', '/', substr($project_root, strlen($doc_root)));
    }

    $base_uri = rtrim($base_uri, '/');
    header("Location: " . ($base_uri === '' ? '/login.php' : $base_uri . '/login.php'));
    exit;
}
?>
