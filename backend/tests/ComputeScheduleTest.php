<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../helpers/helpers.php';

final class ComputeScheduleTest extends TestCase
{
    public static function scheduleProvider(): array
    {
        return [
            'monthly_50k_12mo_12pct' => [
                'principal'              => 50000.0,
                'termMonths'             => 12,
                'frequency'              => 'monthly',
                'annualRate'             => 0.12,
                'expectedPeriods'        => 12,
                'expectedFirstPrincipal' => 4166.67,
                'expectedLastPrincipal'  => 4166.63,  // absorbs rounding residual
                'expectedTotalPrincipal' => 50000.00,
                'expectedFirstInterest'  => 500.00,
                'expectedFirstBalance'   => 45833.33,
            ],
            'bimonthly_30k_6mo_10pct' => [
                'principal'              => 30000.0,
                'termMonths'             => 6,
                'frequency'              => 'bimonthly',
                'annualRate'             => 0.10,
                'expectedPeriods'        => 12,
                'expectedFirstPrincipal' => 2500.00,
                'expectedLastPrincipal'  => 2500.00,  // no rounding residual for this case
                'expectedTotalPrincipal' => 30000.00,
                'expectedFirstInterest'  => 125.00,   // 30000 * (0.10/12) * 0.5
                'expectedFirstBalance'   => 27500.00,
            ],
            'weekly_20k_13mo_12pct' => [
                'principal'              => 20000.0,
                'termMonths'             => 13,
                'frequency'              => 'weekly',
                'annualRate'             => 0.12,
                'expectedPeriods'        => 52,
                'expectedFirstPrincipal' => 384.62,
                'expectedLastPrincipal'  => 384.38,
                'expectedTotalPrincipal' => 20000.00,
                'expectedFirstInterest'  => 50.00,    // 20000 * (0.12/12) * 0.25
                'expectedFirstBalance'   => 19615.38,
            ],
        ];
    }

    // MATH-01, MATH-02, MATH-03: period count, principal sum, last-period residual absorption
    #[DataProvider('scheduleProvider')]
    public function testTotalPrincipalSumsToOriginal(
        float $principal, int $termMonths, string $frequency, float $annualRate,
        int $expectedPeriods, float $expectedFirstPrincipal, float $expectedLastPrincipal,
        float $expectedTotalPrincipal, float $expectedFirstInterest, float $expectedFirstBalance
    ): void {
        $result   = computeSchedule($principal, $termMonths, $frequency, $annualRate);
        $schedule = $result['schedule'];

        // MATH-01: correct number of periods
        $this->assertCount($expectedPeriods, $schedule);
        $this->assertEquals($expectedPeriods, $result['n_periods']);

        // MATH-02: sum of all principals equals original principal within 0.01
        $actualSum = array_sum(array_column($schedule, 'principal'));
        $this->assertEqualsWithDelta(
            $expectedTotalPrincipal, $actualSum, 0.01,
            "Sum of principals ($actualSum) must equal original principal ($principal) within 0.01"
        );

        // MATH-03: first period and last period principal values (last absorbs residual)
        $this->assertEquals($expectedFirstPrincipal, $schedule[0]['principal'],
            "First period principal mismatch for $frequency/$principal/$termMonths");
        $this->assertEquals($expectedLastPrincipal, $schedule[$expectedPeriods - 1]['principal'],
            "Last period principal mismatch for $frequency/$principal/$termMonths — should absorb rounding residual");
    }

    // MATH-04: interest rate factors applied correctly per frequency
    #[DataProvider('scheduleProvider')]
    public function testInterestFormulaByFrequency(
        float $principal, int $termMonths, string $frequency, float $annualRate,
        int $expectedPeriods, float $expectedFirstPrincipal, float $expectedLastPrincipal,
        float $expectedTotalPrincipal, float $expectedFirstInterest, float $expectedFirstBalance
    ): void {
        $result   = computeSchedule($principal, $termMonths, $frequency, $annualRate);
        $schedule = $result['schedule'];

        $this->assertEqualsWithDelta(
            $expectedFirstInterest, $schedule[0]['interest'], 0.01,
            "Period-1 interest must match frequency rate factor for $frequency"
        );
        $this->assertEqualsWithDelta(
            $expectedFirstBalance, $schedule[0]['balance'], 0.01,
            "Period-1 balance must equal principal minus first period principal for $frequency"
        );
    }
}
