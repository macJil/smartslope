<?php
// Simple Database Connection Class - Based on PDF Unit 4
class Database {
    private $host = "127.0.0.1";
    private $dbname = "smartslope_mvp";
    private $username = "root";
    private $password = "";
    private $port = "3306";
    
    public function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $this->username, $this->password, $options);
            $pdo->exec("SET time_zone = '+00:00'");
            return $pdo;
        } catch(PDOException $e) {
            error_log('SmartSlope Database Connection Error: ' . $e->getMessage());
            // Re-throw to let the global exception handler deal with it
            throw $e;
        }
    }
}

// Simple helper function for quick access
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $database = new Database();
        $pdo = $database->connect();
    }
    return $pdo;
}
