<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/db.php';
$sql = [
'CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, username VARCHAR(80) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, active BOOLEAN NOT NULL DEFAULT 1, last_seen DATETIME NULL, last_view VARCHAR(20) NULL)',
'CREATE TABLE IF NOT EXISTS menu (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, category VARCHAR(40) NOT NULL, station VARCHAR(20) NOT NULL, price INT NOT NULL, active BOOLEAN NOT NULL DEFAULT 1, straw BOOLEAN NOT NULL DEFAULT 0, wishes VARCHAR(500) NOT NULL DEFAULT "")',
'CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)',
'CREATE TABLE IF NOT EXISTS menu_addons (menu_id INT NOT NULL, addon_id INT NOT NULL, PRIMARY KEY(menu_id,addon_id), FOREIGN KEY(menu_id) REFERENCES menu(id), FOREIGN KEY(addon_id) REFERENCES menu(id))',
'CREATE TABLE IF NOT EXISTS dining_tables (id INT PRIMARY KEY, name VARCHAR(40) NOT NULL, active BOOLEAN NOT NULL DEFAULT 1)',
'CREATE TABLE IF NOT EXISTS table_visits (id INT AUTO_INCREMENT PRIMARY KEY, table_id INT NOT NULL, opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL, paid_by INT NULL, FOREIGN KEY(table_id) REFERENCES dining_tables(id), FOREIGN KEY(paid_by) REFERENCES users(id), INDEX(table_id,paid_at))',
'CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, request_key VARCHAR(80) NOT NULL UNIQUE, table_id INT NOT NULL, visit_id INT NULL, user_id INT NOT NULL, note VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, cancelled_at DATETIME NULL, cancelled_by INT NULL, cancellation_reason VARCHAR(300) NULL, paid_at DATETIME NULL, paid_by INT NULL, FOREIGN KEY(table_id) REFERENCES dining_tables(id), FOREIGN KEY(visit_id) REFERENCES table_visits(id), FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(cancelled_by) REFERENCES users(id))',
'CREATE TABLE IF NOT EXISTS tickets (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, station VARCHAR(20) NOT NULL, origin VARCHAR(20) NOT NULL DEFAULT "initial", status VARCHAR(20) NOT NULL DEFAULT "new", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, started_at DATETIME NULL, ready_at DATETIME NULL, collected_at DATETIME NULL, FOREIGN KEY(order_id) REFERENCES orders(id), INDEX(status,station))',
'CREATE TABLE IF NOT EXISTS items (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, menu_id INT NOT NULL, name VARCHAR(120) NOT NULL, quantity INT NOT NULL, price INT NOT NULL, note VARCHAR(500) NOT NULL, cancelled_at DATETIME NULL, cancelled_by INT NULL, cancellation_reason VARCHAR(300) NULL, FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(cancelled_by) REFERENCES users(id))',
'CREATE TABLE IF NOT EXISTS events (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, user_id INT NOT NULL, action VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(user_id) REFERENCES users(id))',
'CREATE TABLE IF NOT EXISTS order_changes (id INT AUTO_INCREMENT PRIMARY KEY, request_key VARCHAR(80) NOT NULL UNIQUE, order_id INT NOT NULL, user_id INT NOT NULL, action VARCHAR(20) NOT NULL, reason VARCHAR(300) NOT NULL, before_state JSON NULL, after_state JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(user_id) REFERENCES users(id), INDEX(order_id))',
'CREATE TABLE IF NOT EXISTS station_alerts (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, kind VARCHAR(30) NOT NULL, description VARCHAR(300) NOT NULL, reason VARCHAR(300) NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, acknowledged_by INT NULL, acknowledged_at DATETIME NULL, FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(acknowledged_by) REFERENCES users(id), INDEX(acknowledged_at,ticket_id))',
'CREATE TABLE IF NOT EXISTS service_calls (id INT AUTO_INCREMENT PRIMARY KEY, station VARCHAR(20) NOT NULL, note VARCHAR(200) NOT NULL DEFAULT "", created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, acknowledged_by INT NULL, acknowledged_at DATETIME NULL, cancelled_at DATETIME NULL, FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(acknowledged_by) REFERENCES users(id), INDEX(station,acknowledged_at,cancelled_at))',
'CREATE TABLE IF NOT EXISTS payment_events (id INT AUTO_INCREMENT PRIMARY KEY, visit_id INT NOT NULL, order_id INT NULL, action VARCHAR(20) NOT NULL, amount INT NOT NULL, reason VARCHAR(300) NOT NULL DEFAULT "", user_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(visit_id) REFERENCES table_visits(id), FOREIGN KEY(user_id) REFERENCES users(id), INDEX(visit_id,id))',
'CREATE TABLE IF NOT EXISTS login_attempts (identity_hash CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, blocked_until DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(updated_at))'
];
$hadAddons=(bool)query("SHOW TABLES LIKE 'menu_addons'")->fetch();
foreach($sql as $s) db()->exec($s);
if (!query("SHOW COLUMNS FROM dining_tables LIKE 'active'")->fetch()) db()->exec('ALTER TABLE dining_tables ADD COLUMN active BOOLEAN NOT NULL DEFAULT 1');
if (!query("SHOW COLUMNS FROM orders LIKE 'visit_id'")->fetch()) db()->exec('ALTER TABLE orders ADD COLUMN visit_id INT NULL, ADD CONSTRAINT fk_orders_visit FOREIGN KEY (visit_id) REFERENCES table_visits(id)');
if (!query("SHOW COLUMNS FROM orders LIKE 'cancelled_at'")->fetch()) db()->exec('ALTER TABLE orders ADD COLUMN cancelled_at DATETIME NULL, ADD COLUMN cancelled_by INT NULL, ADD COLUMN cancellation_reason VARCHAR(300) NULL, ADD CONSTRAINT fk_orders_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id)');
if (!query("SHOW COLUMNS FROM orders LIKE 'paid_at'")->fetch()) db()->exec('ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL, ADD COLUMN paid_by INT NULL');
if (!query("SHOW COLUMNS FROM payment_events LIKE 'order_id'")->fetch()) db()->exec('ALTER TABLE payment_events ADD COLUMN order_id INT NULL');
if (!query("SHOW COLUMNS FROM users LIKE 'last_view'")->fetch()) db()->exec('ALTER TABLE users ADD COLUMN last_seen DATETIME NULL, ADD COLUMN last_view VARCHAR(20) NULL');
if (!query("SHOW COLUMNS FROM login_attempts LIKE 'updated_at'")->fetch()) db()->exec('ALTER TABLE login_attempts ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD INDEX(updated_at)');
if (!query("SHOW COLUMNS FROM menu LIKE 'wishes'")->fetch()) db()->exec('ALTER TABLE menu ADD COLUMN wishes VARCHAR(500) NOT NULL DEFAULT ""');
if (!query("SHOW COLUMNS FROM menu LIKE 'straw'")->fetch()) {db()->exec('ALTER TABLE menu ADD COLUMN straw BOOLEAN NOT NULL DEFAULT 0');query('UPDATE menu SET straw=1 WHERE category IN ("Softgetränke","Säfte","Saftschorlen")');}
if (!query("SHOW COLUMNS FROM tickets LIKE 'origin'")->fetch()) db()->exec('ALTER TABLE tickets ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT "initial"');
if (!query("SHOW COLUMNS FROM items LIKE 'cancelled_at'")->fetch()) db()->exec('ALTER TABLE items ADD COLUMN cancelled_at DATETIME NULL, ADD COLUMN cancelled_by INT NULL, ADD COLUMN cancellation_reason VARCHAR(300) NULL, ADD CONSTRAINT fk_items_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id)');
query('UPDATE items i JOIN tickets t ON t.id=i.ticket_id JOIN orders o ON o.id=t.order_id SET i.cancelled_at=o.cancelled_at,i.cancelled_by=o.cancelled_by,i.cancellation_reason=o.cancellation_reason WHERE o.cancelled_at IS NOT NULL AND i.cancelled_at IS NULL');
// Keep pre-migration, unfinished tickets visible in the new table overview.
foreach (query('SELECT DISTINCT o.table_id FROM orders o JOIN tickets t ON t.order_id=o.id WHERE o.visit_id IS NULL AND t.status<>"collected"')->fetchAll() as $row) {
 $tableId=(int)$row['table_id'];db()->beginTransaction();
 query('SELECT id FROM dining_tables WHERE id=? FOR UPDATE',[$tableId])->fetch();
 $visit=query('SELECT id FROM table_visits WHERE table_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE',[$tableId])->fetch();
 if(!$visit){query('INSERT INTO table_visits(table_id) VALUES(?)',[$tableId]);$visitId=(int)db()->lastInsertId();}else $visitId=(int)$visit['id'];
 query('UPDATE orders SET visit_id=? WHERE table_id=? AND visit_id IS NULL AND id IN (SELECT order_id FROM tickets WHERE status<>"collected")',[$visitId,$tableId]);
 db()->commit();
}
// An empty menu is filled from the printed Speisekarte; MENU_SEED=test loads the fixed fixtures the integration tests expect (IDs 1-8).
if (!query('SELECT id FROM menu LIMIT 1')->fetch()) {
 $menu = getenv('MENU_SEED')==='test'
  ? [['Hamburger mit Bacon','Speisen','kitchen',1590],['Schnitzel Wiener Art','Speisen','kitchen',1690],['Heimat Brotbrett','Vorspeisen','kitchen',750],['Karotten-Ingwersuppe','Vorspeisen','kitchen',550],['Heidjer Bauernknusper','Vorspeisen','kitchen',650],['Apfelschorle 0,3 l','Getränke','bar',350],['Pils 0,3 l','Getränke','bar',380],['Schnitzel mit Pommes','Kids','kitchen',850]]
  : require __DIR__.'/speisekarte.php';
 foreach ($menu as $m) query('INSERT INTO menu(name,category,station,price,straw,wishes) VALUES(?,?,?,?,?,?)',[$m[0],$m[1],$m[2],$m[3],$m[4]??0,$m[5]??'']);
}
// One-time data steps for existing databases, recorded in schema_migrations. Each step is idempotent.
$steps=[
 '2026-10-24-sonderwuensche-herbstkarte'=>function(){
  if(getenv('MENU_SEED')==='test')return;
  foreach(require __DIR__.'/speisekarte.php' as $p){
   if(query('SELECT id FROM menu WHERE name=?',[$p[0]])->fetch())query('UPDATE menu SET wishes=? WHERE name=? AND wishes=""',[$p[5],$p[0]]);
   elseif($p[1]==='Herbstkarte')query('INSERT INTO menu(name,category,station,price,straw,wishes) VALUES(?,?,?,?,?,?)',[$p[0],$p[1],$p[2],$p[3],$p[4],$p[5]]);
  }
 },
];
foreach($steps as $name=>$step){if(query('SELECT name FROM schema_migrations WHERE name=?',[$name])->fetch())continue;$step();query('INSERT INTO schema_migrations(name) VALUES(?)',[$name]);echo "Migration $name ausgeführt.\n";}
// First run with the add-on table: link dips and extras as on the printed menu (dips to fries dishes, extras to burgers and loaded fries).
if (!$hadAddons) {
 $kitchen=query('SELECT id,name,category FROM menu WHERE station="kitchen"')->fetchAll();
 $ids=fn(callable $match)=>array_map(fn($m)=>(int)$m['id'],array_values(array_filter($kitchen,$match)));
 $dips=$ids(fn($m)=>str_starts_with($m['name'],'Dip '));
 $extras=fn(array $names)=>$ids(fn($m)=>in_array($m['name'],$names,true));
 $links=[];
 foreach($kitchen as $m){
  $addons=match(true){
   $m['category']==='Burger'=>[...$dips,...$extras(['Extra Bacon','Extra Jalapenos','Extra Patty'])],
   $m['category']==='Schlemmerpommes'=>[...$dips,...$extras(['Extra Bacon','Extra Jalapenos'])],
   in_array($m['name'],['Currywurst Pommes','Pommes klein','Pommes groß','Schnitzel mit Pommes (Kids)','Hähnchennuggets mit Pommes (Kids)'],true)=>$dips,
   default=>[]};
  foreach($addons as $addon)$links[]=[(int)$m['id'],$addon];
 }
 foreach($links as $link)query('INSERT IGNORE INTO menu_addons(menu_id,addon_id) VALUES(?,?)',$link);
}
if (getenv('APP_ENV')==='local') {
 foreach ([['Admin','admin',['admin','service','kitchen','bar']],['Robin','robin',['kitchen']],['Mia','mia',['service']],['Tresen','tresen',['bar']]] as [$name,$username,$roles]) query('INSERT IGNORE INTO users(name,username,password,roles) VALUES(?,?,?,?)',[$name,$username,password_hash(getenv('DEMO_PASSWORD') ?: 'Stimbeck-Demo-2026!',PASSWORD_DEFAULT),json_encode($roles)]);
 for($i=1;$i<=12;$i++) query('INSERT IGNORE INTO dining_tables(id,name) VALUES(?,?)',[$i,'Tisch '.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);
}
echo "Schema ready. Demo users and tables only in APP_ENV=local.\n";
