<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class SupportRepository extends Repository
{
    public function findUsers(mixed $session): \PDOStatement
    {
        return $this->execute('SELECT id,name,username,roles FROM users WHERE id=? AND active=1', [$session['uid']]);
    }

    public function findUsers2(): \PDOStatement
    {
        return $this->execute('SELECT roles FROM users WHERE active=1 AND last_view="bar" AND last_seen>NOW()-INTERVAL 30 SECOND');
    }

    public function insertLoginAttempts(mixed $key, mixed $minutes, mixed $limit): \PDOStatement
    {
        return $this->execute('INSERT INTO login_attempts(identity_hash,attempts,blocked_until,updated_at) VALUES(?,1,NULL,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(updated_at<NOW()-INTERVAL ? MINUTE OR (blocked_until IS NOT NULL AND blocked_until<NOW()),1,attempts+1), blocked_until=IF(attempts>=?,NOW()+INTERVAL ? MINUTE,NULL), updated_at=NOW()', [$key,$minutes,$limit,$minutes]);
    }

    public function findOrders(mixed $unpaidOnly, mixed $visitId): \PDOStatement
    {
        return $this->execute('SELECT COALESCE(SUM(i.quantity*i.price),0) AS total FROM orders o JOIN tickets t ON t.order_id=o.id JOIN items i ON i.ticket_id=t.id WHERE o.visit_id=? AND o.cancelled_at IS NULL AND i.cancelled_at IS NULL'.($unpaidOnly ? ' AND o.paid_at IS NULL' : ''), [$visitId]);
    }
}
