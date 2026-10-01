<?php
declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    /** Return only the supplied identity fields that already exist. */
    public function duplicateFields(string $username, string $email, string $contactNumber): array
    {
        $query = $this->pdo->prepare('SELECT MAX(username = :username) AS username,
            MAX(email = :email) AS email, MAX(contact_number = :phone) AS contact_number FROM users');
        $query->execute(['username'=>$username, 'email'=>$email, 'phone'=>$contactNumber]);
        return array_keys(array_filter($query->fetch(), static fn($value) => (int)$value === 1));
    }

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
