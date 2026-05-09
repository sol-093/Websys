<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Support\Cache\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/involve-cache-test-' . bin2hex(random_bytes(4));
    }

    public function testHitMissForgetAndRemember(): void
    {
        $cache = new FileCache($this->directory);

        self::assertSame('fallback', $cache->get('missing', 'fallback'));

        $cache->set('sample:key', 'value', 60);
        self::assertSame('value', $cache->get('sample:key'));

        $computed = $cache->remember('sample:remember', 60, static fn(): string => 'computed');
        self::assertSame('computed', $computed);
        self::assertSame('computed', $cache->remember('sample:remember', 60, static fn(): string => 'other'));

        $cache->forget('sample:key');
        self::assertNull($cache->get('sample:key'));
    }

    public function testTtlExpiry(): void
    {
        $cache = new FileCache($this->directory);
        $cache->set('short', 'value', 1);
        self::assertSame('value', $cache->get('short'));

        sleep(2);

        self::assertSame('expired', $cache->get('short', 'expired'));
    }
}
