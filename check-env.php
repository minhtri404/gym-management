<?php
include 'includes/config.php';

$masked = $gemini_api_key !== '' ? substr($gemini_api_key, 0, 4) . '...' . substr($gemini_api_key, -4) : '';

echo $gemini_api_key !== '' ? 'OK: ' . htmlspecialchars($masked) : 'MISSING';
