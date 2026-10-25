<?php

declare(strict_types=1);

namespace Kitchen\Logging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Private daily files; retains today and the six preceding Berlin calendar dates. */
final class DailyLog
{
    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin'));
    }

    public static function prepare(string $directory): void
    {
        if (!str_starts_with($directory, '/') || is_link($directory)) {
            throw new RuntimeException('LOG_DIR muss ein absoluter privater Verzeichnispfad sein.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Logverzeichnis nicht verfügbar.');
        }
        if (!@chmod($directory, 0700)) {
            throw new RuntimeException('Logverzeichnis nicht geschützt.');
        }
    }

    public static function append(string $directory, string $line): void
    {
        self::prepare($directory);
        self::cleanup($directory);
        $path = $directory.'/debug_'.self::today()->format('Y-m-d').'.log';
        if (is_link($path)) {
            throw new RuntimeException('Logdatei darf kein Symlink sein.');
        }
        $mask = umask(0077);
        try {
            $handle = @fopen($path, 'ab');
        } finally {
            umask($mask);
        }
        if ($handle === false) {
            throw new RuntimeException('Logdatei nicht schreibbar.');
        }
        try {
            if (!@chmod($path, 0600) || !flock($handle, LOCK_EX)) {
                throw new RuntimeException('Logdatei nicht geschützt oder gesperrt.');
            }
            if (fwrite($handle, $line."\n") !== strlen($line) + 1) {
                throw new RuntimeException('Logeintrag unvollständig.');
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public static function cleanup(string $directory, ?DateTimeImmutable $today = null): int
    {
        self::prepare($directory);
        $cutoff = ($today ?? self::today())->modify('-6 days')->format('Y-m-d');
        $removed = 0;
        foreach (glob($directory.'/debug_????-??-??.log') ?: [] as $file) {
            if (is_link($file) || !is_file($file) || !preg_match('/^debug_(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $match)) {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
            if ($date === false || $date->format('Y-m-d') !== $match[1] || $match[1] >= $cutoff) {
                continue;
            }
            if (!@unlink($file) && is_file($file)) {
                throw new RuntimeException('Abgelaufene Logdatei konnte nicht gelöscht werden.');
            }
            $removed++;
        }
        return $removed;
    }
}
