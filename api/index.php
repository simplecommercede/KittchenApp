<?php
declare(strict_types=1);
require __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>getenv('APP_ENV')!=='local','samesite'=>'Strict','path'=>'/']);
session_start();
function out(mixed $data,int $code=200): never { http_response_code($code); echo json_encode($data,JSON_UNESCAPED_UNICODE); exit; }
function fail(string $message,int $code=400): never {out(['error'=>$message],$code);}
function user(): array { if(!isset($_SESSION['uid'])) fail('Bitte anmelden.',401); $u=query('SELECT id,name,username,roles FROM users WHERE id=? AND active=1',[$_SESSION['uid']])->fetch(); if(!$u) fail('Zugang gesperrt.',401); $u['roles']=json_decode($u['roles'],true); return $u; }
function allows(array $u,string $role): bool {return in_array('admin',$u['roles'],true)||in_array($role,$u['roles'],true);}
function need(array $u,string $role): void {if(!allows($u,$role)) fail('Keine Berechtigung.',403);}
function shortText(mixed $v,int $max): string {if(!is_string($v)||strlen($v)>$max) fail('Ungültiger Text.');return trim($v);}
function positive(mixed $v,int $max=999999): int {if(!is_int($v)||$v<1||$v>$max) fail('Ungültige Zahl.');return $v;}
try {
 $action=$_GET['action']??'me'; $method=$_SERVER['REQUEST_METHOD'];
 $body=[];
 if($method==='POST') { try {$body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);}catch(Throwable){fail('Ungültiges JSON.');} if(!is_array($body)) fail('Ungültige Anfrage.'); if(!hash_equals($_SESSION['csrf']??'invalid',$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail('Sicherheitsprüfung fehlgeschlagen.',403); }
 $_SESSION['csrf']??=bin2hex(random_bytes(32));
 if($action==='session' && $method==='GET') out(['csrf'=>$_SESSION['csrf'],'user'=>isset($_SESSION['uid'])?user():null]);
 if($action==='login' && $method==='POST') {
  $username=shortText($body['username']??'',80); $password=shortText($body['password']??'',200);
  $key=hash('sha256',strtolower($username).'|'.($_SERVER['REMOTE_ADDR']??''));
  $attempt=query('SELECT * FROM login_attempts WHERE identity_hash=?',[$key])->fetch();
  if($attempt && $attempt['blocked_until'] && strtotime($attempt['blocked_until'])>time()) fail('Zu viele Versuche. Bitte später erneut versuchen.',429);
  $u=query('SELECT * FROM users WHERE username=? AND active=1',[$username])->fetch();
  if(!$u||!password_verify($password,$u['password'])) {query('INSERT INTO login_attempts(identity_hash,attempts,blocked_until) VALUES(?,1,NULL) ON DUPLICATE KEY UPDATE attempts=IF(blocked_until IS NOT NULL AND blocked_until<NOW(),1,attempts+1), blocked_until=IF(attempts>=5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL)',[$key]);fail('Benutzername oder Passwort falsch.',401);}
  query('DELETE FROM login_attempts WHERE identity_hash=?',[$key]);session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));out(['user'=>user(),'csrf'=>$_SESSION['csrf']]);
 }
 $u=user();
 if($action==='logout' && $method==='POST'){$_SESSION=[];session_destroy();out(['ok'=>true]);}
 if($action==='menu' && $method==='GET') out(query('SELECT * FROM menu WHERE active=1 ORDER BY id')->fetchAll());
 if($action==='tables' && $method==='GET') out(query('SELECT * FROM dining_tables ORDER BY id')->fetchAll());
 if($action==='tickets' && $method==='GET') {
  $archive=($_GET['archive']??'0')==='1'; $params=[]; $where=$archive?'t.status="collected"':'t.status<>"collected"';
  if(!allows($u,'service')) { $stations=[]; if(allows($u,'kitchen')) $stations[]='kitchen';if(allows($u,'bar')) $stations[]='bar';if(!$stations) fail('Keine Berechtigung.',403);$where.=' AND t.station IN ('.implode(',',array_fill(0,count($stations),'?')).')';$params=$stations; }
  $tickets=query('SELECT t.*,o.table_id,o.note, o.user_id, d.name AS table_name,u.name AS server FROM tickets t JOIN orders o ON o.id=t.order_id JOIN dining_tables d ON d.id=o.table_id JOIN users u ON u.id=o.user_id WHERE '.$where.' ORDER BY t.created_at '.($archive?'DESC':'ASC').',t.id '.($archive?'DESC':'ASC').' LIMIT 200',$params)->fetchAll();
  foreach($tickets as &$ticket){$ticket['items']=query('SELECT * FROM items WHERE ticket_id=?',[$ticket['id']])->fetchAll();if($archive)$ticket['events']=query('SELECT e.action,e.created_at,u.name FROM events e JOIN users u ON u.id=e.user_id WHERE ticket_id=? ORDER BY e.id',[$ticket['id']])->fetchAll();}unset($ticket);out(['tickets'=>$tickets,'server_time'=>gmdate('Y-m-d H:i:s')]);
 }
 if($action==='order' && $method==='POST') {
  need($u,'service');$key=shortText($body['request_key']??'',80);if(strlen($key)<16)fail('Bestellkennung fehlt.');$table=positive($body['table_id']??null);$note=shortText($body['note']??'',500);$lines=$body['items']??null;if(!is_array($lines)||count($lines)<1||count($lines)>100)fail('Bestellung benötigt 1–100 Positionen.');
  if(!query('SELECT id FROM dining_tables WHERE id=?',[$table])->fetch())fail('Tisch nicht gefunden.');
  $existing=query('SELECT id,user_id FROM orders WHERE request_key=?',[$key])->fetch();if($existing){if((int)$existing['user_id']!==(int)$u['id'])fail('Bestellkennung bereits vergeben.',409);out(['id'=>(int)$existing['id'],'duplicate'=>true]);}
  $validated=[];foreach($lines as $line){if(!is_array($line))fail('Ungültige Position.');$id=positive($line['menu_id']??null);$qty=positive($line['quantity']??null,50);$n=shortText($line['note']??'',500);$m=query('SELECT * FROM menu WHERE id=? AND active=1',[$id])->fetch();if(!$m)fail('Gericht nicht verfügbar.');$validated[]=[$m,$qty,$n];}
  db()->beginTransaction();query('INSERT INTO orders(request_key,table_id,user_id,note) VALUES(?,?,?,?)',[$key,$table,$u['id'],$note]);$order=(int)db()->lastInsertId();$stations=[];
  foreach($validated as [$m,$qty,$n]){if(!isset($stations[$m['station']])){query('INSERT INTO tickets(order_id,station) VALUES(?,?)',[$order,$m['station']]);$stations[$m['station']]=(int)db()->lastInsertId();query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,?)',[$stations[$m['station']],$u['id'],'new']);}query('INSERT INTO items(ticket_id,menu_id,name,quantity,price,note) VALUES(?,?,?,?,?,?)',[$stations[$m['station']],$m['id'],$m['name'],$qty,$m['price'],$n]);}
  db()->commit();out(['id'=>$order],201);
 }
 if($action==='status' && $method==='POST') {
  $id=positive($body['id']??null);$target=$body['status']??'';if(!in_array($target,['working','ready','collected'],true))fail('Ungültiger Status.');
  db()->beginTransaction();$t=query('SELECT * FROM tickets WHERE id=? FOR UPDATE',[$id])->fetch();if(!$t){db()->rollBack();fail('Bon nicht gefunden.',404);}need($u,$target==='collected'?'service':$t['station']);
  $expected=['working'=>'new','ready'=>'working','collected'=>'ready'][$target];if($t['status']!==$expected){db()->rollBack();fail('Bon wurde bereits geändert. Bitte aktualisieren.',409);}
  $column=['working'=>'started_at','ready'=>'ready_at','collected'=>'collected_at'][$target];query("UPDATE tickets SET status=?, $column=NOW() WHERE id=?",[$target,$id]);query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,?)',[$id,$u['id'],$target]);db()->commit();out(['ok'=>true]);
 }
 if(str_starts_with($action,'admin_')) {
  need($u,'admin');
  if($action==='admin_users' && $method==='GET'){ $rows=query('SELECT id,name,username,roles,active FROM users ORDER BY id')->fetchAll();foreach($rows as &$row)$row['roles']=json_decode($row['roles'],true);out($rows); }
  if($action==='admin_user' && $method==='POST') {$name=shortText($body['name']??'',80);$username=shortText($body['username']??'',80);$password=shortText($body['password']??'',200);$roles=$body['roles']??[];if(!$name||!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$username)||strlen($password)<12||!is_array($roles)||!$roles||array_diff($roles,['admin','service','kitchen','bar']))fail('Name, Benutzername, Passwort (min. 12 Zeichen) und Rollen prüfen.');query('INSERT INTO users(name,username,password,roles) VALUES(?,?,?,?)',[$name,$username,password_hash($password,PASSWORD_DEFAULT),json_encode(array_values(array_unique($roles)))]);out(['ok'=>true],201);}
  if($action==='admin_menu' && $method==='POST'){$name=shortText($body['name']??'',120);$cat=shortText($body['category']??'',40);$station=$body['station']??'';$price=$body['price']??null;if(!$name||!$cat||!in_array($station,['kitchen','bar'],true)||!is_int($price)||$price<0||$price>100000)fail('Speisendaten prüfen.');$id=$body['id']??null;if($id){positive($id);query('UPDATE menu SET name=?,category=?,station=?,price=? WHERE id=?',[$name,$cat,$station,$price,$id]);}else query('INSERT INTO menu(name,category,station,price) VALUES(?,?,?,?)',[$name,$cat,$station,$price]);out(['ok'=>true]);}
  if($action==='admin_table' && $method==='POST'){$id=positive($body['id']??null,999);$name=shortText($body['name']??'',40);if(!$name)fail('Tischname fehlt.');query('INSERT INTO dining_tables(id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$id,$name]);out(['ok'=>true]);}
 }
 fail('Route nicht gefunden.',404);
} catch(PDOException $e){if(db()->inTransaction())db()->rollBack();error_log($e->getMessage());if($e->getCode()==='23000')fail('Datensatz bereits vorhanden. Bei einer Bestellung bitte mit derselben Bestellkennung wiederholen.',409);fail('Datenbank vorübergehend nicht erreichbar.',503);}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log($e->getMessage());fail('Interner Fehler.',500);}
