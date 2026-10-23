<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\PaymentRepository;

use function Kitchen\Support\{out, fail, need, shortText, positive, visitTotal};

final class PaymentService
{
    public function __construct(private Database $database, private PaymentRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'payments' && $method === 'GET') {
            need($u, 'service');
            $paid = $this->repository->findTableVisits()->fetchAll();
            foreach ($paid as &$payment) {
                $payment['amount'] = visitTotal((int)$payment['visit_id']);
                $payment['visit_id'] = (int)$payment['visit_id'];
                $payment['table_id'] = (int)$payment['table_id'];
            }unset($payment);
            $events = $this->repository->findPaymentEvents()->fetchAll();
            foreach ($events as &$event) {
                $event['order_id'] = $event['order_id'] === null ? null : (int)$event['order_id'];
                $event['id'] = (int)$event['id'];
                $event['visit_id'] = (int)$event['visit_id'];
                $event['amount'] = (int)$event['amount'];
            }unset($event);
            out(['paid' => $paid,'events' => $events]);
        }
        if ($action === 'table_paid' && $method === 'POST') {
            need($u, 'service');
            $visitId = positive($body['visit_id'] ?? null);
            $expectedTotal = $body['expected_total'] ?? null;
            if (!is_int($expectedTotal) || $expectedTotal < 0) {
                fail('Zahlbetrag fehlt.');
            }
            $reference = $this->repository->findTableVisits2($visitId)->fetch();
            if (!$reference) {
                fail('Tischbesuch nicht gefunden.', 404);
            }
            $this->database->connection()->beginTransaction();
            $this->repository->findDiningTables($reference)->fetch();
            $visit = $this->repository->findTableVisits3($visitId)->fetch();
            if (!$visit || $visit['paid_at'] !== null) {
                $this->database->connection()->rollBack();
                fail('Zahlung wurde bereits bestätigt.', 409);
            }
            $pending = $this->repository->findTickets($visitId)->fetch();
            if ((int)$pending['amount'] > 0) {
                $this->database->connection()->rollBack();
                fail('Es sind noch Bons für diesen Tisch offen.', 409);
            }
            $amount = $this->repository->findOrders($visitId)->fetch();
            if ((int)$amount['orders_count'] === 0) {
                $this->database->connection()->rollBack();
                fail('Keine abzurechnenden Bestellungen vorhanden.', 409);
            }
            if ((int)$amount['total'] !== $expectedTotal) {
                $this->database->connection()->rollBack();
                fail('Zahlbetrag hat sich geändert. Bitte Rechnung aktualisieren.', 409);
            }
            $this->repository->updateOrders($u, $visitId);
            $this->repository->updateTableVisits($u, $visitId);
            $this->repository->insertPaymentEvents($visitId, $amount, $u);
            $this->database->connection()->commit();
            out(['ok' => true,'total' => (int)$amount['total']]);
        }
        if ($action === 'order_paid' && $method === 'POST') {
            // Separate payment at one table: one order is paid on its own; the visit closes with the last unpaid order.
            need($u, 'service');
            $orderId = positive($body['order_id'] ?? null);
            $expectedTotal = $body['expected_total'] ?? null;
            if (!is_int($expectedTotal) || $expectedTotal < 0) {
                fail('Zahlbetrag fehlt.');
            }
            $reference = $this->repository->findOrders2($orderId)->fetch();
            if (!$reference || !$reference['visit_id']) {
                fail('Bestellung nicht gefunden.', 404);
            }$visitId = (int)$reference['visit_id'];
            $this->database->connection()->beginTransaction();
            $this->repository->findDiningTables2($reference)->fetch();
            $visit = $this->repository->findTableVisits4($visitId)->fetch();
            $order = $this->repository->findOrders3($orderId)->fetch();
            if (!$visit || $visit['paid_at'] !== null || $order['paid_at'] !== null) {
                $this->database->connection()->rollBack();
                fail('Bestellung wurde bereits bezahlt.', 409);
            }
            if ($order['cancelled_at'] !== null) {
                $this->database->connection()->rollBack();
                fail('Bestellung ist storniert.', 409);
            }
            if ((int)$this->repository->findTickets2($orderId)->fetch()['amount'] > 0) {
                $this->database->connection()->rollBack();
                fail('Bons dieser Bestellung sind noch nicht abgeholt.', 409);
            }
            $total = (int)$this->repository->findTickets3($orderId)->fetch()['total'];
            if ($total !== $expectedTotal) {
                $this->database->connection()->rollBack();
                fail('Zahlbetrag hat sich geändert. Bitte Rechnung aktualisieren.', 409);
            }
            $this->repository->updateOrders2($u, $orderId);
            $this->repository->insertPaymentEvents2($visitId, $orderId, $total, $u);
            $open = (int)$this->repository->findOrders4($visitId)->fetch()['amount'];
            if ($open === 0) {
                $this->repository->updateTableVisits2($u, $visitId);
            }
            $this->database->connection()->commit();
            out(['ok' => true,'total' => $total,'visit_closed' => $open === 0]);
        }
        if ($action === 'payment_reverse' && $method === 'POST') {
            need($u, 'service');
            $visitId = positive($body['visit_id'] ?? null);
            $reason = shortText($body['reason'] ?? '', 300);
            if ($reason === '') {
                fail('Grund für die Rücknahme fehlt.');
            }
            $reference = $this->repository->findTableVisits5($visitId)->fetch();
            if (!$reference) {
                fail('Tischbesuch nicht gefunden.', 404);
            }
            $this->database->connection()->beginTransaction();
            $this->repository->findDiningTables3($reference)->fetch();
            $visit = $this->repository->findTableVisits6($visitId)->fetch();
            if (!$visit || $visit['paid_at'] === null) {
                $this->database->connection()->rollBack();
                fail('Zahlungsbestätigung wurde bereits zurückgenommen.', 409);
            }
            if ($this->repository->findTableVisits7($reference, $visitId)->fetch()) {
                $this->database->connection()->rollBack();
                fail('Für diesen Tisch gibt es bereits einen neueren Besuch. Bitte manuell abgleichen.', 409);
            }
            $amount = visitTotal($visitId);
            $this->repository->updateTableVisits3($visitId);
            $this->repository->updateOrders3($visitId);
            $this->repository->insertPaymentEvents3($visitId, $amount, $reason, $u);
            $this->database->connection()->commit();
            out(['ok' => true,'amount' => $amount]);
        }
    }
}
