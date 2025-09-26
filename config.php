<?php
session_start();

// Database configuration
$host = '127.0.0.1';
$username = 'root'; // Replace with your database username
$password = '';     // Replace with your database password
$database = 'test12';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Keep the MySQLi function for backward compatibility if needed
function get_db_connection() {
    global $host, $username, $password, $database;
    
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4 to match the database
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>