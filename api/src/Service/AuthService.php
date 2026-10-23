<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Repository\AuthRepository;

use function Kitchen\Support\{out, fail, user, shortText, passwordText, loginFailure};

final class AuthService
{
    public function __construct(private AuthRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'session' && $method === 'GET') {
            out(['csrf' => $_SESSION['csrf'],'user' => isset($_SESSION['uid']) ? user() : null]);
        }
        if ($action === 'login' && $method === 'POST') {
            $username = shortText($body['username'] ?? '', 80);
            $password = passwordText($body['password'] ?? '');
            // Two limits: 5 failures per username and address (5 min), 30 failures per address regardless of username (15 min).
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $key = hash('sha256', strtolower($username).'|'.$ip);
            $ipKey = hash('sha256', 'ip|'.$ip);
            if ($this->repository->findLoginAttempts($key, $ipKey)->fetch()) {
                fail('Zu viele Versuche. Bitte später erneut versuchen.', 429);
            }
            $u = $this->repository->findUsers($username)->fetch();
            if (!$u || !password_verify($password, $u['password'])) {
                loginFailure($key, 5, 5);
                loginFailure($ipKey, 30, 15);
                $this->repository->deleteLoginAttempts();
                fail('Benutzername oder Passwort falsch.', 401);
            }
            $this->repository->deleteLoginAttempts2($key);
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            out(['user' => user(),'csrf' => $_SESSION['csrf']]);
        }
        if ($action === 'logout' && $method === 'POST') {
            $this->repository->updateUsers($u);
            $_SESSION = [];
            session_destroy();
            out(['ok' => true]);
        }
    }
}
