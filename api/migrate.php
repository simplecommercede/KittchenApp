<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/db.php';
$sql = [
'CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, username VARCHAR(80) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, active BOOLEAN NOT NULL DEFAULT 1)',
'CREATE TABLE IF NOT EXISTS menu (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, category VARCHAR(40) NOT NULL, station VARCHAR(20) NOT NULL, price INT NOT NULL, active BOOLEAN NOT NULL DEFAULT 1)',
'CREATE TABLE IF NOT EXISTS dining_tables (id INT PRIMARY KEY, name VARCHAR(40) NOT NULL)',
'CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, request_key VARCHAR(80) NOT NULL UNIQUE, table_id INT NOT NULL, user_id INT NOT NULL, note VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(table_id) REFERENCES dining_tables(id), FOREIGN KEY(user_id) REFERENCES users(id))',
'CREATE TABLE IF NOT EXISTS tickets (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, station VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT "new", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, started_at DATETIME NULL, ready_at DATETIME NULL, collected_at DATETIME NULL, FOREIGN KEY(order_id) REFERENCES orders(id), INDEX(status,station))',
'CREATE TABLE IF NOT EXISTS items (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, menu_id INT NOT NULL, name VARCHAR(120) NOT NULL, quantity INT NOT NULL, price INT NOT NULL, note VARCHAR(500) NOT NULL, FOREIGN KEY(ticket_id) REFERENCES tickets(id))',
'CREATE TABLE IF NOT EXISTS events (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, user_id INT NOT NULL, action VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(user_id) REFERENCES users(id))',
'CREATE TABLE IF NOT EXISTS login_attempts (identity_hash CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, blocked_until DATETIME NULL)'
];
foreach($sql as $s) db()->exec($s);
if (getenv('APP_ENV')==='local') {
 foreach ([['Admin','admin',['admin','service','kitchen','bar']],['Robin','robin',['kitchen']],['Mia','mia',['service']],['Tresen','tresen',['bar']]] as [$name,$username,$roles]) query('INSERT IGNORE INTO users(name,username,password,roles) VALUES(?,?,?,?)',[$name,$username,password_hash(getenv('DEMO_PASSWORD') ?: 'Stimbeck-Demo-2026!',PASSWORD_DEFAULT),json_encode($roles)]);
 foreach ([[1,'Hamburger mit Bacon','Speisen','kitchen',1590],[2,'Schnitzel Wiener Art','Speisen','kitchen',1690],[3,'Heimat Brotbrett','Vorspeisen','kitchen',750],[4,'Karotten-Ingwersuppe','Vorspeisen','kitchen',550],[5,'Heidjer Bauernknusper','Vorspeisen','kitchen',650],[6,'Apfelschorle 0,3 l','Getränke','bar',350],[7,'Pils 0,3 l','Getränke','bar',380],[8,'Schnitzel mit Pommes','Kids','kitchen',850]] as $m) query('INSERT IGNORE INTO menu(id,name,category,station,price) VALUES(?,?,?,?,?)',$m);
 for($i=1;$i<=12;$i++) query('INSERT IGNORE INTO dining_tables(id,name) VALUES(?,?)',[$i,'Tisch '.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);
}
echo "Schema ready. Demo seed only in APP_ENV=local.\n";
