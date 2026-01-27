<?php
$servername = "premium281.web-hosting.com";
$username = "prosdfwo_bus8-pak-austria";
$password = "Bus8PakAustria";
$dbname   = "prosdfwo_bus-8-pak-austria";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>