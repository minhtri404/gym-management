<?php
$members = [];
include 'includes/config.php';
include 'includes/auth-check.php';
$stmt = $conn->query("SELECT id, full_name, phone, status FROM members ORDER BY id DESC LIMIT 5");
if ($stmt) {
  while ($row = $stmt->fetch_assoc()) {
    $members[] = $row;
  }
}
header('Content-Type: text/plain; charset=utf-8');
foreach ($members as $m) {
  echo $m['id'] . ' ' . $m['full_name'] . "\n";
}
