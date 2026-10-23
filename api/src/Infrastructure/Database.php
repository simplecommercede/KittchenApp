<?php

declare(strict_types=1);

namespace Kitchen\Infrastructure;

use PDO;

final class Database
{
    private static ?self $instance = null;
    public function __construct(private ?PDO $pdo = null)
    {
    }
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
    public function current(): ?PDO
    {
        return $this->pdo;
    }
    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';dbname='.(getenv('DB_NAME') ?: 'kitchen').';charset=utf8mb4', getenv('DB_USER') ?: 'kitchen', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec("SET time_zone='+00:00'");
            $this->pdo = $pdo;
        }
        return $this->pdo;
    }
}
