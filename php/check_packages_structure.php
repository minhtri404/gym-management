<?php
require_once __DIR__ . '/../includes/config.php';

$result = $conn->query('DESCRIBE packages');
echo "📋 Cấu trúc bảng packages:<br>";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}
$conn->close();
?>
