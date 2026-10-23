<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class PaymentRepository extends Repository
{
    public function findTableVisits(): \PDOStatement
    {
        return $this->execute('SELECT v.id AS visit_id,v.table_id,v.paid_at,d.name AS table_name,COALESCE(u.name,"Unbekannt") AS paid_by FROM table_visits v JOIN dining_tables d ON d.id=v.table_id LEFT JOIN users u ON u.id=v.paid_by WHERE v.paid_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM table_visits newer WHERE newer.table_id=v.table_id AND newer.id>v.id) ORDER BY v.paid_at DESC,v.id DESC');
    }

    public function findPaymentEvents(): \PDOStatement
    {
        return $this->execute('SELECT e.id,e.visit_id,e.order_id,e.action,e.amount,e.reason,e.created_at,d.name AS table_name,u.name AS actor FROM payment_events e JOIN table_visits v ON v.id=e.visit_id JOIN dining_tables d ON d.id=v.table_id JOIN users u ON u.id=e.user_id ORDER BY e.id DESC LIMIT 30');
    }

    public function findTableVisits2(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT table_id FROM table_visits WHERE id=?', [$visitId]);
    }

    public function findDiningTables(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=? FOR UPDATE', [$reference['table_id']]);
    }

    public function findTableVisits3(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT id,paid_at FROM table_visits WHERE id=? FOR UPDATE', [$visitId]);
    }

    public function findTickets(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM tickets t JOIN orders o ON o.id=t.order_id WHERE o.visit_id=? AND t.status NOT IN ("collected","cancelled")', [$visitId]);
    }

    public function findOrders(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT COUNT(DISTINCT o.id) AS orders_count,COALESCE(SUM(i.quantity*i.price),0) AS total FROM orders o JOIN tickets t ON t.order_id=o.id JOIN items i ON i.ticket_id=t.id WHERE o.visit_id=? AND o.cancelled_at IS NULL AND o.paid_at IS NULL AND i.cancelled_at IS NULL', [$visitId]);
    }

    public function updateOrders(mixed $u, mixed $visitId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET paid_at=UTC_TIMESTAMP(),paid_by=? WHERE visit_id=? AND cancelled_at IS NULL AND paid_at IS NULL', [$u['id'],$visitId]);
    }

    public function updateTableVisits(mixed $u, mixed $visitId): \PDOStatement
    {
        return $this->execute('UPDATE table_visits SET paid_at=UTC_TIMESTAMP(),paid_by=? WHERE id=?', [$u['id'],$visitId]);
    }

    public function insertPaymentEvents(mixed $visitId, mixed $amount, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO payment_events(visit_id,action,amount,user_id) VALUES(?,"confirmed",?,?)', [$visitId,(int)$amount['total'],$u['id']]);
    }

    public function findOrders2(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT table_id,visit_id FROM orders WHERE id=?', [$orderId]);
    }

    public function findDiningTables2(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=? FOR UPDATE', [$reference['table_id']]);
    }

    public function findTableVisits4(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT paid_at FROM table_visits WHERE id=? FOR UPDATE', [$visitId]);
    }

    public function findOrders3(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT cancelled_at,paid_at FROM orders WHERE id=? FOR UPDATE', [$orderId]);
    }

    public function findTickets2(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM tickets WHERE order_id=? AND status NOT IN ("collected","cancelled")', [$orderId]);
    }

    public function findTickets3(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT COALESCE(SUM(i.quantity*i.price),0) AS total FROM tickets t JOIN items i ON i.ticket_id=t.id WHERE t.order_id=? AND i.cancelled_at IS NULL', [$orderId]);
    }

    public function updateOrders2(mixed $u, mixed $orderId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET paid_at=UTC_TIMESTAMP(),paid_by=? WHERE id=?', [$u['id'],$orderId]);
    }

    public function insertPaymentEvents2(mixed $visitId, mixed $orderId, mixed $total, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO payment_events(visit_id,order_id,action,amount,user_id) VALUES(?,?,"order_paid",?,?)', [$visitId,$orderId,$total,$u['id']]);
    }

    public function findOrders4(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM orders WHERE visit_id=? AND cancelled_at IS NULL AND paid_at IS NULL', [$visitId]);
    }

    public function updateTableVisits2(mixed $u, mixed $visitId): \PDOStatement
    {
        return $this->execute('UPDATE table_visits SET paid_at=UTC_TIMESTAMP(),paid_by=? WHERE id=?', [$u['id'],$visitId]);
    }

    public function findTableVisits5(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT table_id FROM table_visits WHERE id=?', [$visitId]);
    }

    public function findDiningTables3(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=? FOR UPDATE', [$reference['table_id']]);
    }

    public function findTableVisits6(mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT id,paid_at FROM table_visits WHERE id=? FOR UPDATE', [$visitId]);
    }

    public function findTableVisits7(mixed $reference, mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT id FROM table_visits WHERE table_id=? AND id>? LIMIT 1', [$reference['table_id'],$visitId]);
    }

    public function updateTableVisits3(mixed $visitId): \PDOStatement
    {
        return $this->execute('UPDATE table_visits SET paid_at=NULL,paid_by=NULL WHERE id=?', [$visitId]);
    }

    public function updateOrders3(mixed $visitId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET paid_at=NULL,paid_by=NULL WHERE visit_id=?', [$visitId]);
    }

    public function insertPaymentEvents3(mixed $visitId, mixed $amount, mixed $reason, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO payment_events(visit_id,action,amount,reason,user_id) VALUES(?,"reversed",?,?,?)',[$visitId,$amount,$reason,$u['id']]);
    }
}
