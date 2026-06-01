<?php $query = $_SERVER['QUERY_STRING'] ?? ''; $location = '../user/package/register.php' . ($query !== '' ? '?' . $query : ''); header('Location: ' . $location, true, 301); exit;
