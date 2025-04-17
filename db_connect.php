<?php
$host = 'localhost';
$dbname = 'club';
$username = 'root'; // XAMPP default MySQL username
$password = ''; // XAMPP default MySQL password (usually empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>