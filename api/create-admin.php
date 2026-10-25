<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/db.php';
Kitchen\Logging\Log::event(Kitchen\Logging\EventId::CreateAdmin);
$username=getenv('ADMIN_USERNAME') ?: 'admin';
$password=getenv('ADMIN_PASSWORD') ?: '';
if(strlen($password)<12){Kitchen\Logging\Log::write('info', Kitchen\Logging\EventId::CreateAdmin, 'Passwort zu kurz.', ['outcome' => 'rejected']);fwrite(STDERR,"Set ADMIN_PASSWORD to at least 12 characters.\n");exit(1);}
query('INSERT INTO users(name,username,password,roles) VALUES(?,?,?,?)',['Administrator',$username,password_hash($password,PASSWORD_DEFAULT),json_encode(['admin'])]);
Kitchen\Logging\Log::write('info', Kitchen\Logging\EventId::CreateAdmin, 'Administrator angelegt.', ['outcome' => 'ok','user_id' => (int)db()->lastInsertId()]);
echo "Administrator created.\n";
