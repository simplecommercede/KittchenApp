<?php

declare(strict_types=1);

namespace Kitchen\Logging;

use Throwable;

/**
 * One JSON line per entry, to daily files in LOG_DIR or the PHP error log (Docker: container output).
 * Non-ASCII is \u-escaped, because Apache's error log would otherwise turn it into invalid \x escapes.
 * Entries carry only IDs, codes and fixed texts; free text from requests is never written.
 * LOG_LEVEL (debug|info|warning|error, default info) filters; logging never breaks a request.
 */
final class Log
{
    private const LEVELS = ['debug' => 0,'info' => 1,'warning' => 2,'error' => 3];

    private static ?string $requestId = null;
    private static ?EventId $event = null;
    /** @var array<string, mixed> */
    private static array $context = [];

    /** Starts a new request scope with a fresh correlation ID. */
    public static function begin(): void
    {
        self::$requestId = bin2hex(random_bytes(8));
        self::$event = null;
        self::$context = [];
    }

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    /** Lets a service refine the request's event, e.g. a failed login instead of a login. */
    public static function event(EventId $event): void
    {
        self::$event = $event;
    }

    public static function currentEvent(): ?EventId
    {
        return self::$event;
    }

    /** Adds safe values (IDs, codes) to the request's final log entry. */
    public static function context(array $values): void
    {
        self::$context = [...self::$context, ...$values];
    }

    /** @return array<string, mixed> */
    public static function collected(): array
    {
        return self::$context;
    }

    public static function write(string $level, EventId $event, string $message, array $context = [], ?Throwable $exception = null): void
    {
        try {
            $threshold = self::LEVELS[strtolower((string)getenv('LOG_LEVEL'))] ?? self::LEVELS['info'];
            if ((self::LEVELS[$level] ?? self::LEVELS['error']) < $threshold) {
                return;
            }
            $entry = [
                'ts' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
                'level' => $level,
                'event_id' => $event->value,
                'event' => $event->name,
                'request_id' => self::requestId(),
                'message' => $message,
                ...$context,
            ];
            if ($exception !== null) {
                $entry['exception'] = Redactor::exception($exception);
            }
            $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $directory = getenv('LOG_DIR');
            if ($directory !== false && $directory !== '') {
                // Do not copy personal event data into an uncontrolled fallback log.
                DailyLog::append($directory, (string)$line);
            } else {
                error_log((string)$line);
            }
        } catch (Throwable) {
            error_log("Kitchen logging unavailable; check private LOG_DIR and permissions.");
        }
    }
}
