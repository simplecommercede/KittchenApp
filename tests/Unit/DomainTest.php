<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Model\User;
use Kitchen\Model\OrderItem;
use Kitchen\Http\Response;
use Kitchen\Infrastructure\Database;
use PHPUnit\Framework\TestCase;

use function Kitchen\Support\{positive,passwordText,staffRoles,shortText,drinkBase,need};

final class DomainTest extends TestCase
{
    public function testStationRolesDoNotEscalate(): void
    {
        $cook = new User(1, 'Robin', ['kitchen']);
        self::assertTrue($cook->allows('kitchen'));
        foreach (['admin','bar','service'] as $role) {
            self::assertFalse($cook->allows($role));
        }
        self::assertTrue((new User(2, 'Admin', ['admin']))->allows('bar'));
    }
    public function testItemSnapshotNormalizesDatabaseNumbers(): void
    {
        $item = OrderItem::fromArray(['id' => '2','menu_id' => '7','name' => 'Burger','quantity' => '3','price' => '1590','note' => 'Ohne Tomate']);
        self::assertSame(['id' => 2,'menu_id' => 7,'name' => 'Burger','quantity' => 3,'price' => 1590,'note' => 'Ohne Tomate'], $item->toArray());
    }
    public function testPasswordsPreserveWhitespace(): void
    {
        self::assertSame(' secret ', passwordText(' secret '));
    }
    public function testRoleValidationDeduplicates(): void
    {
        self::assertSame(['kitchen','bar'], staffRoles(['kitchen','bar','kitchen']));
    }
    public function testInvalidRolesFailWith400(): void
    {
        try {
            staffRoles(['root']);
            self::fail('Expected rejection');
        } catch (Response $response) {
            self::assertSame(400, $response->status);
        }
    }
    public function testPermissionFailureIs403(): void
    {
        try {
            need(['roles' => ['service']], 'admin');
            self::fail('Expected rejection');
        } catch (Response $response) {
            self::assertSame(403, $response->status);
        }
    }
    public function testQuantityRejectsStringsZeroAndOverflow(): void
    {
        foreach (['1',0,-1,51] as $value) {
            try {
                positive($value, 50);
                self::fail('Expected rejection');
            } catch (Response $response) {
                self::assertSame(400, $response->status);
            }
        }
        self::assertSame(50, positive(50, 50));
    }
    public function testTextLengthAndDrinkGrouping(): void
    {
        self::assertSame('Hallo', shortText(' Hallo ', 20));
        self::assertSame('Cola', drinkBase('Cola 300 ml'));
        self::assertSame('Cola Zero', drinkBase('Cola Zero'));
        $this->expectException(Response::class);
        shortText('12345', 4);
    }
    public function testCurrentConnectionDoesNotConnect(): void
    {
        self::assertNull((new Database())->current());
    }
}
