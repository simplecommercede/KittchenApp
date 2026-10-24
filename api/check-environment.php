<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    if (version_compare(PHP_VERSION, '8.4.0', '<')) throw new RuntimeException('PHP 8.4 oder neuer erforderlich.');
    foreach (['pdo', 'pdo_pgsql', 'mbstring', 'session'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('PHP-Erweiterung fehlt: '.$extension);
    }
    require __DIR__.'/bootstrap.php';
    if (getenv('APP_ENV') !== 'production' || getenv('DB_DRIVER') !== 'pgsql') {
        throw new RuntimeException('APP_ENV=production und DB_DRIVER=pgsql erforderlich.');
    }
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
        if (!getenv($key) || getenv($key) === 'EINTRAGEN') throw new RuntimeException($key.' muss ausgefüllt werden.');
    }
    $database = Kitchen\Infrastructure\Database::instance()->connection();
    $database->query('SELECT 1');
    echo "PHP und PostgreSQL-Verbindung OK; Live-Konfiguration geladen.\n";
    echo "Keine Migration und keine Datenänderung ausgeführt.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Datenbankverbindung fehlgeschlagen. Host, Port, TLS und Zugangsdaten prüfen.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
