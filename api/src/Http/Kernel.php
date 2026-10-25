<?php

declare(strict_types=1);

namespace Kitchen\Http;

use Kitchen\Infrastructure\Database;
use Kitchen\Logging\EventId;
use Kitchen\Logging\Log;
use Kitchen\Logging\Redactor;
use PDOException;
use Throwable;

use function Kitchen\Support\{fail, user, rollbackQuietly};

final class Kernel
{
    // Request and response fields copied into the log: IDs, amounts and fixed codes only, never names, notes, reasons or credentials.
    private const LOGGED_FIELDS = ['id','table_id','visit_id','order_id','item_id','menu_id','quantity','status','mode','station','active','expected_total','view','archive','new_item_id','ids','duplicate','order_cancelled','visit_closed','total','amount'];

    public function run(): void
    {
        $started = hrtime(true);
        Log::begin();
        ini_set('display_errors', '0');
        $this->captureRuntimeErrors();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Request-Id: '.Log::requestId());
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true,'secure' => getenv('APP_ENV') !== 'local','samesite' => 'Strict','path' => '/']);
        session_start();
        $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'me';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $event = EventId::RequestInvalid;
        $body = [];
        $u = [];
        try {
            if ($method === 'POST') {
                $raw = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536 ? false : file_get_contents('php://input', false, null, 0, 65537);
                if ($raw === false || strlen($raw) > 65536) {
                    fail('Anfrage ist zu groß.', 413);
                } try {
                    $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    fail('Ungültiges JSON.');
                } if (!is_array($body)) {
                    $body = [];
                    fail('Ungültige Anfrage.');
                }
                $event = EventId::CsrfFailed;
                if (!hash_equals($_SESSION['csrf'] ?? 'invalid', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
                    fail('Sicherheitsprüfung fehlgeschlagen.', 403);
                }
            }
            $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
            $event = EventId::forRequest($action, $method);

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
            $this->logResponse($event, $action, $method, $body, $u, $response, $started);
            $response->send();
        } catch (PDOException $e) {
            rollbackQuietly();
            $duplicate = in_array($e->getCode(), ['23000', '23505'], true);
            // The reference is the request ID, so staff can name the matching log entry.
            $response = new Response(['error' => $duplicate ? 'Datensatz bereits vorhanden. Bei einer Bestellung bitte mit derselben Bestellkennung wiederholen.' : 'Datenbank vorübergehend nicht erreichbar.','reference' => Log::requestId()], $duplicate ? 409 : 503);
            $this->logResponse($event, $action, $method, $body, $u, $response, $started, $e);
            $response->send();
        } catch (Throwable $e) {
            rollbackQuietly();
            $response = new Response(['error' => 'Interner Fehler.','reference' => Log::requestId()], 500);
            $this->logResponse($event, $action, $method, $body, $u, $response, $started, $e);
            $response->send();
        }
    }

    /** One entry per request: event, outcome, user and whitelisted IDs; an exception only in redacted form. */
    private function logResponse(EventId $event, string $action, string $method, array $body, array $u, Response $response, int $started, ?Throwable $exception = null): void
    {
        $event = Log::currentEvent() ?? $event;
        $status = $response->status;
        $outcome = match (true) {
            $status < 300 => 'ok',
            $status === 401, $status === 403 => 'denied',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'error',
            default => 'rejected',
        };
        $level = match (true) {
            $status >= 500 => 'error',
            $exception !== null, $outcome === 'denied', $outcome === 'rate_limited' => 'warning',
            $status < 300 && $method === 'GET' => 'debug',
            default => 'info',
        };
        $data = is_array($response->data) && !array_is_list($response->data) ? $response->data : [];
        $userId = $u['id'] ?? $_SESSION['uid'] ?? null;
        // Error texts are fixed strings from fail(), never user input.
        Log::write($level, $event, is_string($data['error'] ?? null) ? $data['error'] : $outcome, [
            'action' => preg_match('/^[a-z_]{1,40}$/', $action) ? $action : '(ungültig)',
            'method' => in_array($method, ['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'], true) ? $method : '(ungültig)',
            'status' => $status,
            'outcome' => $outcome,
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'user_id' => $userId === null ? null : (int)$userId,
            'ip' => Redactor::ip((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            ...array_filter(['input' => self::safeFields([...$_GET, ...$body]),'result' => self::safeFields($data)]),
            ...Log::collected(),
        ], $exception);
    }

    /** @return array<string, int|bool|string|list<int>> */
    public static function safeFields(array $values): array
    {
        $safe = [];
        foreach (self::LOGGED_FIELDS as $field) {
            $value = $values[$field] ?? null;
            if (is_int($value) || is_bool($value) || (is_string($value) && preg_match('/^[a-z0-9_]{1,20}$/', $value))) {
                $safe[$field] = $value;
            } elseif (is_array($value) && array_is_list($value) && count($value) <= 20 && array_filter($value, 'is_int') === $value) {
                $safe[$field] = $value;
            }
        }
        return $safe;
    }

    /** PHP warnings and fatal errors go to the same log, redacted and with the request ID, never into the response. */
    private function captureRuntimeErrors(): void
    {
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            Log::write('warning', EventId::PhpWarning, Redactor::message($message), ['at' => Redactor::path($file).':'.$line,'trace' => Redactor::frames(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1))]);
            return true;
        });
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Log::write('error', EventId::FatalError, Redactor::message($error['message']), ['at' => Redactor::path($error['file']).':'.$error['line']]);
            }
        });
    }
}
