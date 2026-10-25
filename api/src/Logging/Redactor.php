<?php

declare(strict_types=1);

namespace Kitchen\Logging;

use PDOException;
use Throwable;

/**
 * Turns exceptions into log data without personal data or secrets: values in messages are masked,
 * stack frames carry file, line and function but never arguments. Identifiers (tables, columns,
 * constraints), SQLSTATE and driver codes stay, because they are what locates the fault.
 */
final class Redactor
{
    private const MASK = '***';
    private const MAX_MESSAGE = 500;
    private const MAX_FRAMES = 20;
    private const MAX_PREVIOUS = 3;
    // A quoted word after one of these is a schema identifier, not user data.
    private const IDENTIFIERS = ['key','column','table','constraint','relation','index','database','schema','function','type','field','trigger','sequence','view'];

    public static function message(string $message): string
    {
        // Quoted values ('max', "Mia") unless they name a schema object.
        $message = (string)preg_replace_callback('/(?<word>\b[A-Za-z_]+\s+)?(?<q>[\'"])(?<value>.*?)(?P=q)/su', function (array $m): string {
            $word = strtolower(trim($m['word'] ?? ''));
            return in_array($word, self::IDENTIFIERS, true) ? $m[0] : $m['word'].$m['q'].self::MASK.$m['q'];
        }, $message);
        // PostgreSQL details: Key (username)=(max) / Failing row contains (1, Mia, ...).
        $message = (string)preg_replace('/\)=\([^)]*\)/u', ')=('.self::MASK.')', $message);
        $message = (string)preg_replace('/\bcontains\s*\([^)]*\)/iu', 'contains ('.self::MASK.')', $message);
        $message = (string)preg_replace('/\b(password|passwd|pwd|secret|token|api[_-]?key|authorization|csrf)\b(\s*[=:]\s*)\S+/iu', '$1$2'.self::MASK, $message);
        $message = (string)preg_replace('/[^\s@\'"()<>]+@[^\s@\'"()<>]+\.[A-Za-z]{2,}/u', self::MASK, $message);
        // Long mixed letter/digit strings: tokens, session IDs, hashes.
        $message = (string)preg_replace('/\b(?=[A-Za-z0-9+=_$.-]*\d)(?=[A-Za-z0-9+=_$.-]*[A-Za-z])[A-Za-z0-9+=_$.-]{24,}/u', self::MASK, $message);
        return mb_strlen($message) > self::MAX_MESSAGE ? mb_substr($message, 0, self::MAX_MESSAGE).'…' : $message;
    }

    /** @return array<string, mixed> */
    public static function exception(Throwable $e, int $depth = 0): array
    {
        $data = [
            'class' => $e::class,
            'code' => $e->getCode(),
            'message' => self::message($e->getMessage()),
            'at' => self::path($e->getFile()).':'.$e->getLine(),
        ];
        if ($e instanceof PDOException && is_array($e->errorInfo)) {
            $data['sqlstate'] = $e->errorInfo[0] ?? null;
            $data['driver_code'] = $e->errorInfo[1] ?? null;
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

    /** IPv4 without the last octet, IPv6 reduced to /48: enough for abuse patterns, not a person. */
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
