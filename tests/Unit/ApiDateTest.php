<?php

namespace Tests\Unit;

use App\Support\ApiDate;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ApiDateTest extends TestCase
{
    public function test_it_formats_date_values(): void
    {
        $date = CarbonImmutable::parse('2026-01-15 13:45:00', 'UTC');

        $this->assertSame('2026-01-15', ApiDate::date($date));
    }

    public function test_it_formats_datetime_values_as_iso_utc_string(): void
    {
        $date = CarbonImmutable::parse('2026-01-15 13:45:00', 'UTC');

        $this->assertSame(
            '2026-01-15T13:45:00.000000Z',
            ApiDate::datetime($date)
        );
    }

    public function test_it_returns_null_for_empty_values(): void
    {
        $this->assertNull(ApiDate::date(null));
        $this->assertNull(ApiDate::date(''));
        $this->assertNull(ApiDate::datetime(null));
        $this->assertNull(ApiDate::datetime(''));
    }

    public function test_it_keeps_existing_string_values(): void
    {
        $this->assertSame('2026-01-15', ApiDate::date('2026-01-15'));
        $this->assertSame(
            '2026-01-15T13:45:00+00:00',
            ApiDate::datetime('2026-01-15T13:45:00+00:00')
        );
    }
}
