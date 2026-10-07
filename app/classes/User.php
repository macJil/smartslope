<?php

declare(strict_types=1);

// User operations use one shared PDO connection, supplied by the caller.
class User
{
    private PDO $connection;
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }
    public function create(string $fullName, string $username, string $email, string $phone, string $password): int
    {
        $pdo = $this->connection;
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, email, phone, password, role)
             VALUES (?, ?, ?, ?, ?, 'user')"
        );
        $stmt->execute([$fullName, $username, $email, $phone, hash_password($password)]);
        return (int)$pdo->lastInsertId();
    }
}
