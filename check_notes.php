<?php
include 'includes/config.php';
if (isset($conn)) {
    $result = $conn->query('DESCRIBE member_notes');
    if ($result) {
        echo "Structure of member_notes table:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ") " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
        }
    }
}
?>