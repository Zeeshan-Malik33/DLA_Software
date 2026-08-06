<?php
// ---------------------------------------------------------
// Run this ONCE in your browser to create your first admin
// login, e.g. http://localhost/business-management-system/scripts/create_admin.php
// Then DELETE this file (or move it out of the web root) —
// leaving it live would let anyone create an admin account.
// ---------------------------------------------------------
require '../config/database.php';

$name     = 'Admin';
$email    = 'admin@example.com';
$password = 'password123';   // change this before running

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $hash, 'admin']);

echo "Admin user created.<br>";
echo "Email: " . htmlspecialchars($email) . "<br>";
echo "Password: " . htmlspecialchars($password) . "<br>";
echo "<strong>Delete this file now.</strong>";
