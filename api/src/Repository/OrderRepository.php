<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class OrderRepository extends Repository
{
    public function findOrders(mixed $key): \PDOStatement
    {
        return $this->execute('SELECT id,user_id FROM orders WHERE request_key=?', [$key]);
    }

    public function findDiningTables(mixed $table): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=? AND active=1 FOR UPDATE', [$table]);
    }

    public function findTableVisits(mixed $table): \PDOStatement
    {
        return $this->execute('SELECT id FROM table_visits WHERE table_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE', [$table]);
    }

    public function insertTableVisits(mixed $table): \PDOStatement
    {
        return $this->execute('INSERT INTO table_visits(table_id) VALUES(?)', [$table]);
    }

    public function findMenu(mixed $id): \PDOStatement
    {
        return $this->execute('SELECT * FROM menu WHERE id=? AND active=1 FOR UPDATE', [$id]);
    }

    public function insertOrders(mixed $key, mixed $table, mixed $visitId, mixed $u, mixed $note): \PDOStatement
    {
        return $this->execute('INSERT INTO orders(request_key,table_id,visit_id,user_id,note) VALUES(?,?,?,?,?)', [$key,$table,$visitId,$u['id'],$note]);
    }

    public function insertTickets(mixed $order, mixed $m): \PDOStatement
    {
        return $this->execute('INSERT INTO tickets(order_id,station) VALUES(?,?)', [$order,$m['station']]);
    }

    public function insertEvents(mixed $stations, mixed $m, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,?)', [$stations[$m['station']],$u['id'],'new']);
    }

    public function insertItems(mixed $stations, mixed $m, mixed $qty, mixed $n): \PDOStatement
    {
        return $this->execute('INSERT INTO items(ticket_id,menu_id,name,quantity,price,note) VALUES(?,?,?,?,?,?)', [$stations[$m['station']],$m['id'],$m['name'],$qty,$m['price'],$n]);
    }

    public function findOrders2(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT table_id,visit_id FROM orders WHERE id=?', [$orderId]);
    }

    public function findDiningTables2(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT active FROM dining_tables WHERE id=? FOR UPDATE', [$reference['table_id']]);
    }

    public function findTableVisits2(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT paid_at FROM table_visits WHERE id=? FOR UPDATE', [$reference['visit_id']]);
    }

    public function findOrders3(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT note,cancelled_at,paid_at FROM orders WHERE id=? FOR UPDATE', [$orderId]);
    }

    public function findOrderChanges(mixed $key): \PDOStatement
    {
        return $this->execute('SELECT order_id,user_id FROM order_changes WHERE request_key=?', [$key]);
    }

    public function updateOrders(mixed $note, mixed $orderId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET note=? WHERE id=?', [$note,$orderId]);
    }

    public function findTickets(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT id FROM tickets WHERE order_id=? AND status NOT IN (\'collected\',\'cancelled\')', [$orderId]);
    }

    public function insertEvents2(mixed $ticket, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,\'note_changed\')', [$ticket['id'],$u['id']]);
    }

    public function findItems(mixed $itemId, mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT i.*,t.id AS old_ticket_id,t.status AS old_status FROM items i JOIN tickets t ON t.id=i.ticket_id WHERE i.id=? AND t.order_id=? FOR UPDATE', [$itemId,$orderId]);
    }

    public function updateItems(mixed $u, mixed $reason, mixed $itemId): \PDOStatement
    {
        return $this->execute('UPDATE items SET cancelled_at=CURRENT_TIMESTAMP,cancelled_by=?,cancellation_reason=? WHERE id=?', [$u['id'],$reason,$itemId]);
    }

    public function insertStationAlerts(mixed $old, mixed $orderId, mixed $reason, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO station_alerts(ticket_id,kind,description,reason,created_by) VALUES(?,?,?,?,?)', [$old['old_ticket_id'],'item_cancelled',$old['quantity'].'× '.$old['name'].' aus Bestellung #'.str_pad((string)$orderId, 6, '0', STR_PAD_LEFT).' entfernt',$reason,$u['id']]);
    }

    public function findItems2(mixed $old): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM items WHERE ticket_id=? AND cancelled_at IS NULL', [$old['old_ticket_id']]);
    }

    public function updateTickets(mixed $old): \PDOStatement
    {
        return $this->execute('UPDATE tickets SET status=\'cancelled\' WHERE id=?', [$old['old_ticket_id']]);
    }

    public function insertEvents3(mixed $old, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,\'cancelled\')', [$old['old_ticket_id'],$u['id']]);
    }

    public function insertEvents4(mixed $old, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,\'item_removed\')', [$old['old_ticket_id'],$u['id']]);
    }

    public function findMenu2(mixed $menuId): \PDOStatement
    {
        return $this->execute('SELECT * FROM menu WHERE id=? AND active=1 FOR UPDATE', [$menuId]);
    }

    public function insertTickets2(mixed $orderId, mixed $product, mixed $mode): \PDOStatement
    {
        return $this->execute('INSERT INTO tickets(order_id,station,origin) VALUES(?,?,?)', [$orderId,$product['station'],$mode === 'add' ? 'addition' : 'correction']);
    }

    public function insertEvents5(mixed $ticketId, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,\'new\')', [$ticketId,$u['id']]);
    }

    public function insertItems2(mixed $ticketId, mixed $menuId, mixed $product, mixed $quantity, mixed $note): \PDOStatement
    {
        return $this->execute('INSERT INTO items(ticket_id,menu_id,name,quantity,price,note) VALUES(?,?,?,?,?,?)', [$ticketId,$menuId,$product['name'],$quantity,$product['price'],$note]);
    }

    public function findItems3(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT COUNT(*) AS amount FROM items i JOIN tickets t ON t.id=i.ticket_id WHERE t.order_id=? AND i.cancelled_at IS NULL', [$orderId]);
    }

    public function updateOrders2(mixed $u, mixed $reason, mixed $orderId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET cancelled_at=CURRENT_TIMESTAMP,cancelled_by=?,cancellation_reason=? WHERE id=?', [$u['id'],$reason,$orderId]);
    }

    public function insertOrderChanges(mixed $key, mixed $orderId, mixed $u, mixed $mode, mixed $reason, mixed $before, mixed $after): \PDOStatement
    {
        return $this->execute('INSERT INTO order_changes(request_key,order_id,user_id,action,reason,before_state,after_state) VALUES(?,?,?,?,?,?,?)', [$key,$orderId,$u['id'],$mode,$reason,$before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,$after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null]);
    }

    public function findOrders4(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT table_id,visit_id FROM orders WHERE id=?', [$orderId]);
    }

    public function findDiningTables3(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT id FROM dining_tables WHERE id=? FOR UPDATE', [$reference['table_id']]);
    }

    public function findTableVisits3(mixed $reference): \PDOStatement
    {
        return $this->execute('SELECT paid_at FROM table_visits WHERE id=? FOR UPDATE', [$reference['visit_id']]);
    }

    public function findOrders5(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT cancelled_at,paid_at FROM orders WHERE id=? FOR UPDATE', [$orderId]);
    }

    public function findTickets2(mixed $orderId): \PDOStatement
    {
        return $this->execute('SELECT id,status FROM tickets WHERE order_id=? FOR UPDATE', [$orderId]);
    }

    public function updateOrders3(mixed $u, mixed $reason, mixed $orderId): \PDOStatement
    {
        return $this->execute('UPDATE orders SET cancelled_at=CURRENT_TIMESTAMP,cancelled_by=?,cancellation_reason=? WHERE id=?', [$u['id'],$reason,$orderId]);
    }

    public function insertStationAlerts2(mixed $ticket, mixed $orderId, mixed $reason, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO station_alerts(ticket_id,kind,description,reason,created_by) VALUES(?,?,?,?,?)', [$ticket['id'],'order_cancelled','Bestellung #'.str_pad((string)$orderId, 6, '0', STR_PAD_LEFT).' vollständig storniert',$reason,$u['id']]);
    }

    public function updateItems2(mixed $u, mixed $reason, mixed $ticket): \PDOStatement
    {
        return $this->execute('UPDATE items SET cancelled_at=CURRENT_TIMESTAMP,cancelled_by=?,cancellation_reason=? WHERE ticket_id=? AND cancelled_at IS NULL', [$u['id'],$reason,$ticket['id']]);
    }

    public function updateTickets2(mixed $ticket): \PDOStatement
    {
        return $this->execute('UPDATE tickets SET status=\'cancelled\' WHERE id=?', [$ticket['id']]);
    }

    public function insertEvents6(mixed $ticket, mixed $u): \PDOStatement
    {
        return $this->execute('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,\'cancelled\')', [$ticket['id'],$u['id']]);
    }

    public function insertOrderChanges2(mixed $orderId, mixed $u, mixed $reason): \PDOStatement
    {
        return $this->execute('INSERT INTO order_changes(request_key,order_id,user_id,action,reason,after_state) VALUES(?,?,?,?,?,?)', [bin2hex(random_bytes(20)),$orderId,$u['id'],'order_cancel',$reason,json_encode(['cancelled' => true])]);
    }
}
