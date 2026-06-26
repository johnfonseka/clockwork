<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Sync\Timestamps;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimestampsTest extends TestCase
{
    public function test_null_and_blank_become_null(): void
    {
        $this->assertNull(Timestamps::normalise(null));
        $this->assertNull(Timestamps::normalise(''));
        $this->assertNull(Timestamps::normalise('   '));
    }

    public function test_unparseable_becomes_null(): void
    {
        $this->assertNull(Timestamps::normalise('not a date'));
    }

    #[DataProvider('timestamps')]
    public function test_normalises_to_utc(string $input, string $expected): void
    {
        $this->assertSame($expected, Timestamps::normalise($input));
    }

    /** @return array<string,array{string,string}> */
    public static function timestamps(): array
    {
        return [
            'naive (treated as UTC)' => ['2026-06-26 08:00:00', '2026-06-26 08:00:00'],
            'ISO with Z' => ['2026-06-26T08:00:00Z', '2026-06-26 08:00:00'],
            'offset converted to UTC' => ['2026-06-26T10:00:00+02:00', '2026-06-26 08:00:00'],
        ];
    }
}
