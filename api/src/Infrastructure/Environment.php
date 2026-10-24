<?php

declare(strict_types=1);

namespace Kitchen\Infrastructure;

/** Optional private INI file; real process environment always takes precedence. */
final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Konfigurationsdatei fehlt oder ist nicht lesbar.');
        }
        $values = parse_ini_file($path, false, INI_SCANNER_RAW);
        if ($values === false) {
            throw new \RuntimeException('Konfigurationsdatei ist ungültig.');
        }
        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || !is_string($value) || str_contains($value, "\n") || str_contains($value, "\0")) {
                throw new \RuntimeException('Ungültiger Konfigurationseintrag.');
            }
            if (getenv($key) === false) {
                putenv($key.'='.$value);
            }
        }
    }
}
