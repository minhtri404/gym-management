<?php $query = $_SERVER['QUERY_STRING'] ?? ''; $location = '../user/package/detail.php' . ($query !== '' ? '?' . $query : ''); header('Location: ' . $location, true, 301); exit;
