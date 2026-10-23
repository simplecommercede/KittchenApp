<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Repository\TableRepository;

use function Kitchen\Support\{out, fail, need, positive};

final class TableService
{
    public function __construct(private TableRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'table_status' && $method === 'GET') {
            need($u, 'service');
            $rows = $this->repository->findTableVisits()->fetchAll();
            foreach ($rows as &$row) {
                foreach (['visit_id','table_id','ticket_count','pending_count','ready_count','food_count','food_pending'] as $field) {
                    $row[$field] = (int)$row[$field];
                }$row['payment_due'] = $row['ticket_count'] > 0 && ($row['food_count'] > 0 ? $row['food_pending'] === 0 : $row['pending_count'] === 0);
                $row['unpaid_since'] = $row['food_count'] > 0 ? $row['food_delivered_at'] : $row['last_delivered_at'];
            }unset($row);
            out($rows);
        }
        if ($action === 'table_bill' && $method === 'GET') {
            need($u, 'service');
            $tableInput = $_GET['table_id'] ?? null;
            if (!is_string($tableInput) || !ctype_digit($tableInput)) {
                fail('Ungültige Tischnummer.');
            }$table = positive((int)$tableInput);
            $diningTable = $this->repository->findDiningTables($table)->fetch();
            if (!$diningTable) {
                fail('Tisch nicht gefunden.', 404);
            }
            $visit = $this->repository->findTableVisits2($table)->fetch();
            if (!$visit) {
                out(['table' => $diningTable,'visit_id' => null,'orders' => [],'total' => 0,'pending_count' => 0,'can_pay' => false]);
            }
            $orders = $this->repository->findOrders($visit)->fetchAll();
            $index = [];
            foreach ($orders as $position => &$order) {
                $order['id'] = (int)$order['id'];
                $order['items'] = [];
                $order['total'] = 0;
                $order['pending_count'] = 0;
                $index[$order['id']] = $position;
            }unset($order);
            foreach ($this->repository->findOrders2($visit)->fetchAll() as $item) {
                $orderId = (int)$item['order_id'];
                unset($item['order_id']);
                foreach (['id','menu_id','quantity','price'] as $field) {
                    $item[$field] = (int)$item[$field];
                }$position = $index[$orderId];
                $orders[$position]['items'][] = $item;
                if ($item['cancelled_at'] === null) {
                    $orders[$position]['total'] += $item['quantity'] * $item['price'];
                    if (!in_array($item['status'], ['collected','cancelled'], true)) {
                        $orders[$position]['pending_count']++;
                    }
                }
            }
            foreach ($orders as &$order) {
                $order['changes'] = [];
            }unset($order);
            foreach ($this->repository->findOrderChanges($visit)->fetchAll() as $change) {
                $position = $index[(int)$change['order_id']];
                unset($change['order_id']);
                $change['before_state'] = $change['before_state'] ? json_decode($change['before_state'], true) : null;
                $change['after_state'] = $change['after_state'] ? json_decode($change['after_state'], true) : null;
                $orders[$position]['changes'][] = $change;
            }
            // Paid orders stay visible but no longer count towards the open amount.
            $total = 0;
            $activeCount = 0;
            foreach ($orders as &$order) {
                $order['can_pay'] = $order['cancelled_at'] === null && $order['paid_at'] === null && $order['pending_count'] === 0;
                if ($order['cancelled_at'] === null && $order['paid_at'] === null) {
                    $total += $order['total'];
                    $activeCount++;
                }
            }unset($order);
            $open = $this->repository->findTickets($visit)->fetch();
            $pending = (int)$open['amount'];
            out(['table' => $diningTable,'visit_id' => (int)$visit['id'],'orders' => $orders,'total' => $total,'pending_count' => $pending,'can_pay' => $pending === 0 && $activeCount > 0]);
        }
    }
}
