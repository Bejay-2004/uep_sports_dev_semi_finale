<?php
// config/db.php

$host = "localhost";
$port = "3308"; // ✅ WAMP MySQL port
$dbname = "uep_sports_management";
$user = "root";
$pass = "garinbellyjoe2004";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  die("DB connection failed: " . $e->getMessage());
}
