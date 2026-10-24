<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\CatalogRepository;
use Kitchen\Repository\ServiceCallRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class RepositoryTest extends TestCase
{
    public function testCatalogUsesPreparedQueryOnInjectedConnection(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([])->willReturn(true);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->with('SELECT * FROM menu ORDER BY id')->willReturn($statement);
        $repository = new CatalogRepository(new Database($pdo));
        self::assertSame($statement, $repository->findMenu());
    }

    public function testServiceCallsFilterByStationWithPlaceholders(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with(['kitchen','bar'])->willReturn(true);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->with(self::stringContains('c.station IN (?,?)'))->willReturn($statement);
        $repository = new ServiceCallRepository(new Database($pdo));
        self::assertSame($statement, $repository->findVisible(['kitchen','bar']));
    }
}
