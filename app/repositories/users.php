<?php
declare(strict_types=1);

// Users database operations.

function create_user(string $fullName, string $username, string $email, string $phone, string $password): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, username, email, phone, password, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$fullName, $username, $email, $phone, hash_password($password)]);
    return (int)$pdo->lastInsertId();
}
