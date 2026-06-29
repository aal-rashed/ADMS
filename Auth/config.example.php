<?php
$servername = "localhost";
$username   = "demo_user";   // replace with your XAMPP username 
$password   = "demo_pass";   // replace with your XAMPP password 
$dbname     = "adms_demo";   // replace with your local database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/** PDO connection (used by Community module — prepared statements only) */
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
}
?>