<?php
require 'backend/config/database.php';
$pdo = getDB();
$hash = password_hash('password123', PASSWORD_BCRYPT);
$pdo->exec("UPDATE users SET password = '$hash'");
echo "Passwords updated to 'password123'!\n";
