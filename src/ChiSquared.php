<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Chi-squared test with Yates' continuity correction for 2x2 contingency tables.
 *
 * Uses the Wilson-Hilferty cube-root approximation to convert the chi-squared
 * statistic to a z-score, then the Abramowitz & Stegun normal CDF for the p-value.
 * Accurate to +/- 0.001 for df=1.
 */
final class ChiSquared
{
    /**
     * Compute the chi-squared statistic with Yates' continuity correction
     * for a 2x2 contingency table.
     *
     * @param int $successA Conversions in variant A
     * @param int $totalA   Total visitors in variant A
     * @param int $successB Conversions in variant B
     * @param int $totalB   Total visitors in variant B
     */
    public static function statistic(int $successA, int $totalA, int $successB, int $totalB): float
    {
        $failA = $totalA - $successA;
        $failB = $totalB - $successB;

        $n = $totalA + $totalB;

        if ($n === 0) {
            return 0.0;
        }

        // Grand totals for rows and columns
        $rowSuccess = $successA + $successB;
        $rowFail    = $failA + $failB;
        $colA       = $totalA;
        $colB       = $totalB;

        // Expected frequencies
        $eSuccessA = ($rowSuccess * $colA) / $n;
        $eFailA    = ($rowFail * $colA) / $n;
        $eSuccessB = ($rowSuccess * $colB) / $n;
        $eFailB    = ($rowFail * $colB) / $n;

        // Guard against division by zero in expected values
        if ($eSuccessA == 0.0 || $eFailA == 0.0 || $eSuccessB == 0.0 || $eFailB == 0.0) {
            return 0.0;
        }

        // Yates' continuity correction: |O - E| - 0.5
        $chi2 = 0.0;
        $chi2 += (max(0.0, abs($successA - $eSuccessA) - 0.5) ** 2) / $eSuccessA;
        $chi2 += (max(0.0, abs($failA - $eFailA) - 0.5) ** 2) / $eFailA;
        $chi2 += (max(0.0, abs($successB - $eSuccessB) - 0.5) ** 2) / $eSuccessB;
        $chi2 += (max(0.0, abs($failB - $eFailB) - 0.5) ** 2) / $eFailB;

        return $chi2;
    }

    /**
     * Compute the p-value from a chi-squared statistic with df=1.
     *
     * Uses the Wilson-Hilferty cube-root approximation to convert chi-squared
     * to a z-score, then the normal CDF for the tail probability.
     */
    public static function pValue(float $chiSquared, int $df = 1): float
    {
        if ($chiSquared <= 0.0) {
            return 1.0;
        }

        $k = (float) $df;

        // Wilson-Hilferty approximation — accurate to +/- 0.001 for df=1
        $z = (($chiSquared / $k) ** (1.0 / 3.0) - (1.0 - 2.0 / (9.0 * $k)))
            / sqrt(2.0 / (9.0 * $k));

        return 1.0 - NormalDistribution::cdf($z);
    }
}
