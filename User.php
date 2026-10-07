<?php
// Simple User Model - Based on PDF OOP and PDO principles
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Create new user
    public function create($fullName, $username, $email, $phone, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (full_name, username, email, phone, password, role)
                VALUES (:full_name, :username, :email, :phone, :password, 'user')";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $hashedPassword
        ]);
    }
    
    // Authenticate user
    public function authenticate($username, $password) {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }
    
    // Get user by ID
    public function getById($id) {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    // Check if username, email, or phone exists
    public function exists($username, $email, $phone) {
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email OR phone = :phone";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username, 'email' => $email, 'phone' => $phone]);
        return $stmt->fetchColumn() > 0;
    }
}
