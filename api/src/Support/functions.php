<?php

declare(strict_types=1);

namespace Kitchen\Support;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\SupportRepository;
use Kitchen\Model\User;
use Kitchen\Model\OrderItem;
use Throwable;

function repository(): SupportRepository
{
    return new SupportRepository(Database::instance());
}
function out(mixed $data, int $code = 200): never
{
    throw new \Kitchen\Http\Response($data, $code);
}
function fail(string $message, int $code = 400): never
{
    out(['error' => $message], $code);
}
function user(): array
{
    if (!isset($_SESSION['uid'])) {
        fail('Bitte anmelden.', 401);
    } $u = repository()->findUsers($_SESSION)->fetch();
    if (!$u) {
        fail('Zugang gesperrt.', 401);
    } $u['roles'] = json_decode($u['roles'], true);
    return $u;
}
function allows(array $u, string $role): bool
{
    return User::fromArray($u)->allows($role);
}
function need(array $u, string $role): void
{
    if (!allows($u, $role)) {
        fail('Keine Berechtigung.', 403);
    }
}
// The bar counts as staffed while someone with the bar role had the Tresen view open in the last 30 seconds.
function barStaffed(): bool
{
    foreach (repository()->findUsers2()->fetchAll() as $row) {
        $roles = json_decode($row['roles'], true);
        if (in_array('bar', $roles, true) || in_array('admin', $roles, true)) {
            return true;
        }
    }return false;
}
// Service covers the bar (new → working → ready, cancellation notices) while nobody staffs it.
function coversBar(array $u): bool
{
    return !allows($u, 'bar') && allows($u, 'service') && !barStaffed();
}
function mayWorkStation(array $u, string $station): bool
{
    return allows($u, $station) || ($station === 'bar' && coversBar($u));
}
function shortText(mixed $v, int $max): string
{
    if (!is_string($v) || strlen($v) > $max) {
        fail('Ungültiger Text.');
    }return trim($v);
}
function passwordText(mixed $v): string
{
    if (!is_string($v) || strlen($v) > 200) {
        fail('Ungültiger Text.');
    }return $v;
}
function rollbackQuietly(): void
{
    try {
        $db = Database::instance()->current();
        if ($db?->inTransaction()) {
            $db->rollBack();
        }
    } catch (Throwable) {
    }
}
function positive(mixed $v, int $max = 999999): int
{
    if (!is_int($v) || $v < 1 || $v > $max) {
        fail('Ungültige Zahl.');
    }return $v;
}
function staffRoles(mixed $roles): array
{
    if (!is_array($roles) || !$roles || count($roles) > 4) {
        fail('Mindestens eine gültige Rolle wählen.');
    }foreach ($roles as $role) {
        if (!is_string($role) || !in_array($role, ['admin','service','kitchen','bar'], true)) {
            fail('Ungültige Rolle.');
        }
    }return array_values(array_unique($roles));
}
// Counts a failed login; $limit failures within $minutes block the key for $minutes.
function loginFailure(string $key, int $limit, int $minutes): void
{
    repository()->insertLoginAttempts($key, $minutes, $limit);
}
function drinkBase(string $name): string
{
    return preg_match('/^(.*?)\s+\d+\s*ml$/u', $name, $m) ? $m[1] : $name;
}
// Sum of the active (not removed) items of a visit, optionally only of unpaid orders.
function visitTotal(int $visitId, bool $unpaidOnly = false): int
{
    return (int)repository()->findOrders($unpaidOnly, $visitId)->fetch()['total'];
}
function itemState(array $item): array
{
    return OrderItem::fromArray($item)->toArray();
}
