<?php
// Executed only inside the disposable PostgreSQL test stack, never on the live DB.
declare(strict_types=1);
require '/var/www/html/api/db.php';
$checks = [
    'Keine Demo-Benutzer' => (int)query('SELECT COUNT(*) FROM users')->fetchColumn() === 0,
    'Keine Demo-Tische' => (int)query('SELECT COUNT(*) FROM dining_tables')->fetchColumn() === 0,
    'Speisekarte initialisiert' => (int)query('SELECT COUNT(*) FROM menu')->fetchColumn() > 8,
    'Zusätze verknüpft' => (int)query('SELECT COUNT(*) FROM menu_addons')->fetchColumn() > 0,
    'Keine doppelten Gerichte' => (int)query('SELECT COUNT(*)-COUNT(DISTINCT name) FROM menu')->fetchColumn() === 0,
];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'OK: ' : 'FEHLER: ').$label."\n";
    if (!$ok) exit(1);
}
