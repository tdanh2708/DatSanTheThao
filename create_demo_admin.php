<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this local demo setup from the command line.\n");
}
require __DIR__ . '/config/database.php';

$email = 'admin.demo@example.test';
$password = 'AdminCourt2026!';
$existingAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($existingAdmin > 0) {
    echo "Admin account already exists; no account was added.\n";
    exit(0);
}
$statement = $pdo->prepare("INSERT INTO users(full_name,email,password,role,status) VALUES(?,?,?,'admin','active')");
$statement->execute(['Quản trị viên Demo', $email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Created local demo admin. Email: {$email}; password: {$password}\n";
