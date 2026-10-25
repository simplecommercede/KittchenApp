<?php

declare(strict_types=1);

namespace Kitchen\Logging;

use PDOException;
use Throwable;

/** Exceptions keep location, codes and argument-free traces, never raw messages or SQL details. */
final class Redactor
{
    private const MAX_FRAMES = 20;
    private const MAX_PREVIOUS = 3;

    /** Arbitrary exception messages may contain any user data; never persist them. */
    public static function message(string $message): string
    {
        return 'Fehlerdetails aus Datenschutzgründen nicht protokolliert.';
    }

    /** @return array<string, mixed> */
    public static function exception(Throwable $e, int $depth = 0): array
    {
        $data = [
            'class' => $e::class,
            'code' => is_int($e->getCode()) || preg_match('/^[0-9A-Z]{5}$/', (string)$e->getCode()) ? $e->getCode() : null,
            'message' => self::message($e->getMessage()),
            'at' => self::path($e->getFile()).':'.$e->getLine(),
        ];
        if ($e instanceof PDOException && is_array($e->errorInfo)) {
            $data['sqlstate'] = is_string($e->errorInfo[0] ?? null) && preg_match('/^[0-9A-Z]{5}$/', $e->errorInfo[0]) ? $e->errorInfo[0] : null;
            $data['driver_code'] = is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : null;
        }
        $data['trace'] = self::frames($e->getTrace());
        if ($e->getPrevious() !== null && $depth < self::MAX_PREVIOUS) {
            $data['previous'] = self::exception($e->getPrevious(), $depth + 1);
        }
        return $data;
    }

    /** @return list<string> "src/Service/OrderService.php:415 Kitchen\Repository\OrderRepository->findMenu()" */
    public static function frames(array $trace): array
    {
        $frames = [];
        foreach (array_slice($trace, 0, self::MAX_FRAMES) as $frame) {
            $at = isset($frame['file']) ? self::path($frame['file']).':'.($frame['line'] ?? 0) : '[internal]';
            $frames[] = $at.' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()';
        }
        return $frames;
    }

    /** Path relative to the API directory, so logs do not depend on the release folder. */
    public static function path(string $file): string
    {
        $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR;
        return str_starts_with($file, $root) ? substr($file, strlen($root)) : basename($file);
    }

    /** IPv4 without the last octet, IPv6 reduced to /48: reduced precision, still potentially personal data. */
    public static function ip(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        $keep = strlen($packed) === 4 ? 3 : 6;
        return (string)inet_ntop(substr($packed, 0, $keep).str_repeat("\0", strlen($packed) - $keep));
    }
}
