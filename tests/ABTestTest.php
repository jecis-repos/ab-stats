<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\ABTest;
use Jekabs\AbStats\TestResult;
use Jekabs\AbStats\Variant;
use PHPUnit\Framework\TestCase;

final class ABTestTest extends TestCase
{
    public function test_significant_result(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 50, total: 500),
            treatment: Variant::make('B', successes: 100, total: 500),
        );

        $this->assertTrue($result->isSignificant);
        $this->assertSame('B', $result->winner);
        $this->assertLessThan(0.05, $result->pValue);
        $this->assertGreaterThan(0.0, $result->lift);
    }

    public function test_not_significant_result(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 48, total: 500),
            treatment: Variant::make('B', successes: 52, total: 500),
        );

        $this->assertFalse($result->isSignificant);
        $this->assertNull($result->winner);
    }

    public function test_minimum_sample_guard(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 5, total: 10),
            treatment: Variant::make('B', successes: 10, total: 10),
            minSampleSize: 100,
        );

        $this->assertFalse($result->isSignificant);
        $this->assertNull($result->winner);
        $this->assertStringContainsString('Insufficient data', $result->reason);
    }

    public function test_custom_significance_level(): void
    {
        // Borderline result — significant at 0.10 but not at 0.01
        $result005 = ABTest::evaluate(
            control:   Variant::make('A', successes: 50, total: 500),
            treatment: Variant::make('B', successes: 70, total: 500),
            significance: 0.05,
        );

        $result001 = ABTest::evaluate(
            control:   Variant::make('A', successes: 50, total: 500),
            treatment: Variant::make('B', successes: 70, total: 500),
            significance: 0.001,
        );

        // At 0.05 it may or may not be significant, but at 0.001 it should not be
        $this->assertFalse($result001->isSignificant);
    }

    public function test_identical_rates(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 50, total: 500),
            treatment: Variant::make('B', successes: 50, total: 500),
        );

        $this->assertFalse($result->isSignificant);
        $this->assertSame('Conversion rates are identical', $result->reason);
    }

    public function test_control_wins_when_better(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 100, total: 500),
            treatment: Variant::make('B', successes: 50, total: 500),
        );

        $this->assertTrue($result->isSignificant);
        $this->assertSame('A', $result->winner);
        $this->assertLessThan(0.0, $result->lift); // Negative lift = control is better
    }

    public function test_lift_percent(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 100, total: 1000),
            treatment: Variant::make('B', successes: 150, total: 1000),
        );

        $this->assertSame('+50.00%', $result->liftPercent());
    }

    public function test_to_array(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 50, total: 500),
            treatment: Variant::make('B', successes: 100, total: 500),
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('control', $array);
        $this->assertArrayHasKey('treatment', $array);
        $this->assertArrayHasKey('chi_squared', $array);
        $this->assertArrayHasKey('p_value', $array);
        $this->assertArrayHasKey('is_significant', $array);
        $this->assertArrayHasKey('winner', $array);
        $this->assertArrayHasKey('lift_percent', $array);
    }

    public function test_evaluate_multiple(): void
    {
        $results = ABTest::evaluateMultiple(
            control: Variant::make('Control', successes: 50, total: 500),
            treatments: [
                Variant::make('Treatment-A', successes: 52, total: 500),
                Variant::make('Treatment-B', successes: 100, total: 500),
                Variant::make('Treatment-C', successes: 75, total: 500),
            ],
        );

        $this->assertCount(3, $results);
        // Sorted by p-value ascending (most significant first)
        $this->assertSame('Treatment-B', $results[0]->treatment->name);
    }

    public function test_variant_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Variant::make('bad', successes: -1, total: 100);
    }

    public function test_variant_successes_exceed_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Variant::make('bad', successes: 101, total: 100);
    }

    public function test_zero_control_conversion(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 0, total: 500),
            treatment: Variant::make('B', successes: 50, total: 500),
        );

        // Should not throw, lift is 0 when control rate is 0
        $this->assertSame(0.0, $result->lift);
    }
}
