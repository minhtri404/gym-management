<?php
include 'includes/config.php';
if (isset($conn)) {
    $result = $conn->query('SHOW TABLES');
    if ($result) {
        echo "Tables in database:\n";
        while ($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
    }
}
?>