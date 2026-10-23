<?php
declare(strict_types=1);
// Compatibility entry point for CLI migrations; application code uses Database/Repositories.
require_once __DIR__.'/bootstrap.php';
function db(): PDO {return Kitchen\Infrastructure\Database::instance()->connection();}
function dbConnection(bool $connect): ?PDO {return $connect?db():Kitchen\Infrastructure\Database::instance()->current();}
function query(string $sql,array $params=[]): PDOStatement {return (new Kitchen\Repository\Repository(Kitchen\Infrastructure\Database::instance()))->execute($sql,$params);}
