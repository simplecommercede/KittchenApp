<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Infrastructure\Database;
use Kitchen\Repository\CatalogRepository;
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
}
