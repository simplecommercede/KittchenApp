<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\AdminRepository;

use function Kitchen\Support\{out, fail, need, shortText, passwordText, positive, staffRoles, drinkBase};

final class AdminService
{
    public function __construct(private Database $database, private AdminRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        need($u, 'admin');
        if ($action === 'admin_users' && $method === 'GET') {
            $rows = $this->repository->findUsers()->fetchAll();
            foreach ($rows as &$row) {
                $row['roles'] = json_decode($row['roles'], true);
            }out($rows);
        }
        if ($action === 'admin_user' && $method === 'POST') {
            $name = shortText($body['name'] ?? '', 80);
            $username = shortText($body['username'] ?? '', 80);
            $password = passwordText($body['password'] ?? '');
            $roles = staffRoles($body['roles'] ?? null);
            if (!$name || !preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username) || strlen($password) < 12) {
                fail('Name, Benutzername und Passwort (min. 12 Zeichen) prüfen.');
            }if ($this->repository->findUsers2($username)->fetch()) {
                fail('Benutzername ist bereits vergeben.', 409);
            }$this->repository->insertUsers($name, $username, $password, $roles);
            out(['ok' => true], 201);
        }
        if ($action === 'admin_user_update' && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $name = shortText($body['name'] ?? '', 80);
            $username = shortText($body['username'] ?? '', 80);
            $roles = staffRoles($body['roles'] ?? null);
            if (!$name || !preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
                fail('Name und Benutzername prüfen.');
            }
            $this->database->connection()->beginTransaction();
            $staff = $this->repository->findUsers3()->fetchAll();
            $target = null;
            $activeAdmins = 0;
            foreach ($staff as $person) {
                if ((int)$person['id'] === $id) {
                    $target = $person;
                }if ((int)$person['active'] === 1 && in_array('admin', json_decode($person['roles'], true), true)) {
                    $activeAdmins++;
                }if ((int)$person['id'] !== $id && $person['username'] === $username) {
                    $this->database->connection()->rollBack();
                    fail('Benutzername ist bereits vergeben.', 409);
                }
            }
            if (!$target) {
                $this->database->connection()->rollBack();
                fail('Mitarbeiter nicht gefunden.', 404);
            }
            $wasAdmin = (int)$target['active'] === 1 && in_array('admin', json_decode($target['roles'], true), true);
            $willBeAdmin = in_array('admin', $roles, true);
            if ($wasAdmin && !$willBeAdmin && ($activeAdmins <= 1 || $id === (int)$u['id'])) {
                $this->database->connection()->rollBack();
                fail('Der eigene oder letzte Admin-Zugang muss Admin bleiben.', 409);
            }
            $this->repository->updateUsers($name, $username, $roles, $id);
            $this->database->connection()->commit();
            out(['ok' => true]);
        }
        if ($action === 'admin_user_status' && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $active = $body['active'] ?? null;
            if (!is_bool($active)) {
                fail('Status prüfen.');
            }
            $this->database->connection()->beginTransaction();
            $staff = $this->repository->findUsers4()->fetchAll();
            $target = null;
            $activeAdmins = 0;
            foreach ($staff as $person) {
                if ((int)$person['id'] === $id) {
                    $target = $person;
                }if ((int)$person['active'] === 1 && in_array('admin', json_decode($person['roles'], true), true)) {
                    $activeAdmins++;
                }
            }
            if (!$target) {
                $this->database->connection()->rollBack();
                fail('Mitarbeiter nicht gefunden.', 404);
            }
            if (!$active && $id === (int)$u['id']) {
                $this->database->connection()->rollBack();
                fail('Eigenen Zugang nicht entfernen.', 409);
            }
            if (!$active && (int)$target['active'] === 1 && in_array('admin', json_decode($target['roles'], true), true) && $activeAdmins <= 1) {
                $this->database->connection()->rollBack();
                fail('Letzten Admin-Zugang nicht entfernen.', 409);
            }
            $this->repository->updateUsers2($active, $id);
            $this->database->connection()->commit();
            out(['ok' => true]);
        }
        if ($action === 'admin_menu' && $method === 'POST') {
            $name = shortText($body['name'] ?? '', 120);
            $cat = shortText($body['category'] ?? '', 40);
            $station = $body['station'] ?? '';
            $price = $body['price'] ?? null;
            if (!$name || !$cat || !in_array($station, ['kitchen','bar'], true) || !is_int($price) || $price < 0 || $price > 100000) {
                fail('Speisendaten prüfen.');
            }
            // Drinks carry their size in the name ("Cola 300 ml") so service can pick the size per product.
            $straw = false;
            if ($station === 'bar') {
                $ml = $body['volume_ml'] ?? null;
                if (!is_int($ml) || $ml < 1 || $ml > 5000) {
                    fail('Bei Getränken die Menge in ml angeben.');
                }if (preg_match('/\d+\s*(ml|cl|l)$/i', $name)) {
                    fail('Menge nur im Feld ml angeben, nicht im Namen.');
                }$name .= ' '.$ml.' ml';
                if (strlen($name) > 120) {
                    fail('Name ist zu lang.');
                }$straw = ($body['straw'] ?? false) === true;
            }
            // Optional add-ons (sides, dips, extras) are other kitchen products; omitted key keeps the current links.
            $addons = null;
            if (array_key_exists('addons', $body)) {
                if ($station !== 'kitchen' || !is_array($body['addons']) || count($body['addons']) > 30) {
                    fail('Zubuchbares nur für Speisen angeben.');
                }
                $addons = array_values(array_unique(array_map(fn ($addon) => positive($addon), $body['addons'])));
                if ($addons && count($this->repository->findKitchenProducts($addons)->fetchAll()) !== count($addons)) {
                    fail('Zubuchbares muss aus vorhandenen Speisen bestehen.');
                }
            }
            // Quick special wishes per dish ("Ohne Tomate, Veganes Patty"); omitted key keeps them.
            $wishes = null;
            if (array_key_exists('wishes', $body)) {
                if ($station !== 'kitchen') {
                    fail('Sonderwünsche nur für Speisen angeben.');
                }
                $parts = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]/', shortText($body['wishes'], 500)) ?: []), fn ($wish) => $wish !== '')));
                if (count($parts) > 15 || array_filter($parts, fn ($wish) => mb_strlen($wish) > 40)) {
                    fail('Höchstens 15 Sonderwünsche mit je bis zu 40 Zeichen.');
                }
                $wishes = implode(', ', $parts);
            }
            $id = $body['id'] ?? null;
            $this->database->connection()->beginTransaction();
            if ($id) {
                positive($id);
                if (!$this->repository->findMenu($id)->fetch()) {
                    fail('Produkt nicht gefunden.', 404);
                }$this->repository->updateMenu($name, $cat, $station, $price, $straw, $id);
            } else {
                $this->repository->insertMenu($name, $cat, $station, $price, $straw);
                $id = $this->repository->lastInsertId();
            }
            if ($addons !== null) {
                if (in_array((int)$id, $addons, true)) {
                    fail('Ein Gericht kann nicht zu sich selbst zugebucht werden.');
                }
                $this->repository->replaceAddons((int)$id, $addons);
            }
            if ($wishes !== null) {
                $this->repository->updateWishes((int)$id, $wishes);
            }
            $this->database->connection()->commit();
            out(['ok' => true,'id' => (int)$id]);
        }
        if ($action === 'admin_menu_status' && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $active = $body['active'] ?? null;
            if (!is_bool($active)) {
                fail('Status prüfen.');
            }if (!$this->repository->findMenu2($id)->fetch()) {
                fail('Produkt nicht gefunden.', 404);
            }$this->repository->updateMenu2($active, $id);
            out(['ok' => true]);
        }
        if ($action === 'admin_drink' && $method === 'POST') {
            // One drink with its portion sizes. Each size stays its own menu row ("Apfelschorle 300 ml") so orders keep per-size names and prices.
            $name = shortText($body['name'] ?? '', 100);
            $cat = shortText($body['category'] ?? '', 40);
            $straw = ($body['straw'] ?? false) === true;
            $sizes = $body['sizes'] ?? null;
            if (!$name || !$cat || preg_match('/\d+\s*(ml|cl|l)$/i', $name)) {
                fail('Getränkename ohne Menge und Kategorie angeben.');
            }
            if (!is_array($sizes) || !$sizes || count($sizes) > 6) {
                fail('Mindestens eine Größe mit ml und Preis angeben.');
            }
            $volumes = [];
            $ids = [];
            foreach ($sizes as $size) {
                if (!is_array($size)) {
                    fail('Größe prüfen.');
                }$ml = $size['volume_ml'] ?? null;
                $price = $size['price'] ?? null;
                $active = $size['active'] ?? true;
                if (!is_int($ml) || $ml < 1 || $ml > 5000 || !is_int($price) || $price < 0 || $price > 100000 || !is_bool($active)) {
                    fail('Jede Größe braucht ml und Preis.');
                }if (in_array($ml, $volumes, true)) {
                    fail('Jede Menge nur einmal angeben.');
                }$volumes[] = $ml;
                if (isset($size['id'])) {
                    $ids[] = positive($size['id']);
                }
            }
            if (count(array_unique($ids)) !== count($ids)) {
                fail('Größe doppelt angegeben.');
            }
            $this->database->connection()->beginTransaction();
            $byId = array_column($this->repository->findMenu3()->fetchAll(), 'name', 'id');
            $base = null;
            foreach ($ids as $id) {
                if (!isset($byId[$id])) {
                    fail('Größe nicht gefunden.', 404);
                }$b = drinkBase($byId[$id]);
                if ($base !== null && $b !== $base) {
                    fail('Größen gehören zu verschiedenen Getränken.');
                }$base = $b;
            }
            $group = $base === null ? [] : array_keys(array_filter($byId, fn ($n) => drinkBase($n) === $base));
            if (array_diff($group, $ids)) {
                fail('Alle vorhandenen Größen mitsenden; nicht mehr angebotene Größen deaktivieren.');
            }
            foreach ($byId as $id => $n) {
                if (!in_array($id, $group, true) && mb_strtolower(drinkBase($n)) === mb_strtolower($name)) {
                    fail('Ein Getränk mit diesem Namen gibt es bereits.', 409);
                }
            }
            $saved = [];
            foreach ($sizes as $size) {
                $values = [$name.' '.$size['volume_ml'].' ml',$cat,$size['price'],$straw ? 1 : 0,($size['active'] ?? true) ? 1 : 0];
                if (isset($size['id'])) {
                    $this->repository->updateMenu3($values, $size);
                    $saved[] = $size['id'];
                } else {
                    $this->repository->insertMenu2($values);
                    $saved[] = (int)$this->database->connection()->lastInsertId();
                }
            }
            $this->database->connection()->commit();
            out(['ok' => true,'ids' => $saved]);
        }
        if ($action === 'admin_table' && $method === 'POST') {
            $id = positive($body['id'] ?? null, 999);
            $name = shortText($body['name'] ?? '', 40);
            if (!$name) {
                fail('Tischname fehlt.');
            }$this->repository->insertDiningTables($id, $name);
            out(['ok' => true]);
        }
        if ($action === 'admin_table_status' && $method === 'POST') {
            $id = positive($body['id'] ?? null, 999);
            $active = $body['active'] ?? null;
            if (!is_bool($active)) {
                fail('Status prüfen.');
            }if (!$this->repository->findDiningTables($id)->fetch()) {
                fail('Tisch nicht gefunden.',404);
            }$this->repository->updateDiningTables($active, $id);
            out(['ok' => true]);
        }
    }
}
