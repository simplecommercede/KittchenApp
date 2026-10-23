<?php

declare(strict_types=1);

namespace Kitchen\Repository;

use Kitchen\Infrastructure\Database;
use PDOStatement;

class Repository
{
    public function __construct(protected Database $database)
    {
    }
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
}
