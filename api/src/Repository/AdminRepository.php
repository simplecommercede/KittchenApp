<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class AdminRepository extends Repository
{
    public function findUsers(): \PDOStatement
    {
        return $this->execute('SELECT id,name,username,roles,active FROM users ORDER BY id');
    }

    public function findUsers2(mixed $username): \PDOStatement
    {
        return $this->execute('SELECT id FROM users WHERE username=?', [$username]);
    }

    public function insertUsers(mixed $name, mixed $username, mixed $password, mixed $roles): \PDOStatement
    {
        return $this->execute('INSERT INTO users(name,username,password,roles) VALUES(?,?,?,?)', [$name,$username,password_hash($password, PASSWORD_DEFAULT),json_encode($roles)]);
    }

    public function findUsers3(): \PDOStatement
    {
        return $this->execute('SELECT id,username,roles,active FROM users FOR UPDATE');
    }

    public function updateUsers(mixed $name, mixed $username, mixed $roles, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE users SET name=?,username=?,roles=? WHERE id=?', [$name,$username,json_encode($roles),$id]);
    }

    public function findUsers4(): \PDOStatement
    {
        return $this->execute('SELECT id,roles,active FROM users FOR UPDATE');
    }

    public function updateUsers2(mixed $active, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE users SET active=? WHERE id=?', [$active ? 1 : 0,$id]);
    }

    public function findMenu(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT id FROM menu WHERE id=?', [$id]);
    }

    public function updateMenu(mixed $name, mixed $cat, mixed $station, mixed $price, mixed $straw, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE menu SET name=?,category=?,station=?,price=?,straw=? WHERE id=?', [$name,$cat,$station,$price,$straw ? 1 : 0,$id]);
    }

    public function insertMenu(mixed $name, mixed $cat, mixed $station, mixed $price, mixed $straw): \PDOStatement
    {
        return $this->execute('INSERT INTO menu(name,category,station,price,straw) VALUES(?,?,?,?,?)', [$name,$cat,$station,$price,$straw ? 1 : 0]);
    }

    public function lastInsertId(): int
    {
        return (int)$this->database->connection()->lastInsertId();
    }

    public function findKitchenProducts(array $ids): \PDOStatement
    {
        return $this->execute('SELECT id FROM menu WHERE station="kitchen" AND id IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids);
    }

    public function replaceAddons(int $menuId, array $addonIds): void
    {
        $this->execute('DELETE FROM menu_addons WHERE menu_id=?', [$menuId]);
        foreach ($addonIds as $addonId) {
            $this->execute('INSERT INTO menu_addons(menu_id,addon_id) VALUES(?,?)', [$menuId,$addonId]);
        }
    }

    public function findMenu2(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT id FROM menu WHERE id=?', [$id]);
    }

    public function updateMenu2(mixed $active, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE menu SET active=? WHERE id=?', [$active ? 1 : 0,$id]);
    }

    public function findMenu3(): \PDOStatement
    {
        return $this->execute('SELECT id,name FROM menu WHERE station="bar" FOR UPDATE');
    }

    public function updateMenu3(mixed $values, mixed $size): \PDOStatement
    {
        return $this->execute('UPDATE menu SET name=?,category=?,price=?,straw=?,active=? WHERE id=?', [...$values,$size['id']]);
    }

    public function insertMenu2(mixed $values): \PDOStatement
    {
        return $this->execute('INSERT INTO menu(name,category,price,straw,active,station) VALUES(?,?,?,?,?,"bar")', $values);
    }

    public function insertDiningTables(mixed $id, mixed $name): \PDOStatement
    {
        return $this->execute('INSERT INTO dining_tables(id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)', [$id,$name]);
    }

    public function findDiningTables(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=?', [$id]);
    }

    public function updateDiningTables(mixed $active, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE dining_tables SET active=? WHERE id=?', [$active ? 1 : 0,$id]);
    }
}
