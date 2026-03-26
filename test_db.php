<?php
$host = "127.0.0.1";
$port = 3306;
$dbname = "gym_management";
$username = "root";
$password = "";

echo "Testing database connection...\n";

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";

    // Try without database name
    echo "Trying to connect without database name...\n";
    $conn2 = new mysqli($host, $username, $password, '', $port);
    if ($conn2->connect_error) {
        echo "Connection without database also failed: " . $conn2->connect_error . "\n";
    } else {
        echo "Connection without database successful!\n";
        echo "Creating database...\n";
        if ($conn2->query("CREATE DATABASE IF NOT EXISTS gym_management")) {
            echo "Database created successfully!\n";
        } else {
            echo "Failed to create database: " . $conn2->error . "\n";
        }
        $conn2->close();
    }
} else {
    echo "Connection successful!\n";
    $conn->close();
}
?>