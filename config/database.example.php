<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// Copy this file to database.php and update values for your local MySQL setup.
$dbHost = '127.0.0.1';
$dbName = 'dat_san_the_thao';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra config/database.php và import database.sql.');
}
