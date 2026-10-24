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
    public function driver(): string
    {
        $driver = getenv('DB_DRIVER') ?: 'mysql';
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new \RuntimeException('DB_DRIVER muss mysql oder pgsql sein.');
        }
        return $driver;
    }
    public function dsn(): string
    {
        $driver = $this->driver();
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'kitchen';
        $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
        foreach ([$host,$name,$port] as $value) {
            if (preg_match('/[;\r\n\x00]/', $value)) {
                throw new \RuntimeException('Ungültiger Datenbankparameter.');
            }
        }
        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
            throw new \RuntimeException('Ungültiger Datenbankport.');
        }
        if ($driver === 'mysql') {
            return "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        }
        $ssl = getenv('DB_SSLMODE') ?: 'prefer';
        if (!in_array($ssl, ['disable','allow','prefer','require','verify-ca','verify-full'], true)) {
            throw new \RuntimeException('Ungültiger DB_SSLMODE.');
        }
        $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl;connect_timeout=10";
        $cert = getenv('DB_SSLROOTCERT');
        if ($cert !== false && $cert !== '') {
            if (preg_match('/[;\r\n\x00]/', $cert)) {
                throw new \RuntimeException('Ungültiger Zertifikatspfad.');
            }
            $dsn .= ';sslrootcert='.$cert;
        }
        return $dsn;
    }
    public function connection(): PDO
    {
        if ($this->pdo === null) {
            if (getenv('APP_ENV') === 'production') {
                foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
                    if (getenv($key) === false || getenv($key) === '') {
                        throw new \RuntimeException('Produktive Datenbankkonfiguration ist unvollständig.');
                    }
                }
            }
            $pdo = new PDO($this->dsn(), getenv('DB_USER') ?: 'kitchen', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec($this->driver() === 'pgsql' ? "SET TIME ZONE 'UTC'" : "SET time_zone='+00:00'");
            $this->pdo = $pdo;
        }
        return $this->pdo;
    }
    /** SQL expression with integer/unit validated before interpolation. */
    public function ago(int $amount, string $unit): string
    {
        if ($amount < 0 || !in_array($unit, ['SECOND','MINUTE','DAY'], true)) {
            throw new \InvalidArgumentException('Ungültiges Intervall.');
        }
        return $this->driver() === 'pgsql' ? "CURRENT_TIMESTAMP - INTERVAL '$amount $unit'" : "CURRENT_TIMESTAMP - INTERVAL $amount $unit";
    }
}
