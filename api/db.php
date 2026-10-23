<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
function db(): PDO { return dbConnection(true); }
// Returns the open connection without connecting, so error handlers never retry a failed connection.
function dbConnection(bool $connect): ?PDO {
    static $db = null;
    if (!$db && $connect) {
        $pdo = new PDO('mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';dbname='.(getenv('DB_NAME') ?: 'kitchen').';charset=utf8mb4', getenv('DB_USER') ?: 'kitchen', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        // All stored timestamps are UTC, independent of the database server's system zone.
        $pdo->exec("SET time_zone='+00:00'");
        $db = $pdo;
    }
    return $db;
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
