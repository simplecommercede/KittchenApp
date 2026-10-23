<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class CatalogRepository extends Repository
{
    public function findMenu(): \PDOStatement
    {
        return $this->execute('SELECT * FROM menu ORDER BY id');
    }

    public function findDiningTables(): \PDOStatement
    {
        return $this->execute('SELECT * FROM dining_tables ORDER BY id');
    }
}
