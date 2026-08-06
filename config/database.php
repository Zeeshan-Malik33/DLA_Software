<?php
// ---------------------------------------------------------
// Database connection (PDO) — XAMPP local defaults
// ---------------------------------------------------------
$host    = 'localhost';
$db      = 'DLA_DB';
$user    = 'root';
$pass    = '';           // XAMPP's default root password is blank
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}