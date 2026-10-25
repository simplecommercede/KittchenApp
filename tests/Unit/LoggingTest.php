<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Http\Kernel;
use Kitchen\Logging\EventId;
use Kitchen\Logging\Log;
use Kitchen\Logging\Redactor;
use PHPUnit\Framework\TestCase;

final class LoggingTest extends TestCase
{
    private string $file;
    private array $previous = [];
    protected function setUp(): void
    {
        foreach (['LOG_FILE','LOG_LEVEL'] as $key) {
            $this->previous[$key] = getenv($key);
        }
        $this->file = (string)tempnam(sys_get_temp_dir(), 'kitchen-log-');
        putenv('LOG_FILE='.$this->file);
        putenv('LOG_LEVEL');
        Log::begin();
    }
    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value === false ? $key : $key.'='.$value);
        }
        @unlink($this->file);
    }
    private function entries(): array
    {
        return array_map(fn ($line) => json_decode($line, true, 32, JSON_THROW_ON_ERROR), array_filter(explode("\n", (string)file_get_contents($this->file))));
    }
    public function testEveryRoutedActionHasItsOwnEvent(): void
    {
        $routes = ['GET session','POST login','POST logout','GET menu','GET tables','GET table_status','GET table_bill','GET tickets','POST status','POST station_alert_ack','POST order','POST order_change','POST order_cancel','GET payments','POST table_paid','POST order_paid','POST payment_reverse','GET service_calls','POST service_call','POST service_call_ack','POST service_call_cancel','GET admin_users','POST admin_user','POST admin_user_update','POST admin_user_status','POST admin_menu','POST admin_menu_status','POST admin_drink','POST admin_table','POST admin_table_status'];
        $events = array_map(fn ($route) => EventId::forRequest(explode(' ', $route)[1], explode(' ', $route)[0]), $routes);
        self::assertNotContains(EventId::RouteNotFound, $events);
        self::assertCount(count($routes), array_unique(array_map(fn ($event) => $event->value, $events)));
        self::assertSame(EventId::RouteNotFound, EventId::forRequest('order', 'GET'));
    }
    public function testMessagesKeepIdentifiersButMaskValues(): void
    {
        self::assertSame("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '***' for key 'users.username'", Redactor::message("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'mia.k' for key 'users.username'"));
        self::assertSame('duplicate key value violates unique constraint "users_username_key" DETAIL: Key (username)=(***) already exists.', Redactor::message('duplicate key value violates unique constraint "users_username_key" DETAIL: Key (username)=(mia.k) already exists.'));
        self::assertSame('FATAL: password authentication failed for user "***"', Redactor::message('FATAL: password authentication failed for user "kitchen"'));
        self::assertSame('Failing row contains (***).', Redactor::message('Failing row contains (7, Mia, Ohne Zwiebeln).'));
        self::assertSame('password=*** token: *** mail *** session ***', Redactor::message('password=Geheim123! token: abc mail mia@example.org session 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b'));
        self::assertSame("Data too long for column 'name' at row 1", Redactor::message("Data too long for column 'name' at row 1"));
    }
    public function testExceptionsCarryLocationButNoArguments(): void
    {
        $throw = function (string $password): never {
            throw new \RuntimeException('Anmeldung für "'.$password.'" fehlgeschlagen', 7, new \PDOException("Duplicate entry 'Geheim-Passwort-42' for key 'users.username'"));
        };
        try {
            $throw('Geheim-Passwort-42');
        } catch (\RuntimeException $e) {
            Log::write('error', EventId::OrderCreate, 'Interner Fehler.', ['status' => 500], $e);
        }
        $raw = (string)file_get_contents($this->file);
        self::assertStringNotContainsString('Geheim-Passwort-42', $raw);
        $entry = $this->entries()[0];
        self::assertSame(5000, $entry['event_id']);
        self::assertSame('OrderCreate', $entry['event']);
        self::assertSame(Log::requestId(), $entry['request_id']);
        self::assertSame(\RuntimeException::class, $entry['exception']['class']);
        self::assertSame(7, $entry['exception']['code']);
        self::assertMatchesRegularExpression('/^LoggingTest\.php:\d+$/', $entry['exception']['at']);
        self::assertStringContainsString('LoggingTest', $entry['exception']['trace'][0]);
        self::assertSame("Duplicate entry '***' for key 'users.username'", $entry['exception']['previous']['message']);
    }
    public function testLevelFilterAndInputWhitelist(): void
    {
        putenv('LOG_LEVEL=warning');
        Log::write('info', EventId::TicketList, 'ok');
        Log::write('warning', EventId::LoginFailed, 'Benutzername oder Passwort falsch.');
        self::assertSame([2002], array_column($this->entries(), 'event_id'));
        self::assertSame(['id' => 4,'status' => 'ready','ids' => [1, 2]], Kernel::safeFields(['id' => 4,'status' => 'ready','ids' => [1, 2],'password' => 'x','note' => 'Ohne Zwiebeln','reason' => 'Gast','username' => 'mia']));
        self::assertSame([], Kernel::safeFields(['status' => 'Bitte <script>','mode' => ['nested' => 1]]));
    }
    public function testIpAddressesAreShortened(): void
    {
        self::assertSame('203.0.113.0', Redactor::ip('203.0.113.77'));
        self::assertSame('2001:db8:85a3::', Redactor::ip('2001:db8:85a3:8d3:1319:8a2e:370:7348'));
        self::assertNull(Redactor::ip(''));
    }
}
