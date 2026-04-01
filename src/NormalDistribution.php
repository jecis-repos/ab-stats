<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Normal distribution CDF using the Abramowitz & Stegun polynomial approximation.
 * Maximum error: 7.5e-8.
 *
 * Reference: Handbook of Mathematical Functions, formula 26.2.17
 */
final class NormalDistribution
{
    private const P  = 0.2316419;
    private const B1 = 0.319381530;
    private const B2 = -0.356563782;
    private const B3 = 1.781477937;
    private const B4 = -1.821255978;
    private const B5 = 1.330274429;

    /**
     * Cumulative distribution function for the standard normal distribution.
     *
     * Returns P(Z <= z) where Z ~ N(0,1).
     */
    public static function cdf(float $z): float
    {
        if ($z < -8.0) {
            return 0.0;
        }

        if ($z > 8.0) {
            return 1.0;
        }

        $negative = $z < 0.0;
        $z = abs($z);

        $t = 1.0 / (1.0 + self::P * $z);
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        $t4 = $t3 * $t;
        $t5 = $t4 * $t;

        $pdf = (1.0 / sqrt(2.0 * M_PI)) * exp(-0.5 * $z * $z);

        $cdf = 1.0 - $pdf * (
            self::B1 * $t +
            self::B2 * $t2 +
            self::B3 * $t3 +
            self::B4 * $t4 +
            self::B5 * $t5
        );

        return $negative ? 1.0 - $cdf : $cdf;
    }
}
