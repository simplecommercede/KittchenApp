<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db.php';
$connection=db();
try {
    $connection->beginTransaction();
    $connection->exec('SELECT pg_advisory_xact_lock(78246193)');
    $hadAddons=query("SELECT to_regclass('public.menu_addons')")->fetchColumn()!==null;
    $connection->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(80) PRIMARY KEY, applied_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    foreach(glob(__DIR__.'/migrations/postgresql/*.sql') ?: [] as $file) {
        $name='pgsql-'.basename($file,'.sql');
        if(query('SELECT name FROM schema_migrations WHERE name=?',[$name])->fetch()) continue;
        $sql=file_get_contents($file);
        if($sql===false)throw new RuntimeException('Migration nicht lesbar.');
        $connection->exec($sql);
        query('INSERT INTO schema_migrations(name) VALUES(?)',[$name]);
        echo "Migration $name ausgeführt.\n";
    }
    require_once __DIR__.'/seed.php';
    seedDatabase($hadAddons);
    $connection->commit();
} catch(Throwable $e) {
    if($connection->inTransaction())$connection->rollBack();
    fwrite(STDERR,'Migration fehlgeschlagen: '.$e->getMessage()."\n");
    exit(1);
}
