<?php

declare(strict_types=1);

class Database
{
    private array $settings;
    private ?PDO $connection = null;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function connect(): PDO
    {
        if ($this->connection === null) {
            $settings = $this->settings;
            $dsn = "mysql:host={$settings['db_host']};port={$settings['db_port']};dbname={$settings['db_name']};charset=utf8mb4";
            $this->connection = new PDO($dsn, $settings['db_user'], $settings['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connection->exec("SET time_zone = '+00:00'");
        }
        return $this->connection;
    }
}
