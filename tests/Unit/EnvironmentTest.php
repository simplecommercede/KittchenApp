<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Infrastructure\Environment;
use Kitchen\Infrastructure\Database;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private array $previous = [];
    protected function setUp(): void
    {
        foreach (['DB_DRIVER','DB_HOST','DB_PORT','DB_NAME','DB_SSLMODE','DB_SSLROOTCERT','KITCHEN_TEST_SECRET','KITCHEN_TEST_OVERRIDE'] as $key) {
            $this->previous[$key] = getenv($key);
            putenv($key);
        }
    }
    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value === false ? $key : $key.'='.$value);
        }
    }
    public function testPrivateConfigPreservesPasswordAndProcessOverrides(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kitchen-env-');
        try {
            file_put_contents($file, 'KITCHEN_TEST_SECRET = "a$bc;#=42!"'."\n".'KITCHEN_TEST_OVERRIDE = "file"');
            putenv('KITCHEN_TEST_OVERRIDE=process');
            Environment::load($file);
            self::assertSame('a$bc;#=42!', getenv('KITCHEN_TEST_SECRET'));
            self::assertSame('process', getenv('KITCHEN_TEST_OVERRIDE'));
        } finally {
            unlink($file);
        }
    }
    public function testPostgresDsnAndIntervals(): void
    {
        putenv('DB_DRIVER=pgsql');
        putenv('DB_HOST=db');
        putenv('DB_SSLMODE=verify-full');
        $db = new Database();
        self::assertSame('pgsql:host=db;port=5432;dbname=kitchen;sslmode=verify-full;connect_timeout=10', $db->dsn());
        self::assertSame("CURRENT_TIMESTAMP - INTERVAL '5 MINUTE'", $db->ago(5, 'MINUTE'));
    }
    public function testMysqlRemainsDefault(): void
    {
        self::assertSame('mysql:host=127.0.0.1;port=3306;dbname=kitchen;charset=utf8mb4', (new Database())->dsn());
    }
    public function testDsnRejectsInjectedOptions(): void
    {
        putenv('DB_HOST=db;dbname=other');
        $this->expectException(\RuntimeException::class);
        (new Database())->dsn();
    }
    public function testUnsupportedDriverFails(): void
    {
        putenv('DB_DRIVER=sqlite');
        $this->expectException(\RuntimeException::class);
        (new Database())->driver();
    }
}
