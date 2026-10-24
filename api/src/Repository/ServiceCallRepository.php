<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class ServiceCallRepository extends Repository
{
    /** Open calls plus calls answered in the last two minutes, so the calling station sees who is coming. */
    public function findVisible(array $stations): \PDOStatement
    {
        $placeholders = implode(',', array_fill(0, count($stations), '?'));
        return $this->execute('SELECT c.id,c.station,c.note,c.created_at,c.acknowledged_at,u.name AS requested_by,a.name AS acknowledged_by FROM service_calls c JOIN users u ON u.id=c.created_by LEFT JOIN users a ON a.id=c.acknowledged_by WHERE c.cancelled_at IS NULL AND (c.acknowledged_at IS NULL OR c.acknowledged_at>'. $this->database->ago(2, 'MINUTE') . ') AND c.station IN ('.$placeholders.') ORDER BY c.id', $stations);
    }

    public function findOpenForStation(string $station): \PDOStatement
    {
        return $this->execute('SELECT id FROM service_calls WHERE station=? AND acknowledged_at IS NULL AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE', [$station]);
    }

    public function insert(string $station, string $note, array $u): int
    {
        $this->execute('INSERT INTO service_calls(station,note,created_by) VALUES(?,?,?)', [$station,$note,$u['id']]);
        return (int)$this->database->connection()->lastInsertId();
    }

    public function updateNote(int $id, string $note): \PDOStatement
    {
        return $this->execute('UPDATE service_calls SET note=? WHERE id=?', [$note,$id]);
    }

    public function findForUpdate(int $id): \PDOStatement
    {
        return $this->execute('SELECT id,station,acknowledged_at,cancelled_at FROM service_calls WHERE id=? FOR UPDATE', [$id]);
    }

    public function acknowledge(int $id, array $u): \PDOStatement
    {
        return $this->execute('UPDATE service_calls SET acknowledged_at=NOW(),acknowledged_by=? WHERE id=?', [$u['id'],$id]);
    }

    public function cancel(int $id): \PDOStatement
    {
        return $this->execute('UPDATE service_calls SET cancelled_at=NOW() WHERE id=?', [$id]);
    }
}
