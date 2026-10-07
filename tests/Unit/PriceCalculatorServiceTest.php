<?php

namespace Tests\Unit;

use App\Models\Cabin;
use App\Services\PriceCalculatorService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PriceCalculatorServiceTest extends TestCase
{
    public function test_departure_day_is_not_charged(): void
    {
        $cabin = new Cabin([
            'price_per_night' => 10,
            'weekly_prices' => array_replace(array_fill_keys(Cabin::WEEKDAYS, 10), [
                'friday' => 20, 'saturday' => 20,
            ]),
        ]);
        $arrival = Carbon::parse('2026-10-08');
        $departure = Carbon::parse('2026-10-09');
        $calculator = new PriceCalculatorService;

        $oneNight = $calculator->calculate($cabin, $arrival, $departure);
        $this->assertEquals(10, $oneNight['total']);
        $this->assertSame(1, $oneNight['nights']);
        $this->assertSame('thursday', $oneNight['nightly_prices'][0]['day']);

        $mixed = $calculator->calculate($cabin, $arrival, Carbon::parse('2026-10-10'));
        $this->assertEquals(30, $mixed['total']);
        $this->assertNull($mixed['price_per_night']);
        $this->assertEquals(15, $mixed['average_price_per_night']);
        $this->assertSame(['2026-10-08', '2026-10-09'], array_column($mixed['nightly_prices'], 'date'));
        $this->assertSame('2026-10-08', $arrival->toDateString());
        $this->assertSame('2026-10-09', $departure->toDateString());
    }

    public function test_weekly_rates_repeat_across_weeks_and_zero_is_a_valid_rate(): void
    {
        $cabin = new Cabin([
            'price_per_night' => 99,
            'weekly_prices' => array_replace(array_fill_keys(Cabin::WEEKDAYS, 10), [
                'sunday' => 0, 'friday' => 20, 'saturday' => 20,
            ]),
        ]);
        $result = (new PriceCalculatorService)->calculate(
            $cabin, Carbon::parse('2026-10-05'), Carbon::parse('2026-10-19')
        );

        $this->assertSame(14, $result['nights']);
        $this->assertEquals(160, $result['total']);
        $this->assertEquals(0, $result['nightly_prices'][6]['price']);
    }

    public function test_fallback_rate_and_decimal_totals(): void
    {
        $cabin = new Cabin(['price_per_night' => 0.10]);
        $result = (new PriceCalculatorService)->calculate(
            $cabin, Carbon::parse('2026-12-31'), Carbon::parse('2027-01-03')
        );

        $this->assertSame(3, $result['nights']);
        $this->assertSame(0.3, $result['total']);
        $this->assertSame(0.1, $result['price_per_night']);
    }

    public function test_same_calendar_day_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new PriceCalculatorService)->calculate(
            new Cabin(['price_per_night' => 10]),
            Carbon::parse('2026-10-08 10:00'),
            Carbon::parse('2026-10-08 15:00')
        );
    }
}
