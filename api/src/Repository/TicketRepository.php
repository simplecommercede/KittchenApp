<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class TicketRepository extends Repository
{
    public function updateUsers(mixed $view, mixed $u): \PDOStatement
    {
        return $this->execute('UPDATE users SET last_seen=NOW(),last_view=? WHERE id=?', [$view,$u['id']]);
    }

    public function findTickets(mixed $where, mixed $archive, mixed $params): \PDOStatement
    {
        return $this->execute('SELECT t.*,o.table_id,o.note,o.user_id,o.cancellation_reason,d.name AS table_name,u.name AS server FROM tickets t JOIN orders o ON o.id=t.order_id JOIN dining_tables d ON d.id=o.table_id JOIN users u ON u.id=o.user_id WHERE '.$where.' ORDER BY t.created_at '.($archive ? 'DESC' : 'ASC').',t.id '.($archive ? 'DESC' : 'ASC').($archive ? ' LIMIT 200' : ''), $params);
    }

    public function findItems(mixed $ticket): \PDOStatement
    {
        return $this->execute('SELECT * FROM items WHERE ticket_id=?', [$ticket['id']]);
    }

    public function findEvents(mixed $ticket): \PDOStatement
    {
        return $this->execute('SELECT e.action,e.created_at,u.name FROM events e JOIN users u ON u.id=e.user_id WHERE ticket_id=? ORDER BY e.id', [$ticket['id']]);
    }

    public function findStationAlerts(mixed $placeholders, mixed $alertStations): \PDOStatement
    {
        return $this->execute('SELECT a.id,a.ticket_id,a.kind,a.description,a.reason,a.created_at,t.order_id,t.station,d.name AS table_name,u.name AS requested_by FROM station_alerts a JOIN tickets t ON t.id=a.ticket_id JOIN orders o ON o.id=t.order_id JOIN dining_tables d ON d.id=o.table_id JOIN users u ON u.id=a.created_by WHERE a.acknowledged_at IS NULL AND t.station IN ('.$placeholders.') ORDER BY a.id', $alertStations);
    }

    public function findStationAlerts2(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT a.id,a.ticket_id,a.acknowledged_at,t.station FROM station_alerts a JOIN tickets t ON t.id=a.ticket_id WHERE a.id=? FOR UPDATE', [$id]);
    }

    public function updateStationAlerts(mixed $u, mixed $id): \PDOStatement
    {
        return $this->execute('UPDATE station_alerts SET acknowledged_at=CURRENT_TIMESTAMP,acknowledged_by=? WHERE id=?', [$u['id'],$id]);
    }

    public function insertEvents(mixed $alert, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,? ,\'cancel_acknowledged\')', [$alert['ticket_id'],$u['id']]);
    }

    public function findTickets2(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT * FROM tickets WHERE id=? FOR UPDATE', [$id]);
    }

    public function findStationAlerts3(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT id FROM station_alerts WHERE ticket_id=? AND acknowledged_at IS NULL LIMIT 1', [$id]);
    }

    public function updateTickets(mixed $column, mixed $target, mixed $id): \PDOStatement
    {
        return $this->execute("UPDATE tickets SET status=?, $column=NOW() WHERE id=?", [$target,$id]);
    }

    public function insertEvents2(mixed $id, mixed $u, mixed $target): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,?)', [$id,$u['id'],$target]);
    }
}
