<?php
session_start();

$host = "127.0.0.1";
$port = 3307;
$dbname = "gym_management";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Kết nối database thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
