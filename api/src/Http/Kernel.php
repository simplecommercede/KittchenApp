<?php

declare(strict_types=1);

namespace Kitchen\Http;

use Kitchen\Infrastructure\Database;
use PDOException;
use Throwable;

use function Kitchen\Support\{fail, user, rollbackQuietly};

final class Kernel
{
    public function run(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true,'secure' => getenv('APP_ENV') !== 'local','samesite' => 'Strict','path' => '/']);
        session_start();
        try {
            $action = $_GET['action'] ?? 'me';
            $method = $_SERVER['REQUEST_METHOD'];
            $body = [];
            if ($method === 'POST') {
                $raw = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536 ? false : file_get_contents('php://input', false, null, 0, 65537);
                if ($raw === false || strlen($raw) > 65536) {
                    fail('Anfrage ist zu groß.', 413);
                } try {
                    $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    fail('Ungültiges JSON.');
                } if (!is_array($body)) {
                    fail('Ungültige Anfrage.');
                } if (!hash_equals($_SESSION['csrf'] ?? 'invalid', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                    fail('Sicherheitsprüfung fehlgeschlagen.', 403);
                }
            }
            $_SESSION['csrf'] ??= bin2hex(random_bytes(32));

            $database = Database::instance();
            $u = in_array($action, ['session','login'], true) ? [] : user();
            $service = match($action) {
                'session' => new \Kitchen\Service\AuthService(new \Kitchen\Repository\AuthRepository($database)),
                'login' => new \Kitchen\Service\AuthService(new \Kitchen\Repository\AuthRepository($database)),
                'logout' => new \Kitchen\Service\AuthService(new \Kitchen\Repository\AuthRepository($database)),
                'menu' => new \Kitchen\Service\CatalogService(new \Kitchen\Repository\CatalogRepository($database)),
                'tables' => new \Kitchen\Service\CatalogService(new \Kitchen\Repository\CatalogRepository($database)),
                'table_status' => new \Kitchen\Service\TableService(new \Kitchen\Repository\TableRepository($database)),
                'table_bill' => new \Kitchen\Service\TableService(new \Kitchen\Repository\TableRepository($database)),
                'tickets' => new \Kitchen\Service\TicketService($database, new \Kitchen\Repository\TicketRepository($database)),
                'station_alert_ack' => new \Kitchen\Service\TicketService($database, new \Kitchen\Repository\TicketRepository($database)),
                'status' => new \Kitchen\Service\TicketService($database, new \Kitchen\Repository\TicketRepository($database)),
                'order' => new \Kitchen\Service\OrderService($database, new \Kitchen\Repository\OrderRepository($database)),
                'order_change' => new \Kitchen\Service\OrderService($database, new \Kitchen\Repository\OrderRepository($database)),
                'order_cancel' => new \Kitchen\Service\OrderService($database, new \Kitchen\Repository\OrderRepository($database)),
                'payments' => new \Kitchen\Service\PaymentService($database, new \Kitchen\Repository\PaymentRepository($database)),
                'table_paid' => new \Kitchen\Service\PaymentService($database, new \Kitchen\Repository\PaymentRepository($database)),
                'order_paid' => new \Kitchen\Service\PaymentService($database, new \Kitchen\Repository\PaymentRepository($database)),
                'payment_reverse' => new \Kitchen\Service\PaymentService($database, new \Kitchen\Repository\PaymentRepository($database)),
                'service_calls', 'service_call', 'service_call_ack', 'service_call_cancel' => new \Kitchen\Service\ServiceCallService($database, new \Kitchen\Repository\ServiceCallRepository($database)),
                'admin_users' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_user' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_user_update' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_user_status' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_menu' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_menu_status' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_drink' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_table' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                'admin_table_status' => new \Kitchen\Service\AdminService($database, new \Kitchen\Repository\AdminRepository($database)),
                default => null,
            };
            $service?->handle($action, $method, $body, $u);
            fail('Route nicht gefunden.', 404);
        } catch (Response $response) {
            rollbackQuietly();
            $response->send();
        } catch (PDOException $e) {
            rollbackQuietly();
            error_log($e->getMessage());
            (new Response(['error' => $e->getCode() === '23000' ? 'Datensatz bereits vorhanden. Bei einer Bestellung bitte mit derselben Bestellkennung wiederholen.' : 'Datenbank vorübergehend nicht erreichbar.'], $e->getCode() === '23000' ? 409 : 503))->send();
        } catch (Throwable $e) {
            rollbackQuietly();
            error_log((string)$e);
            (new Response(['error' => 'Interner Fehler.'], 500))->send();
        }
    }
}
