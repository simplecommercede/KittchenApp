<?php
declare(strict_types=1);
// Compatibility entry point for CLI migrations; application code uses Database/Repositories.
require_once __DIR__.'/bootstrap.php';
function db(): PDO {return Kitchen\Infrastructure\Database::instance()->connection();}
function dbConnection(bool $connect): ?PDO {return $connect?db():Kitchen\Infrastructure\Database::instance()->current();}
// Command line: an uncaught error is logged redacted under the script's event; the console gets no stack trace with arguments.
if (PHP_SAPI === 'cli') {
    set_exception_handler(function (Throwable $e): void {
        Kitchen\Logging\Log::write('error', Kitchen\Logging\Log::currentEvent() ?? Kitchen\Logging\EventId::CliFailed, 'Befehl abgebrochen.', ['script' => basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),'outcome' => 'error'], $e);
        fwrite(STDERR, 'Abgebrochen: '.Kitchen\Logging\Redactor::message($e->getMessage()).' (Log-Referenz '.Kitchen\Logging\Log::requestId().")\n");
        exit(1);
    });
}
function query(string $sql,array $params=[]): PDOStatement {return (new Kitchen\Repository\Repository(Kitchen\Infrastructure\Database::instance()))->execute($sql,$params);}
