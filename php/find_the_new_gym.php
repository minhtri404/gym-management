<?php
require_once __DIR__ . '/../includes/config.php';

echo "🔍 Tìm 'The New Gym' trong database:<br><br>";

// Danh sách bảng cần kiểm tra
$tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);

foreach ($tables as $table) {
    $table_name = $table[0];
    $columns_result = $conn->query("DESCRIBE $table_name");
    
    $columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $type = $col['Type'];
        if (strpos($type, 'text') !== false || strpos($type, 'varchar') !== false) {
            $columns[] = $col['Field'];
        }
    }
    
    if (!empty($columns)) {
        foreach ($columns as $column) {
            $result = $conn->query("SELECT * FROM $table_name WHERE $column LIKE '%The New Gym%'");
            if ($result && $result->num_rows > 0) {
                echo "✅ Tìm thấy ở bảng <b>$table_name</b>, cột <b>$column</b>:<br>";
                while ($row = $result->fetch_assoc()) {
                    echo "<pre>";
                    print_r($row);
                    echo "</pre>";
                }
            }
        }
    }
}

$conn->close();
?>
