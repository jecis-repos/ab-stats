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
     * Quantile function (inverse CDF) for the standard normal distribution.
     *
     * Uses Peter Acklam's rational approximation, accurate to about 1.15e-9.
     *
     * @param float $p Probability in (0, 1)
     * @return float The z-score such that P(Z <= z) = p
     */
    public static function quantile(float $p): float
    {
        if ($p <= 0.0) {
            return -INF;
        }

        if ($p >= 1.0) {
            return INF;
        }

        // Coefficients for the rational approximation
        $a1 = -3.969683028665376e+01;
        $a2 = 2.209460984245205e+02;
        $a3 = -2.759285104469687e+02;
        $a4 = 1.383577518672690e+02;
        $a5 = -3.066479806614716e+01;
        $a6 = 2.506628277459239e+00;

        $b1 = -5.447609879822406e+01;
        $b2 = 1.615858368580409e+02;
        $b3 = -1.556989798598866e+02;
        $b4 = 6.680131188771972e+01;
        $b5 = -1.328068155288572e+01;

        $c1 = -7.784894002430293e-03;
        $c2 = -3.223964580411365e-01;
        $c3 = -2.400758277161838e+00;
        $c4 = -2.549732539343734e+00;
        $c5 = 4.374664141464968e+00;
        $c6 = 2.938163982698783e+00;

        $d1 = 7.784695709041462e-03;
        $d2 = 3.224671290700398e-01;
        $d3 = 2.445134137142996e+00;
        $d4 = 3.754408661907416e+00;

        $pLow = 0.02425;
        $pHigh = 1.0 - $pLow;

        if ($p < $pLow) {
            // Lower tail rational approximation
            $q = sqrt(-2.0 * log($p));

            return ((((($c1 * $q + $c2) * $q + $c3) * $q + $c4) * $q + $c5) * $q + $c6)
                / (((($d1 * $q + $d2) * $q + $d3) * $q + $d4) * $q + 1.0);
        }

        if ($p <= $pHigh) {
            // Central rational approximation
            $q = $p - 0.5;
            $r = $q * $q;

            return ((((($a1 * $r + $a2) * $r + $a3) * $r + $a4) * $r + $a5) * $r + $a6) * $q
                / ((((($b1 * $r + $b2) * $r + $b3) * $r + $b4) * $r + $b5) * $r + 1.0);
        }

        // Upper tail — use symmetry
        $q = sqrt(-2.0 * log(1.0 - $p));

        return -(((((($c1 * $q + $c2) * $q + $c3) * $q + $c4) * $q + $c5) * $q + $c6)
            / (((($d1 * $q + $d2) * $q + $d3) * $q + $d4) * $q + 1.0));
    }

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
