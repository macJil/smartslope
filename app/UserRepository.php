<?php
declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function authenticate(string $username, string $password): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT user_id, full_name, username, email, contact_number, password_hash, role, created_at
             FROM users WHERE username=:username LIMIT 1'
        );
        $query->execute(['username'=>$username]);
        $user = $query->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        unset($user['password_hash']);
        return $user;
    }

    public function create(string $fullName, string $username, string $email, string $contactNumber, string $password): void
    {
        $query = $this->pdo->prepare(
            "INSERT INTO users (full_name,username,email,contact_number,password_hash,role)
             VALUES (:full_name,:username,:email,:contact_number,:password_hash,'user')"
        );
        $query->execute([
            'full_name'=>$fullName, 'username'=>$username, 'email'=>$email,
            'contact_number'=>$contactNumber,
            'password_hash'=>password_hash($password, PASSWORD_DEFAULT),
        ]);
    }
}
