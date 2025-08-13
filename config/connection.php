<?php
function connection()
{
    $host = 'localhost';
    $password = "";
    $username = "root";
    $dbname = "messaging";

    // Create connection
    $conn = mysqli_connect($host, $username, $password, $dbname);

    // Check connection
    if (!$conn) {
        die("DB Failed: " . mysqli_connect_error());
    }

    return $conn;
}
