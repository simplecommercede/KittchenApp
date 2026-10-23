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
function itemState(array $item): array {return ['id'=>(int)$item['id'],'menu_id'=>(int)$item['menu_id'],'name'=>$item['name'],'quantity'=>(int)$item['quantity'],'price'=>(int)$item['price'],'note'=>$item['note']];}
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
 if($action==='menu' && $method==='GET') out(query('SELECT * FROM menu ORDER BY id')->fetchAll());
 if($action==='tables' && $method==='GET') out(query('SELECT * FROM dining_tables ORDER BY id')->fetchAll());
 if($action==='table_status' && $method==='GET') {
  need($u,'service');
  $rows=query('SELECT v.id AS visit_id,v.table_id,d.name AS table_name,v.opened_at,COUNT(CASE WHEN t.status<>"cancelled" THEN t.id END) AS ticket_count,
   SUM(CASE WHEN t.status NOT IN ("collected","cancelled") THEN 1 ELSE 0 END) AS pending_count,
   SUM(CASE WHEN t.status="ready" THEN 1 ELSE 0 END) AS ready_count,
   SUM(CASE WHEN t.station="kitchen" AND t.status<>"cancelled" THEN 1 ELSE 0 END) AS food_count,
   SUM(CASE WHEN t.station="kitchen" AND t.status NOT IN ("collected","cancelled") THEN 1 ELSE 0 END) AS food_pending,
   MIN(CASE WHEN t.station="kitchen" AND t.status NOT IN ("collected","cancelled") THEN t.created_at END) AS food_waiting_since,
   MIN(CASE WHEN t.status NOT IN ("collected","cancelled") THEN t.created_at END) AS waiting_since,
   MAX(CASE WHEN t.station="kitchen" AND t.status="collected" THEN t.collected_at END) AS food_delivered_at,
   MAX(CASE WHEN t.status="collected" THEN t.collected_at END) AS last_delivered_at
   FROM table_visits v JOIN dining_tables d ON d.id=v.table_id
   LEFT JOIN orders o ON o.visit_id=v.id LEFT JOIN tickets t ON t.order_id=o.id
   WHERE v.paid_at IS NULL GROUP BY v.id,v.table_id,d.name,v.opened_at HAVING ticket_count>0 ORDER BY v.table_id')->fetchAll();
  foreach($rows as &$row){foreach(['visit_id','table_id','ticket_count','pending_count','ready_count','food_count','food_pending'] as $field)$row[$field]=(int)$row[$field];$row['payment_due']=$row['ticket_count']>0&&($row['food_count']>0?$row['food_pending']===0:$row['pending_count']===0);$row['unpaid_since']=$row['food_count']>0?$row['food_delivered_at']:$row['last_delivered_at'];}unset($row);
  out($rows);
 }
 if($action==='table_bill' && $method==='GET') {
  need($u,'service');$tableInput=$_GET['table_id']??null;if(!is_string($tableInput)||!ctype_digit($tableInput))fail('Ungültige Tischnummer.');$table=positive((int)$tableInput);
  $diningTable=query('SELECT id,name,active FROM dining_tables WHERE id=?',[$table])->fetch();if(!$diningTable)fail('Tisch nicht gefunden.',404);
  $visit=query('SELECT id FROM table_visits WHERE table_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1',[$table])->fetch();
  if(!$visit)out(['table'=>$diningTable,'visit_id'=>null,'orders'=>[],'total'=>0,'pending_count'=>0,'can_pay'=>false]);
  $orders=query('SELECT o.id,o.created_at,o.note,o.cancelled_at,o.cancellation_reason,u.name AS server,c.name AS cancelled_by FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN users c ON c.id=o.cancelled_by WHERE o.visit_id=? ORDER BY o.id',[$visit['id']])->fetchAll();
  $index=[];foreach($orders as $position=>&$order){$order['id']=(int)$order['id'];$order['items']=[];$order['total']=0;$index[$order['id']]=$position;}unset($order);
  foreach(query('SELECT o.id AS order_id,i.id,i.menu_id,i.name,i.quantity,i.price,i.note,i.cancelled_at,i.cancellation_reason,t.station,t.status FROM orders o JOIN tickets t ON t.order_id=o.id JOIN items i ON i.ticket_id=t.id WHERE o.visit_id=? ORDER BY o.id,i.id',[$visit['id']])->fetchAll() as $item){
   $orderId=(int)$item['order_id'];unset($item['order_id']);foreach(['id','menu_id','quantity','price'] as $field)$item[$field]=(int)$item[$field];$position=$index[$orderId];$orders[$position]['items'][]=$item;if($item['cancelled_at']===null)$orders[$position]['total']+=$item['quantity']*$item['price'];
  }
  foreach($orders as &$order)$order['changes']=[];unset($order);
  foreach(query('SELECT c.order_id,c.action,c.reason,c.before_state,c.after_state,c.created_at,u.name AS actor FROM order_changes c JOIN orders o ON o.id=c.order_id JOIN users u ON u.id=c.user_id WHERE o.visit_id=? ORDER BY c.id',[$visit['id']])->fetchAll() as $change){
   $position=$index[(int)$change['order_id']];unset($change['order_id']);$change['before_state']=$change['before_state']?json_decode($change['before_state'],true):null;$change['after_state']=$change['after_state']?json_decode($change['after_state'],true):null;$orders[$position]['changes'][]=$change;
  }
  $total=0;$activeCount=0;foreach($orders as $order)if($order['cancelled_at']===null){$total+=$order['total'];$activeCount++;}
  $open=query('SELECT COUNT(*) AS amount FROM tickets t JOIN orders o ON o.id=t.order_id WHERE o.visit_id=? AND o.cancelled_at IS NULL AND t.status NOT IN ("collected","cancelled")',[$visit['id']])->fetch();$pending=(int)$open['amount'];
  out(['table'=>$diningTable,'visit_id'=>(int)$visit['id'],'orders'=>$orders,'total'=>$total,'pending_count'=>$pending,'can_pay'=>$pending===0&&$activeCount>0]);
 }
 if($action==='tickets' && $method==='GET') {
  $archive=($_GET['archive']??'0')==='1'; $params=[]; $where=$archive?'t.status IN ("collected","cancelled")':'t.status NOT IN ("collected","cancelled")';
  if(!allows($u,'service')) { $stations=[]; if(allows($u,'kitchen')) $stations[]='kitchen';if(allows($u,'bar')) $stations[]='bar';if(!$stations) fail('Keine Berechtigung.',403);$where.=' AND t.station IN ('.implode(',',array_fill(0,count($stations),'?')).')';$params=$stations; }
  $tickets=query('SELECT t.*,o.table_id,o.note,o.user_id,o.cancellation_reason,d.name AS table_name,u.name AS server FROM tickets t JOIN orders o ON o.id=t.order_id JOIN dining_tables d ON d.id=o.table_id JOIN users u ON u.id=o.user_id WHERE '.$where.' ORDER BY t.created_at '.($archive?'DESC':'ASC').',t.id '.($archive?'DESC':'ASC').' LIMIT 200',$params)->fetchAll();
  foreach($tickets as &$ticket){$ticket['items']=query('SELECT * FROM items WHERE ticket_id=?',[$ticket['id']])->fetchAll();if($archive)$ticket['events']=query('SELECT e.action,e.created_at,u.name FROM events e JOIN users u ON u.id=e.user_id WHERE ticket_id=? ORDER BY e.id',[$ticket['id']])->fetchAll();}unset($ticket);out(['tickets'=>$tickets,'server_time'=>gmdate('Y-m-d H:i:s')]);
 }
 if($action==='order' && $method==='POST') {
  need($u,'service');$key=shortText($body['request_key']??'',80);if(strlen($key)<16)fail('Bestellkennung fehlt.');$table=positive($body['table_id']??null);$note=shortText($body['note']??'',500);$lines=$body['items']??null;if(!is_array($lines)||count($lines)<1||count($lines)>100)fail('Bestellung benötigt 1–100 Positionen.');
  $existing=query('SELECT id,user_id FROM orders WHERE request_key=?',[$key])->fetch();if($existing){if((int)$existing['user_id']!==(int)$u['id'])fail('Bestellkennung bereits vergeben.',409);out(['id'=>(int)$existing['id'],'duplicate'=>true]);}
  $validated=[];foreach($lines as $line){if(!is_array($line))fail('Ungültige Position.');$validated[]=[positive($line['menu_id']??null),positive($line['quantity']??null,50),shortText($line['note']??'',500)];}
  db()->beginTransaction();
  if(!query('SELECT id FROM dining_tables WHERE id=? AND active=1 FOR UPDATE',[$table])->fetch()){db()->rollBack();fail('Tisch nicht verfügbar.');}
  $visit=query('SELECT id FROM table_visits WHERE table_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE',[$table])->fetch();
  if($visit)$visitId=(int)$visit['id'];else{query('INSERT INTO table_visits(table_id) VALUES(?)',[$table]);$visitId=(int)db()->lastInsertId();}
  $available=[];foreach($validated as [$id,$qty,$n]){$m=query('SELECT * FROM menu WHERE id=? AND active=1 FOR UPDATE',[$id])->fetch();if(!$m){db()->rollBack();fail('Gericht nicht verfügbar.');}$available[]=[$m,$qty,$n];}
  query('INSERT INTO orders(request_key,table_id,visit_id,user_id,note) VALUES(?,?,?,?,?)',[$key,$table,$visitId,$u['id'],$note]);$order=(int)db()->lastInsertId();$stations=[];
  foreach($available as [$m,$qty,$n]){if(!isset($stations[$m['station']])){query('INSERT INTO tickets(order_id,station) VALUES(?,?)',[$order,$m['station']]);$stations[$m['station']]=(int)db()->lastInsertId();query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,?)',[$stations[$m['station']],$u['id'],'new']);}query('INSERT INTO items(ticket_id,menu_id,name,quantity,price,note) VALUES(?,?,?,?,?,?)',[$stations[$m['station']],$m['id'],$m['name'],$qty,$m['price'],$n]);}
  db()->commit();out(['id'=>$order],201);
 }
 if($action==='order_change' && $method==='POST') {
  need($u,'service');$orderId=positive($body['order_id']??null);$mode=$body['mode']??'';if(!in_array($mode,['add','replace','remove','note'],true))fail('Ungültige Korrektur.');
  $key=shortText($body['request_key']??'',80);if(strlen($key)<16)fail('Korrekturkennung fehlt.');$reason=shortText($body['reason']??'',300);if($reason==='')fail('Grund für die Korrektur fehlt.');
  $itemId=in_array($mode,['replace','remove'],true)?positive($body['item_id']??null):null;
  $menuId=in_array($mode,['add','replace'],true)?positive($body['menu_id']??null):null;
  $quantity=in_array($mode,['add','replace'],true)?positive($body['quantity']??null,50):null;
  $note=shortText($body['note']??'',500);
  $reference=query('SELECT table_id,visit_id FROM orders WHERE id=?',[$orderId])->fetch();if(!$reference)fail('Bestellung nicht gefunden.',404);
  db()->beginTransaction();$diningTable=query('SELECT active FROM dining_tables WHERE id=? FOR UPDATE',[$reference['table_id']])->fetch();
  $visit=$reference['visit_id']?query('SELECT paid_at FROM table_visits WHERE id=? FOR UPDATE',[$reference['visit_id']])->fetch():null;
  $order=query('SELECT note,cancelled_at FROM orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
  $existing=query('SELECT order_id,user_id FROM order_changes WHERE request_key=?',[$key])->fetch();
  if($existing){db()->rollBack();if((int)$existing['order_id']!==$orderId||(int)$existing['user_id']!==(int)$u['id'])fail('Korrekturkennung bereits vergeben.',409);out(['ok'=>true,'duplicate'=>true]);}
  if(!$visit||$visit['paid_at']!==null||!$order||$order['cancelled_at']!==null){db()->rollBack();fail('Bestellung ist bereits abgeschlossen oder storniert.',409);}
  if(in_array($mode,['add','replace'],true)&&(!$diningTable||(int)$diningTable['active']!==1)){db()->rollBack();fail('Tisch ist für neue Positionen deaktiviert.',409);}
  $before=null;$after=null;$newItemId=null;$orderCancelled=false;
  if($mode==='note'){
   if($order['note']===$note){db()->rollBack();fail('Bestellnotiz ist unverändert.',409);}
   $before=['note'=>$order['note']];$after=['note'=>$note];query('UPDATE orders SET note=? WHERE id=?',[$note,$orderId]);
   foreach(query('SELECT id FROM tickets WHERE order_id=? AND status NOT IN ("collected","cancelled")',[$orderId])->fetchAll() as $ticket)query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,"note_changed")',[$ticket['id'],$u['id']]);
  }else{
   if($itemId!==null){
    $old=query('SELECT i.*,t.id AS old_ticket_id FROM items i JOIN tickets t ON t.id=i.ticket_id WHERE i.id=? AND t.order_id=? FOR UPDATE',[$itemId,$orderId])->fetch();
    if(!$old||$old['cancelled_at']!==null){db()->rollBack();fail('Position wurde bereits geändert. Bitte Rechnung aktualisieren.',409);}
    $before=itemState($old);query('UPDATE items SET cancelled_at=UTC_TIMESTAMP(),cancelled_by=?,cancellation_reason=? WHERE id=?',[$u['id'],$reason,$itemId]);
    $remaining=query('SELECT COUNT(*) AS amount FROM items WHERE ticket_id=? AND cancelled_at IS NULL',[$old['old_ticket_id']])->fetch();
    if((int)$remaining['amount']===0){query('UPDATE tickets SET status="cancelled" WHERE id=?',[$old['old_ticket_id']]);query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,"cancelled")',[$old['old_ticket_id'],$u['id']]);}
    else query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,"item_removed")',[$old['old_ticket_id'],$u['id']]);
   }
   if($menuId!==null){
    $product=query('SELECT * FROM menu WHERE id=? AND active=1 FOR UPDATE',[$menuId])->fetch();if(!$product){db()->rollBack();fail('Produkt nicht verfügbar.',409);}
    query('INSERT INTO tickets(order_id,station,origin) VALUES(?,?,?)',[$orderId,$product['station'],$mode==='add'?'addition':'correction']);$ticketId=(int)db()->lastInsertId();
    query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,"new")',[$ticketId,$u['id']]);
    query('INSERT INTO items(ticket_id,menu_id,name,quantity,price,note) VALUES(?,?,?,?,?,?)',[$ticketId,$menuId,$product['name'],$quantity,$product['price'],$note]);$newItemId=(int)db()->lastInsertId();
    $after=['id'=>$newItemId,'menu_id'=>$menuId,'name'=>$product['name'],'quantity'=>$quantity,'price'=>(int)$product['price'],'note'=>$note];
   }
   if($mode==='remove'){
    $remaining=query('SELECT COUNT(*) AS amount FROM items i JOIN tickets t ON t.id=i.ticket_id WHERE t.order_id=? AND i.cancelled_at IS NULL',[$orderId])->fetch();
    if((int)$remaining['amount']===0){query('UPDATE orders SET cancelled_at=UTC_TIMESTAMP(),cancelled_by=?,cancellation_reason=? WHERE id=?',[$u['id'],$reason,$orderId]);$orderCancelled=true;}
   }
  }
  query('INSERT INTO order_changes(request_key,order_id,user_id,action,reason,before_state,after_state) VALUES(?,?,?,?,?,?,?)',[$key,$orderId,$u['id'],$mode,$reason,$before?json_encode($before,JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE):null]);
  db()->commit();out(['ok'=>true,'new_item_id'=>$newItemId,'order_cancelled'=>$orderCancelled]);
 }
 if($action==='order_cancel' && $method==='POST') {
  need($u,'service');$orderId=positive($body['order_id']??null);$reason=shortText($body['reason']??'',300);if($reason==='')fail('Stornogrund fehlt.');
  $reference=query('SELECT table_id,visit_id FROM orders WHERE id=?',[$orderId])->fetch();if(!$reference)fail('Bestellung nicht gefunden.',404);
  db()->beginTransaction();query('SELECT id FROM dining_tables WHERE id=? FOR UPDATE',[$reference['table_id']])->fetch();
  $visit=$reference['visit_id']?query('SELECT paid_at FROM table_visits WHERE id=? FOR UPDATE',[$reference['visit_id']])->fetch():null;
  $order=query('SELECT cancelled_at FROM orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
  if(!$visit||$visit['paid_at']!==null){db()->rollBack();fail('Bezahlte oder ältere Bestellungen können hier nicht storniert werden.',409);}
  if(!$order||$order['cancelled_at']!==null){db()->rollBack();fail('Bestellung wurde bereits storniert.',409);}
  $tickets=query('SELECT id FROM tickets WHERE order_id=? FOR UPDATE',[$orderId])->fetchAll();
  query('UPDATE orders SET cancelled_at=UTC_TIMESTAMP(),cancelled_by=?,cancellation_reason=? WHERE id=?',[$u['id'],$reason,$orderId]);
  foreach($tickets as $ticket){query('UPDATE items SET cancelled_at=UTC_TIMESTAMP(),cancelled_by=?,cancellation_reason=? WHERE ticket_id=? AND cancelled_at IS NULL',[$u['id'],$reason,$ticket['id']]);query('UPDATE tickets SET status="cancelled" WHERE id=?',[$ticket['id']]);query('INSERT INTO events(ticket_id,user_id,action) VALUES(?,?,"cancelled")',[$ticket['id'],$u['id']]);}
  query('INSERT INTO order_changes(request_key,order_id,user_id,action,reason,after_state) VALUES(?,?,?,?,?,?)',[bin2hex(random_bytes(20)),$orderId,$u['id'],'order_cancel',$reason,json_encode(['cancelled'=>true])]);
  db()->commit();out(['ok'=>true,'order_id'=>$orderId]);
 }
 if($action==='table_paid' && $method==='POST') {
  need($u,'service');$visitId=positive($body['visit_id']??null);$expectedTotal=$body['expected_total']??null;if(!is_int($expectedTotal)||$expectedTotal<0)fail('Zahlbetrag fehlt.');
  $reference=query('SELECT table_id FROM table_visits WHERE id=?',[$visitId])->fetch();if(!$reference)fail('Tischbesuch nicht gefunden.',404);
  db()->beginTransaction();query('SELECT id FROM dining_tables WHERE id=? FOR UPDATE',[$reference['table_id']])->fetch();
  $visit=query('SELECT id,paid_at FROM table_visits WHERE id=? FOR UPDATE',[$visitId])->fetch();
  if(!$visit||$visit['paid_at']!==null){db()->rollBack();fail('Zahlung wurde bereits bestätigt.',409);}
  $pending=query('SELECT COUNT(*) AS amount FROM tickets t JOIN orders o ON o.id=t.order_id WHERE o.visit_id=? AND t.status NOT IN ("collected","cancelled")',[$visitId])->fetch();
  if((int)$pending['amount']>0){db()->rollBack();fail('Es sind noch Bons für diesen Tisch offen.',409);}
  $amount=query('SELECT COUNT(DISTINCT o.id) AS orders_count,COALESCE(SUM(i.quantity*i.price),0) AS total FROM orders o JOIN tickets t ON t.order_id=o.id JOIN items i ON i.ticket_id=t.id WHERE o.visit_id=? AND o.cancelled_at IS NULL AND i.cancelled_at IS NULL',[$visitId])->fetch();
  if((int)$amount['orders_count']===0){db()->rollBack();fail('Keine abzurechnenden Bestellungen vorhanden.',409);}
  if((int)$amount['total']!==$expectedTotal){db()->rollBack();fail('Zahlbetrag hat sich geändert. Bitte Rechnung aktualisieren.',409);}
  query('UPDATE table_visits SET paid_at=UTC_TIMESTAMP(),paid_by=? WHERE id=?',[$u['id'],$visitId]);db()->commit();out(['ok'=>true,'total'=>(int)$amount['total']]);
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
  if($action==='admin_menu_status' && $method==='POST'){$id=positive($body['id']??null);$active=$body['active']??null;if(!is_bool($active))fail('Status prüfen.');if(!query('SELECT id FROM menu WHERE id=?',[$id])->fetch())fail('Produkt nicht gefunden.',404);query('UPDATE menu SET active=? WHERE id=?',[$active?1:0,$id]);out(['ok'=>true]);}
  if($action==='admin_table' && $method==='POST'){$id=positive($body['id']??null,999);$name=shortText($body['name']??'',40);if(!$name)fail('Tischname fehlt.');query('INSERT INTO dining_tables(id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$id,$name]);out(['ok'=>true]);}
  if($action==='admin_table_status' && $method==='POST'){$id=positive($body['id']??null,999);$active=$body['active']??null;if(!is_bool($active))fail('Status prüfen.');if(!query('SELECT id FROM dining_tables WHERE id=?',[$id])->fetch())fail('Tisch nicht gefunden.',404);query('UPDATE dining_tables SET active=? WHERE id=?',[$active?1:0,$id]);out(['ok'=>true]);}
 }
 fail('Route nicht gefunden.',404);
} catch(PDOException $e){if(db()->inTransaction())db()->rollBack();error_log($e->getMessage());if($e->getCode()==='23000')fail('Datensatz bereits vorhanden. Bei einer Bestellung bitte mit derselben Bestellkennung wiederholen.',409);fail('Datenbank vorübergehend nicht erreichbar.',503);}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log($e->getMessage());fail('Interner Fehler.',500);}
