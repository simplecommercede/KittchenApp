<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\TicketRepository;

use function Kitchen\Support\{out, fail, allows, need, coversBar, mayWorkStation, positive};

final class TicketService
{
    public function __construct(private Database $database, private TicketRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'tickets' && $method === 'GET') {
            $view = $_GET['view'] ?? null;
            if (in_array($view, ['service','kitchen','bar','admin'], true)) {
                $this->repository->updateUsers($view, $u);
            }
            $cover = coversBar($u);
            $archive = ($_GET['archive'] ?? '0') === '1';
            $params = [];
            $where = $archive ? 't.status IN (\'collected\',\'cancelled\')' : 't.status NOT IN (\'collected\',\'cancelled\')';
            if (!allows($u, 'service')) {
                $stations = [];
                if (allows($u, 'kitchen')) {
                    $stations[] = 'kitchen';
                }if (allows($u, 'bar')) {
                    $stations[] = 'bar';
                }if (!$stations) {
                    fail('Keine Berechtigung.', 403);
                }$where .= ' AND t.station IN ('.implode(',', array_fill(0, count($stations), '?')).')';
                $params = $stations;
            }
            $tickets = $this->repository->findTickets($where, $archive, $params)->fetchAll();
            foreach ($tickets as &$ticket) {
                $ticket['items'] = $this->repository->findItems($ticket)->fetchAll();
                if ($archive) {
                    $ticket['events'] = $this->repository->findEvents($ticket)->fetchAll();
                }
            }unset($ticket);
            $alerts = [];
            if (!$archive) {
                $alertStations = [];
                if (allows($u, 'kitchen')) {
                    $alertStations[] = 'kitchen';
                }if (allows($u, 'bar') || $cover) {
                    $alertStations[] = 'bar';
                }
                if ($alertStations) {
                    $placeholders = implode(',', array_fill(0, count($alertStations), '?'));
                    $alerts = $this->repository->findStationAlerts($placeholders, $alertStations)->fetchAll();
                }
            }
            out(['tickets' => $tickets,'alerts' => $alerts,'bar_cover' => $cover,'server_time' => gmdate('Y-m-d H:i:s')]);
        }
        if ($action === 'station_alert_ack' && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $this->database->connection()->beginTransaction();
            $alert = $this->repository->findStationAlerts2($id)->fetch();
            if (!$alert) {
                $this->database->connection()->rollBack();
                fail('Stornohinweis nicht gefunden.', 404);
            }
            if (!mayWorkStation($u, $alert['station'])) {
                $this->database->connection()->rollBack();
                fail('Keine Berechtigung.', 403);
            }
            if ($alert['acknowledged_at'] !== null) {
                $this->database->connection()->rollBack();
                out(['ok' => true,'duplicate' => true]);
            }
            $this->repository->updateStationAlerts($u, $id);
            $this->repository->insertEvents($alert, $u);
            $this->database->connection()->commit();
            out(['ok' => true]);
        }
        if ($action === 'status' && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $target = $body['status'] ?? '';
            if (!in_array($target, ['working','ready','collected'], true)) {
                fail('Ungültiger Status.');
            }
            $this->database->connection()->beginTransaction();
            $t = $this->repository->findTickets2($id)->fetch();
            if (!$t) {
                $this->database->connection()->rollBack();
                fail('Bon nicht gefunden.', 404);
            }if ($target === 'collected') {
                need($u, 'service');
            } elseif (!mayWorkStation($u, $t['station'])) {
                $this->database->connection()->rollBack();
                fail('Keine Berechtigung.', 403);
            }
            $expected = ['working' => 'new','ready' => 'working','collected' => 'ready'][$target];
            if ($t['status'] !== $expected) {
                $this->database->connection()->rollBack();
                fail('Bon wurde bereits geändert. Bitte aktualisieren.', 409);
            }
            if ($target !== 'working' && $this->repository->findStationAlerts3($id)->fetch()) {
                $this->database->connection()->rollBack();
                fail('Offenen Stornohinweis zuerst an der Station bestätigen.', 409);
            }
            $column = ['working' => 'started_at','ready' => 'ready_at','collected' => 'collected_at'][$target];
            $this->repository->updateTickets($column, $target, $id);
            $this->repository->insertEvents2($id, $u, $target);
            $this->database->connection()->commit();
            out(['ok' => true]);
        }
    }
}
