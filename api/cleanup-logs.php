<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';
try {
    $directory = getenv('LOG_DIR');
    if ($directory === false || $directory === '') throw new RuntimeException('LOG_DIR fehlt.');
    $removed = Kitchen\Logging\DailyLog::cleanup($directory);
    echo "Logbereinigung: $removed Dateien entfernt.\n";
} catch (Throwable) {
    fwrite(STDERR, "Logbereinigung fehlgeschlagen; LOG_DIR und Rechte prüfen.\n");
    exit(1);
}
