<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\OrderRepository;

use function Kitchen\Support\{out, fail, need, shortText, positive, itemState};

final class OrderService
{
    public function __construct(private Database $database, private OrderRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'order' && $method === 'POST') {
            need($u, 'service');
            $key = shortText($body['request_key'] ?? '', 80);
            if (strlen($key) < 16) {
                fail('Bestellkennung fehlt.');
            }$table = positive($body['table_id'] ?? null);
            $note = shortText($body['note'] ?? '', 500);
            $lines = $body['items'] ?? null;
            if (!is_array($lines) || count($lines) < 1 || count($lines) > 100) {
                fail('Bestellung benötigt 1–100 Positionen.');
            }
            $existing = $this->repository->findOrders($key)->fetch();
            if ($existing) {
                if ((int)$existing['user_id'] !== (int)$u['id']) {
                    fail('Bestellkennung bereits vergeben.', 409);
                }out(['id' => (int)$existing['id'],'duplicate' => true]);
            }
            $validated = [];
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    fail('Ungültige Position.');
                }$validated[] = [positive($line['menu_id'] ?? null),positive($line['quantity'] ?? null, 50),shortText($line['note'] ?? '', 500)];
            }
            $this->database->connection()->beginTransaction();
            if (!$this->repository->findDiningTables($table)->fetch()) {
                $this->database->connection()->rollBack();
                fail('Tisch nicht verfügbar.');
            }
            $visit = $this->repository->findTableVisits($table)->fetch();
            if ($visit) {
                $visitId = (int)$visit['id'];
            } else {
                $this->repository->insertTableVisits($table);
                $visitId = (int)$this->database->connection()->lastInsertId();
            }
            $available = [];
            foreach ($validated as [$id,$qty,$n]) {
                $m = $this->repository->findMenu($id)->fetch();
                if (!$m) {
                    $this->database->connection()->rollBack();
                    fail('Gericht nicht verfügbar.');
                }$available[] = [$m,$qty,$n];
            }
            $this->repository->insertOrders($key, $table, $visitId, $u, $note);
            $order = (int)$this->database->connection()->lastInsertId();
            $stations = [];
            foreach ($available as [$m,$qty,$n]) {
                if (!isset($stations[$m['station']])) {
                    $this->repository->insertTickets($order, $m);
                    $stations[$m['station']] = (int)$this->database->connection()->lastInsertId();
                    $this->repository->insertEvents($stations, $m, $u);
                }$this->repository->insertItems($stations, $m, $qty, $n);
            }
            $this->database->connection()->commit();
            out(['id' => $order], 201);
        }
        if ($action === 'order_change' && $method === 'POST') {
            need($u, 'service');
            $orderId = positive($body['order_id'] ?? null);
            $mode = $body['mode'] ?? '';
            if (!in_array($mode, ['add','replace','remove','note'], true)) {
                fail('Ungültige Korrektur.');
            }
            $key = shortText($body['request_key'] ?? '', 80);
            if (strlen($key) < 16) {
                fail('Korrekturkennung fehlt.');
            }$reason = shortText($body['reason'] ?? '', 300);
            if ($reason === '') {
                fail('Grund für die Korrektur fehlt.');
            }
            $itemId = in_array($mode, ['replace','remove'], true) ? positive($body['item_id'] ?? null) : null;
            $menuId = in_array($mode, ['add','replace'], true) ? positive($body['menu_id'] ?? null) : null;
            $quantity = in_array($mode, ['add','replace'], true) ? positive($body['quantity'] ?? null, 50) : null;
            $note = shortText($body['note'] ?? '', 500);
            $reference = $this->repository->findOrders2($orderId)->fetch();
            if (!$reference) {
                fail('Bestellung nicht gefunden.', 404);
            }
            $this->database->connection()->beginTransaction();
            $diningTable = $this->repository->findDiningTables2($reference)->fetch();
            $visit = $reference['visit_id'] ? $this->repository->findTableVisits2($reference)->fetch() : null;
            $order = $this->repository->findOrders3($orderId)->fetch();
            $existing = $this->repository->findOrderChanges($key)->fetch();
            if ($existing) {
                $this->database->connection()->rollBack();
                if ((int)$existing['order_id'] !== $orderId || (int)$existing['user_id'] !== (int)$u['id']) {
                    fail('Korrekturkennung bereits vergeben.', 409);
                }out(['ok' => true,'duplicate' => true]);
            }
            if (!$visit || $visit['paid_at'] !== null || !$order || $order['cancelled_at'] !== null || $order['paid_at'] !== null) {
                $this->database->connection()->rollBack();
                fail('Bestellung ist bereits bezahlt, abgeschlossen oder storniert.', 409);
            }
            if (in_array($mode, ['add','replace'], true) && (!$diningTable || (int)$diningTable['active'] !== 1)) {
                $this->database->connection()->rollBack();
                fail('Tisch ist für neue Positionen deaktiviert.', 409);
            }
            $before = null;
            $after = null;
            $newItemId = null;
            $orderCancelled = false;
            if ($mode === 'note') {
                if ($order['note'] === $note) {
                    $this->database->connection()->rollBack();
                    fail('Bestellnotiz ist unverändert.', 409);
                }
                $before = ['note' => $order['note']];
                $after = ['note' => $note];
                $this->repository->updateOrders($note, $orderId);
                foreach ($this->repository->findTickets($orderId)->fetchAll() as $ticket) {
                    $this->repository->insertEvents2($ticket, $u);
                }
            } else {
                if ($itemId !== null) {
                    $old = $this->repository->findItems($itemId, $orderId)->fetch();
                    if (!$old || $old['cancelled_at'] !== null) {
                        $this->database->connection()->rollBack();
                        fail('Position wurde bereits geändert. Bitte Rechnung aktualisieren.', 409);
                    }
                    $before = itemState($old);
                    $this->repository->updateItems($u, $reason, $itemId);
                    if (in_array($old['old_status'], ['working','ready'], true)) {
                        $this->repository->insertStationAlerts($old, $orderId, $reason, $u);
                    }
                    $remaining = $this->repository->findItems2($old)->fetch();
                    if ((int)$remaining['amount'] === 0) {
                        $this->repository->updateTickets($old);
                        $this->repository->insertEvents3($old, $u);
                    } else {
                        $this->repository->insertEvents4($old, $u);
                    }
                }
                if ($menuId !== null) {
                    $product = $this->repository->findMenu2($menuId)->fetch();
                    if (!$product) {
                        $this->database->connection()->rollBack();
                        fail('Produkt nicht verfügbar.', 409);
                    }
                    $this->repository->insertTickets2($orderId, $product, $mode);
                    $ticketId = (int)$this->database->connection()->lastInsertId();
                    $this->repository->insertEvents5($ticketId, $u);
                    $this->repository->insertItems2($ticketId, $menuId, $product, $quantity, $note);
                    $newItemId = (int)$this->database->connection()->lastInsertId();
                    $after = ['id' => $newItemId,'menu_id' => $menuId,'name' => $product['name'],'quantity' => $quantity,'price' => (int)$product['price'],'note' => $note];
                }
                if ($mode === 'remove') {
                    $remaining = $this->repository->findItems3($orderId)->fetch();
                    if ((int)$remaining['amount'] === 0) {
                        $this->repository->updateOrders2($u, $reason, $orderId);
                        $orderCancelled = true;
                    }
                }
            }
            $this->repository->insertOrderChanges($key, $orderId, $u, $mode, $reason, $before, $after);
            $this->database->connection()->commit();
            out(['ok' => true,'new_item_id' => $newItemId,'order_cancelled' => $orderCancelled]);
        }
        if ($action === 'order_cancel' && $method === 'POST') {
            need($u, 'service');
            $orderId = positive($body['order_id'] ?? null);
            $reason = shortText($body['reason'] ?? '', 300);
            if ($reason === '') {
                fail('Stornogrund fehlt.');
            }
            $reference = $this->repository->findOrders4($orderId)->fetch();
            if (!$reference) {
                fail('Bestellung nicht gefunden.', 404);
            }
            $this->database->connection()->beginTransaction();
            $this->repository->findDiningTables3($reference)->fetch();
            $visit = $reference['visit_id'] ? $this->repository->findTableVisits3($reference)->fetch() : null;
            $order = $this->repository->findOrders5($orderId)->fetch();
            if (!$visit || $visit['paid_at'] !== null || ($order && $order['paid_at'] !== null)) {
                $this->database->connection()->rollBack();
                fail('Bezahlte oder ältere Bestellungen können hier nicht storniert werden.', 409);
            }
            if (!$order || $order['cancelled_at'] !== null) {
                $this->database->connection()->rollBack();
                fail('Bestellung wurde bereits storniert.', 409);
            }
            $tickets = $this->repository->findTickets2($orderId)->fetchAll();
            $this->repository->updateOrders3($u, $reason, $orderId);
            foreach ($tickets as $ticket) {
                if (in_array($ticket['status'], ['working','ready'], true)) {
                    $this->repository->insertStationAlerts2($ticket, $orderId, $reason, $u);
                }$this->repository->updateItems2($u, $reason, $ticket);
                $this->repository->updateTickets2($ticket);
                $this->repository->insertEvents6($ticket, $u);
            }
            $this->repository->insertOrderChanges2($orderId, $u, $reason);
            $this->database->connection()->commit();
            out(['ok' => true,'order_id' => $orderId]);
        }
    }
}
