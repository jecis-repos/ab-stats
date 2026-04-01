<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Sample size and statistical power calculator for A/B tests.
 *
 * Usage:
 *   $n = PowerCalculator::requiredSampleSize(
 *       baselineRate: 0.05,
 *       minimumDetectableEffect: 0.01,
 *   );
 *   // $n ≈ 3,800 per variant
 */
final class PowerCalculator
{
    /**
     * Calculate the required sample size per variant for an A/B test.
     *
     * Uses the standard two-proportion z-test formula:
     *   n = (Z_{alpha/2} + Z_{beta})^2 * (p1*(1-p1) + p2*(1-p2)) / (p1 - p2)^2
     *
     * @param float $baselineRate            Current conversion rate (e.g. 0.05 for 5%)
     * @param float $minimumDetectableEffect Absolute difference to detect (e.g. 0.01 for 1pp)
     * @param float $significance            Significance level (default 0.05)
     * @param float $power                   Statistical power (default 0.80)
     * @return int  Sample size per variant (not total)
     */
    public static function requiredSampleSize(
        float $baselineRate,
        float $minimumDetectableEffect,
        float $significance = 0.05,
        float $power = 0.80,
    ): int {
        $p1 = $baselineRate;
        $p2 = $baselineRate + $minimumDetectableEffect;

        $zAlpha = NormalDistribution::quantile(1.0 - $significance / 2.0);
        $zBeta = NormalDistribution::quantile($power);

        $numerator = ($zAlpha + $zBeta) ** 2 * ($p1 * (1 - $p1) + $p2 * (1 - $p2));
        $denominator = ($p1 - $p2) ** 2;

        if ($denominator <= 0.0) {
            return PHP_INT_MAX;
        }

        return (int) ceil($numerator / $denominator);
    }

    /**
     * Calculate the achievable power given a sample size.
     *
     * @param float $baselineRate       Current conversion rate
     * @param float $mde                Minimum detectable effect (absolute)
     * @param int   $sampleSizePerVariant  Sample size per variant
     * @param float $significance       Significance level (default 0.05)
     * @return float Statistical power (0-1)
     */
    public static function achievablePower(
        float $baselineRate,
        float $mde,
        int $sampleSizePerVariant,
        float $significance = 0.05,
    ): float {
        $p1 = $baselineRate;
        $p2 = $baselineRate + $mde;

        $zAlpha = NormalDistribution::quantile(1.0 - $significance / 2.0);

        $se = sqrt(($p1 * (1 - $p1) + $p2 * (1 - $p2)) / $sampleSizePerVariant);

        if ($se <= 0.0) {
            return 1.0;
        }

        $zBeta = (abs($p2 - $p1) / $se) - $zAlpha;

        return NormalDistribution::cdf($zBeta);
    }
}
