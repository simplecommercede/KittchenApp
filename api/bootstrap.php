<?php
declare(strict_types=1);
date_default_timezone_set('UTC');
require_once __DIR__.'/vendor/autoload.php';

// Release layout: <app-root>/releases/<id>/public/api; credentials stay in <app-root>/shared.
// Fixed DocumentRoot hosting can provide a path-only locator (never credentials).
$config=getenv('APP_CONFIG_FILE');
if (($config===false || $config==='') && is_file(__DIR__.'/config-path.php')) {
    $config=require __DIR__.'/config-path.php';
    if (!is_string($config) || $config==='') throw new RuntimeException('Ungültiger Konfigurationspfad.');
}
if ($config!==false && $config!=='') {
    Kitchen\Infrastructure\Environment::load($config);
} else {
    $defaultConfig=dirname(__DIR__,4).'/shared/app.ini';
    if(is_file($defaultConfig)) Kitchen\Infrastructure\Environment::load($defaultConfig);
}
