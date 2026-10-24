<?php

declare(strict_types=1);

namespace Kitchen\Repository;

final class AuthRepository extends Repository
{
    public function findLoginAttempts(mixed $key, mixed $ipKey): \PDOStatement
    {
        return $this->execute('SELECT 1 FROM login_attempts WHERE identity_hash IN (?,?) AND blocked_until>NOW() LIMIT 1', [$key,$ipKey]);
    }

    public function findUsers(mixed $username): \PDOStatement
    {
        return $this->execute('SELECT * FROM users WHERE username=? AND active=1', [$username]);
    }

    public function deleteLoginAttempts(): \PDOStatement
    {
        return $this->execute('DELETE FROM login_attempts WHERE updated_at<'. $this->database->ago(1, 'DAY') . ' AND (blocked_until IS NULL OR blocked_until<NOW())');
    }

    public function deleteLoginAttempts2(mixed $key): \PDOStatement
    {
        return $this->execute('DELETE FROM login_attempts WHERE identity_hash=?', [$key]);
    }

    public function updateUsers(mixed $u): \PDOStatement
    {
        return $this->execute('UPDATE users SET last_view=NULL WHERE id=?', [$u['id']]);
    }
}
