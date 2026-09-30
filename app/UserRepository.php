<?php
declare(strict_types=1);

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function authenticate(string $username, string $password): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, full_name, username, email, password_hash, role, created_at
             FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    public function create(string $fullName, string $username, string $email, string $password): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO users (full_name, username, email, password_hash, role)
             VALUES (:full_name, :username, :email, :password_hash, 'user')"
        );
        $statement->execute([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }
}
