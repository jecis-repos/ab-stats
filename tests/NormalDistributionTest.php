<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\NormalDistribution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NormalDistributionTest extends TestCase
{
    #[DataProvider('knownValues')]
    public function test_cdf_matches_known_values(float $z, float $expected, float $tolerance): void
    {
        $actual = NormalDistribution::cdf($z);
        $this->assertEqualsWithDelta($expected, $actual, $tolerance, "CDF({$z})");
    }

    public static function knownValues(): array
    {
        return [
            'z=0.0'     => [0.0, 0.5, 1e-6],
            'z=1.0'     => [1.0, 0.8413, 1e-4],
            'z=-1.0'    => [-1.0, 0.1587, 1e-4],
            'z=1.96'    => [1.96, 0.975, 1e-3],
            'z=-1.96'   => [-1.96, 0.025, 1e-3],
            'z=2.576'   => [2.576, 0.995, 1e-3],
            'z=3.0'     => [3.0, 0.99865, 1e-4],
            'z=-3.0'    => [-3.0, 0.00135, 1e-4],
            'z=0.5'     => [0.5, 0.6915, 1e-4],
            'z=-0.5'    => [-0.5, 0.3085, 1e-4],
        ];
    }

    public function test_extreme_negative_returns_zero(): void
    {
        $this->assertSame(0.0, NormalDistribution::cdf(-10.0));
    }

    public function test_extreme_positive_returns_one(): void
    {
        $this->assertSame(1.0, NormalDistribution::cdf(10.0));
    }

    public function test_symmetry(): void
    {
        for ($z = 0.1; $z <= 3.0; $z += 0.1) {
            $sum = NormalDistribution::cdf($z) + NormalDistribution::cdf(-$z);
            $this->assertEqualsWithDelta(1.0, $sum, 1e-6, "CDF({$z}) + CDF(-{$z}) should equal 1");
        }
    }

    public function test_monotonically_increasing(): void
    {
        $prev = 0.0;
        for ($z = -4.0; $z <= 4.0; $z += 0.1) {
            $current = NormalDistribution::cdf($z);
            $this->assertGreaterThanOrEqual($prev, $current, "CDF should be monotonically increasing at z={$z}");
            $prev = $current;
        }
    }

    public function test_maximum_error_within_bound(): void
    {
        // Spot-check against high-precision known values (from NIST)
        $precise = [
            1.0 => 0.84134474606854,
            2.0 => 0.97724986805182,
            3.0 => 0.99865010196837,
        ];

        foreach ($precise as $z => $expected) {
            $actual = NormalDistribution::cdf($z);
            $error = abs($expected - $actual);
            $this->assertLessThan(7.5e-8, $error, "Error for z={$z} should be < 7.5e-8, got {$error}");
        }
    }
}
