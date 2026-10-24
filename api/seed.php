<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function seedDatabase(bool $hadAddons): void
{
$postgres=Kitchen\Infrastructure\Database::instance()->driver()==='pgsql';
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
   if(query('SELECT id FROM menu WHERE name=?',[$p[0]])->fetch())query('UPDATE menu SET wishes=? WHERE name=? AND wishes=\'\'',[$p[5],$p[0]]);
   elseif($p[1]==='Herbstkarte')query('INSERT INTO menu(name,category,station,price,straw,wishes) VALUES(?,?,?,?,?,?)',[$p[0],$p[1],$p[2],$p[3],$p[4],$p[5]]);
  }
 },
];
foreach($steps as $name=>$step){if(query('SELECT name FROM schema_migrations WHERE name=?',[$name])->fetch())continue;$step();query('INSERT INTO schema_migrations(name) VALUES(?)',[$name]);echo "Migration $name ausgeführt.\n";}
// First run with the add-on table: link dips and extras as on the printed menu (dips to fries dishes, extras to burgers and loaded fries).
if (!$hadAddons) {
 $kitchen=query('SELECT id,name,category FROM menu WHERE station=\'kitchen\'')->fetchAll();
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
 foreach($links as $link)query(($postgres ? 'INSERT INTO menu_addons(menu_id,addon_id) VALUES(?,?) ON CONFLICT DO NOTHING' : 'INSERT IGNORE INTO menu_addons(menu_id,addon_id) VALUES(?,?)'),$link);
}
if (getenv('APP_ENV')==='local') {
 foreach ([['Admin','admin',['admin','service','kitchen','bar']],['Robin','robin',['kitchen']],['Mia','mia',['service']],['Tresen','tresen',['bar']]] as [$name,$username,$roles]) query(($postgres ? 'INSERT INTO users(name,username,password,roles) VALUES(?,?,?,?) ON CONFLICT DO NOTHING' : 'INSERT IGNORE INTO users(name,username,password,roles) VALUES(?,?,?,?)'),[$name,$username,password_hash(getenv('DEMO_PASSWORD') ?: 'Stimbeck-Demo-2026!',PASSWORD_DEFAULT),json_encode($roles)]);
 for($i=1;$i<=12;$i++) query(($postgres ? 'INSERT INTO dining_tables(id,name) VALUES(?,?) ON CONFLICT DO NOTHING' : 'INSERT IGNORE INTO dining_tables(id,name) VALUES(?,?)'),[$i,'Tisch '.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);
}
echo "Schema ready. Demo users and tables only in APP_ENV=local.\n";

}
