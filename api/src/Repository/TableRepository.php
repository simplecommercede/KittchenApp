<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class TableRepository extends Repository
{
    public function findTableVisits(): \PDOStatement
    {
        return $this->execute('SELECT v.id AS visit_id,v.table_id,d.name AS table_name,v.opened_at,COUNT(CASE WHEN t.status<>\'cancelled\' THEN t.id END) AS ticket_count,
   SUM(CASE WHEN t.status NOT IN (\'collected\',\'cancelled\') THEN 1 ELSE 0 END) AS pending_count,
   SUM(CASE WHEN t.status=\'ready\' THEN 1 ELSE 0 END) AS ready_count,
   SUM(CASE WHEN t.station=\'kitchen\' AND t.status<>\'cancelled\' THEN 1 ELSE 0 END) AS food_count,
   SUM(CASE WHEN t.station=\'kitchen\' AND t.status NOT IN (\'collected\',\'cancelled\') THEN 1 ELSE 0 END) AS food_pending,
   MIN(CASE WHEN t.station=\'kitchen\' AND t.status NOT IN (\'collected\',\'cancelled\') THEN t.created_at END) AS food_waiting_since,
   MIN(CASE WHEN t.status NOT IN (\'collected\',\'cancelled\') THEN t.created_at END) AS waiting_since,
   MAX(CASE WHEN t.station=\'kitchen\' AND t.status=\'collected\' THEN t.collected_at END) AS food_delivered_at,
   MAX(CASE WHEN t.status=\'collected\' THEN t.collected_at END) AS last_delivered_at
   FROM table_visits v JOIN dining_tables d ON d.id=v.table_id
   LEFT JOIN orders o ON o.visit_id=v.id LEFT JOIN tickets t ON t.order_id=o.id
   WHERE v.paid_at IS NULL GROUP BY v.id,v.table_id,d.name,v.opened_at HAVING COUNT(CASE WHEN t.status<>\'cancelled\' THEN t.id END)>0 ORDER BY v.table_id');
    }

    public function findDiningTables(mixed $table): \PDOStatement
    {
        return $this->execute('SELECT id,name,active FROM dining_tables WHERE id=?', [$table]);
    }

    public function findTableVisits2(mixed $table): \PDOStatement
    {
        return $this->execute('SELECT id FROM table_visits WHERE table_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1', [$table]);
    }

    public function findOrders(mixed $visit): \PDOStatement
    {
        return $this->execute('SELECT o.id,o.created_at,o.note,o.cancelled_at,o.cancellation_reason,o.paid_at,u.name AS server,c.name AS cancelled_by FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN users c ON c.id=o.cancelled_by WHERE o.visit_id=? ORDER BY o.id', [$visit['id']]);
    }

    public function findOrders2(mixed $visit): \PDOStatement
    {
        return $this->execute('SELECT o.id AS order_id,i.id,i.menu_id,i.name,i.quantity,i.price,i.note,i.cancelled_at,i.cancellation_reason,t.station,t.status FROM orders o JOIN tickets t ON t.order_id=o.id JOIN items i ON i.ticket_id=t.id WHERE o.visit_id=? ORDER BY o.id,i.id', [$visit['id']]);
    }

    public function findOrderChanges(mixed $visit): \PDOStatement
    {
        return $this->execute('SELECT c.order_id,c.action,c.reason,c.before_state,c.after_state,c.created_at,u.name AS actor FROM order_changes c JOIN orders o ON o.id=c.order_id JOIN users u ON u.id=c.user_id WHERE o.visit_id=? ORDER BY c.id', [$visit['id']]);
    }

    public function findTickets(mixed $visit): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM tickets t JOIN orders o ON o.id=t.order_id WHERE o.visit_id=? AND o.cancelled_at IS NULL AND t.status NOT IN (\'collected\',\'cancelled\')', [$visit['id']]);
    }
}
