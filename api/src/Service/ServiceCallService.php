<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\ServiceCallRepository;

use function Kitchen\Support\{out, fail, allows, need, shortText, positive};

/** Kitchen or bar calls the service ("Bitte in die Küche"); service answers with "Ich komme". */
final class ServiceCallService
{
    public function __construct(private Database $database, private ServiceCallRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'service_calls' && $method === 'GET') {
            $stations = allows($u, 'service') ? ['kitchen','bar'] : array_values(array_filter(['kitchen','bar'], fn ($station) => allows($u, $station)));
            if (!$stations) {
                fail('Keine Berechtigung.', 403);
            }
            $calls = $this->repository->findVisible($stations)->fetchAll();
            foreach ($calls as &$call) {
                $call['id'] = (int)$call['id'];
            }
            unset($call);
            out($calls);
        }
        if ($action === 'service_call' && $method === 'POST') {
            $station = $body['station'] ?? '';
            if (!in_array($station, ['kitchen','bar'], true)) {
                fail('Ungültige Station.');
            }
            need($u, $station);
            $note = shortText($body['note'] ?? '', 120);
            $this->database->connection()->beginTransaction();
            $open = $this->repository->findOpenForStation($station)->fetch();
            if ($open) {
                // One open call per station; a second call only updates the reason.
                $this->repository->updateNote((int)$open['id'], $note);
                $this->database->connection()->commit();
                out(['id' => (int)$open['id'],'duplicate' => true]);
            }
            $id = $this->repository->insert($station, $note, $u);
            $this->database->connection()->commit();
            out(['id' => $id], 201);
        }
        if (($action === 'service_call_ack' || $action === 'service_call_cancel') && $method === 'POST') {
            $id = positive($body['id'] ?? null);
            $this->database->connection()->beginTransaction();
            $call = $this->repository->findForUpdate($id)->fetch();
            if (!$call) {
                $this->database->connection()->rollBack();
                fail('Ruf nicht gefunden.', 404);
            }
            if ($action === 'service_call_ack') {
                need($u, 'service');
            } elseif (!allows($u, $call['station'])) {
                $this->database->connection()->rollBack();
                fail('Keine Berechtigung.', 403);
            }
            if ($call['cancelled_at'] !== null || ($action === 'service_call_cancel' && $call['acknowledged_at'] !== null)) {
                $this->database->connection()->rollBack();
                fail('Ruf ist bereits erledigt.', 409);
            }
            if ($call['acknowledged_at'] !== null) {
                $this->database->connection()->rollBack();
                out(['ok' => true,'duplicate' => true]);
            }
            $action === 'service_call_ack' ? $this->repository->acknowledge($id, $u) : $this->repository->cancel($id);
            $this->database->connection()->commit();
            out(['ok' => true]);
        }
    }
}
