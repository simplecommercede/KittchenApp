<?php

declare(strict_types=1);

namespace Kitchen\Tests;

use Kitchen\Logging\DailyLog;
use PHPUnit\Framework\TestCase;

final class DailyLogTest extends TestCase
{
    public function testCreatesPrivateDirectoryAndAppendsJsonLines(): void
    {
        $dir = sys_get_temp_dir().'/kitchen-daily-'.bin2hex(random_bytes(8));
        try {
            DailyLog::append($dir, '{"first":1}');
            DailyLog::append($dir, '{"second":2}');
            $file = $dir.'/debug_'.DailyLog::today()->format('Y-m-d').'.log';
            self::assertSame("{\"first\":1}\n{\"second\":2}\n", file_get_contents($file));
            self::assertSame(0700, fileperms($dir) & 0777);
            self::assertSame(0600, fileperms($file) & 0777);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
    public function testRetentionUsesCalendarDateNotModificationTimeAndLeavesOtherFiles(): void
    {
        $dir = sys_get_temp_dir().'/kitchen-retention-'.bin2hex(random_bytes(8));
        DailyLog::prepare($dir);
        $names = ['debug_2026-11-18.log','debug_2026-11-19.log','debug_2026-11-25.log','debug_2026-11-26.log','debug_2026-99-99.log','other.log'];
        try {
            foreach ($names as $name) {
                file_put_contents($dir.'/'.$name, 'example');
            }
            symlink($dir.'/other.log', $dir.'/debug_2020-01-01.log');
            self::assertSame(1, DailyLog::cleanup($dir, new \DateTimeImmutable('2026-11-25', new \DateTimeZone('Europe/Berlin'))));
            self::assertFileDoesNotExist($dir.'/debug_2026-11-18.log');
            foreach (array_slice($names, 1) as $name) {
                self::assertFileExists($dir.'/'.$name);
            }
            self::assertTrue(is_link($dir.'/debug_2020-01-01.log'));
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
