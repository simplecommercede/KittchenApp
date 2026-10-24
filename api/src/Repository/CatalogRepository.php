<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class CatalogRepository extends Repository
{
    public function findMenu(): \PDOStatement
    {
        return $this->execute('SELECT * FROM menu ORDER BY id');
    }

    public function findAddons(): \PDOStatement
    {
        return $this->execute('SELECT menu_id,addon_id FROM menu_addons ORDER BY menu_id,addon_id');
    }

    public function findDiningTables(): \PDOStatement
    {
        return $this->execute('SELECT * FROM dining_tables ORDER BY id');
    }
}
