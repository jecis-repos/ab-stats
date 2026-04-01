<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\NormalDistribution;
use Jekabs\AbStats\PowerCalculator;
use PHPUnit\Framework\TestCase;

final class PowerCalculatorTest extends TestCase
{
    public function test_required_sample_size_known_values(): void
    {
        // Baseline 10%, MDE 5pp absolute → standard formula gives ~500-800 per variant at 80% power
        $n = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.05,
        );

        $this->assertGreaterThanOrEqual(400, $n);
        $this->assertLessThanOrEqual(800, $n);

        // Baseline 5%, MDE 1pp absolute → ~7,000-9,000 per variant at 80% power
        $n2 = PowerCalculator::requiredSampleSize(
            baselineRate: 0.05,
            minimumDetectableEffect: 0.01,
        );

        $this->assertGreaterThanOrEqual(7000, $n2);
        $this->assertLessThanOrEqual(9000, $n2);
    }

    public function test_higher_power_requires_larger_samples(): void
    {
        $n80 = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.02,
            power: 0.80,
        );

        $n90 = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.02,
            power: 0.90,
        );

        $this->assertGreaterThan($n80, $n90);
    }

    public function test_smaller_mde_requires_larger_samples(): void
    {
        $nLarge = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.05,
        );

        $nSmall = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.01,
        );

        $this->assertGreaterThan($nLarge, $nSmall);
    }

    public function test_achievable_power_returns_valid_range(): void
    {
        $power = PowerCalculator::achievablePower(
            baselineRate: 0.10,
            mde: 0.02,
            sampleSizePerVariant: 2000,
        );

        $this->assertGreaterThanOrEqual(0.0, $power);
        $this->assertLessThanOrEqual(1.0, $power);
    }

    public function test_achievable_power_increases_with_sample_size(): void
    {
        $powerSmall = PowerCalculator::achievablePower(
            baselineRate: 0.10,
            mde: 0.02,
            sampleSizePerVariant: 500,
        );

        $powerLarge = PowerCalculator::achievablePower(
            baselineRate: 0.10,
            mde: 0.02,
            sampleSizePerVariant: 5000,
        );

        $this->assertGreaterThan($powerSmall, $powerLarge);
    }

    public function test_achievable_power_roundtrip_with_sample_size(): void
    {
        // Calculate required sample size for 80% power, then verify achievable power is ~80%
        $n = PowerCalculator::requiredSampleSize(
            baselineRate: 0.10,
            minimumDetectableEffect: 0.02,
            power: 0.80,
        );

        $power = PowerCalculator::achievablePower(
            baselineRate: 0.10,
            mde: 0.02,
            sampleSizePerVariant: $n,
        );

        $this->assertEqualsWithDelta(0.80, $power, 0.02);
    }

    public function test_quantile_at_median(): void
    {
        $this->assertEqualsWithDelta(0.0, NormalDistribution::quantile(0.5), 1e-6);
    }

    public function test_quantile_at_975(): void
    {
        $this->assertEqualsWithDelta(1.96, NormalDistribution::quantile(0.975), 0.01);
    }

    public function test_quantile_at_025(): void
    {
        $this->assertEqualsWithDelta(-1.96, NormalDistribution::quantile(0.025), 0.01);
    }

    public function test_quantile_cdf_roundtrip(): void
    {
        $probabilities = [0.01, 0.05, 0.10, 0.25, 0.5, 0.75, 0.90, 0.95, 0.99];

        foreach ($probabilities as $p) {
            $z = NormalDistribution::quantile($p);
            $roundtrip = NormalDistribution::cdf($z);

            $this->assertEqualsWithDelta(
                $p,
                $roundtrip,
                1e-5,
                sprintf('CDF(quantile(%.2f)) should roundtrip, got %.8f', $p, $roundtrip),
            );
        }
    }

    public function test_quantile_symmetry(): void
    {
        $this->assertEqualsWithDelta(
            -NormalDistribution::quantile(0.975),
            NormalDistribution::quantile(0.025),
            1e-6,
        );
    }
}
