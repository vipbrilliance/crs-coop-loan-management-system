<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../helpers/helpers.php';

// MATH-05: generateDueDates() handles month-end dates correctly
final class GenerateDueDatesTest extends TestCase
{
    public static function monthEndProvider(): array
    {
        return [
            'jan31_3_monthly_non_leap' => [
                'firstDate' => '2026-01-31',
                'nPeriods'  => 3,
                'frequency' => 'monthly',
                'expected'  => ['2026-01-31', '2026-02-28', '2026-03-31'],
            ],
            'jan31_2_monthly_leap' => [
                'firstDate' => '2028-01-31',
                'nPeriods'  => 2,
                'frequency' => 'monthly',
                'expected'  => ['2028-01-31', '2028-02-29'],
            ],
            'dec31_2_monthly' => [
                'firstDate' => '2026-12-31',
                'nPeriods'  => 2,
                'frequency' => 'monthly',
                'expected'  => ['2026-12-31', '2027-01-31'],
            ],
            'jan31_12_monthly_full_year' => [
                'firstDate' => '2026-01-31',
                'nPeriods'  => 12,
                'frequency' => 'monthly',
                'expected'  => [
                    '2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30',
                    '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31',
                    '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
                ],
            ],
        ];
    }

    #[DataProvider('monthEndProvider')]
    public function testMonthEndDateHandling(
        string $firstDate, int $nPeriods, string $frequency, array $expected
    ): void {
        $result = generateDueDates($firstDate, $nPeriods, $frequency);
        $this->assertCount(count($expected), $result);
        foreach ($expected as $i => $date) {
            $this->assertEquals($date, $result[$i],
                "Period $i date mismatch for firstDate=$firstDate frequency=$frequency"
            );
        }
    }

    public function testWeeklyFrequencyNoOverflow(): void
    {
        // Weekly +7 days never overflows — should produce exactly 7-day intervals
        $result = generateDueDates('2026-01-31', 4, 'weekly');
        $this->assertEquals('2026-01-31', $result[0]);
        $this->assertEquals('2026-02-07', $result[1]);
        $this->assertEquals('2026-02-14', $result[2]);
        $this->assertEquals('2026-02-21', $result[3]);
    }

    public function testNormalDateNoClamp(): void
    {
        $dates = generateDueDates('2026-03-15', 3, 'monthly');
        $this->assertEquals('2026-03-15', $dates[0]);
        $this->assertEquals('2026-04-15', $dates[1]);
        $this->assertEquals('2026-05-15', $dates[2]);
    }

    public function testCorrectPeriodCount(): void
    {
        $dates = generateDueDates('2026-01-01', 12, 'monthly');
        $this->assertCount(12, $dates);
    }
}
