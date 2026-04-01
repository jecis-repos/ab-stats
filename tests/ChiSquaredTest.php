<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\ChiSquared;
use PHPUnit\Framework\TestCase;

final class ChiSquaredTest extends TestCase
{
    public function test_statistic_with_known_values(): void
    {
        // Classic example: 200 vs 200, 90 vs 110 successes
        $chi2 = ChiSquared::statistic(90, 200, 110, 200);

        // With Yates' correction for this 2x2 table
        $this->assertEqualsWithDelta(3.61, $chi2, 0.1);
    }

    public function test_statistic_identical_rates(): void
    {
        $chi2 = ChiSquared::statistic(50, 100, 50, 100);
        $this->assertEqualsWithDelta(0.0, $chi2, 0.1);
    }

    public function test_statistic_zero_total(): void
    {
        $this->assertSame(0.0, ChiSquared::statistic(0, 0, 0, 0));
    }

    public function test_statistic_all_success(): void
    {
        // All convert — no variation
        $chi2 = ChiSquared::statistic(100, 100, 100, 100);
        $this->assertSame(0.0, $chi2);
    }

    public function test_p_value_significant(): void
    {
        // Large difference should produce small p-value
        $chi2 = ChiSquared::statistic(50, 500, 100, 500);
        $pValue = ChiSquared::pValue($chi2);

        $this->assertLessThan(0.05, $pValue);
    }

    public function test_p_value_not_significant(): void
    {
        // Small difference should produce large p-value
        $chi2 = ChiSquared::statistic(51, 500, 49, 500);
        $pValue = ChiSquared::pValue($chi2);

        $this->assertGreaterThan(0.05, $pValue);
    }

    public function test_p_value_zero_chi_squared(): void
    {
        $this->assertSame(1.0, ChiSquared::pValue(0.0));
    }

    public function test_p_value_at_critical_boundary(): void
    {
        // Chi-squared critical value for df=1 at α=0.05 is 3.841
        $pValue = ChiSquared::pValue(3.841);
        $this->assertEqualsWithDelta(0.05, $pValue, 0.005);
    }

    public function test_yates_correction_reduces_statistic(): void
    {
        // Without Yates' correction the statistic would be higher
        // With Yates', small samples get a conservative adjustment
        $chi2 = ChiSquared::statistic(10, 50, 15, 50);

        // Should be non-zero but conservative
        $this->assertGreaterThan(0.0, $chi2);
        $this->assertLessThan(5.0, $chi2);
    }
}
