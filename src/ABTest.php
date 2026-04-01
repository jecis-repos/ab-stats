<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * A/B test evaluator with configurable significance level and minimum sample guard.
 *
 * Usage:
 *   $result = ABTest::evaluate(
 *       control:   Variant::make('A', successes: 120, total: 1000),
 *       treatment: Variant::make('B', successes: 150, total: 1000),
 *   );
 *
 *   $result->isSignificant; // true/false
 *   $result->winner;        // 'B' or null
 *   $result->pValue;        // 0.0234...
 *   $result->liftPercent(); // '+25.00%'
 */
final class ABTest
{
    private const DEFAULT_SIGNIFICANCE = 0.05;
    private const DEFAULT_MIN_SAMPLE = 100;

    /**
     * Evaluate an A/B test between control and treatment variants.
     *
     * @param Variant $control        The baseline variant
     * @param Variant $treatment      The challenger variant
     * @param float   $significance   Significance level (default 0.05 = 95% confidence)
     * @param int     $minSampleSize  Minimum observations per variant before declaring significance
     */
    public static function evaluate(
        Variant $control,
        Variant $treatment,
        float $significance = self::DEFAULT_SIGNIFICANCE,
        int $minSampleSize = self::DEFAULT_MIN_SAMPLE,
    ): TestResult {
        // Lift: relative improvement of treatment over control
        $lift = $control->conversionRate > 0.0
            ? ($treatment->conversionRate - $control->conversionRate) / $control->conversionRate
            : 0.0;

        // Minimum sample guard
        if ($control->total < $minSampleSize || $treatment->total < $minSampleSize) {
            return new TestResult(
                control: $control,
                treatment: $treatment,
                chiSquared: 0.0,
                pValue: 1.0,
                significanceLevel: $significance,
                isSignificant: false,
                winner: null,
                lift: $lift,
                reason: sprintf(
                    'Insufficient data: need at least %d observations per variant (control=%d, treatment=%d)',
                    $minSampleSize,
                    $control->total,
                    $treatment->total,
                ),
            );
        }

        // Identical conversion rates — no test needed
        if ($control->conversionRate === $treatment->conversionRate) {
            return new TestResult(
                control: $control,
                treatment: $treatment,
                chiSquared: 0.0,
                pValue: 1.0,
                significanceLevel: $significance,
                isSignificant: false,
                winner: null,
                lift: 0.0,
                reason: 'Conversion rates are identical',
            );
        }

        $chi2 = ChiSquared::statistic(
            $control->successes, $control->total,
            $treatment->successes, $treatment->total,
        );

        $pValue = ChiSquared::pValue($chi2);
        $isSignificant = $pValue < $significance;

        $winner = null;
        $reason = sprintf('p=%.4f, not significant at α=%.2f', $pValue, $significance);

        if ($isSignificant) {
            $winner = $treatment->conversionRate > $control->conversionRate
                ? $treatment->name
                : $control->name;

            $reason = sprintf(
                '%s wins with %.2f%% vs %.2f%% (p=%.4f, α=%.2f, lift=%s)',
                $winner,
                ($winner === $treatment->name ? $treatment->conversionRate : $control->conversionRate) * 100,
                ($winner === $treatment->name ? $control->conversionRate : $treatment->conversionRate) * 100,
                $pValue,
                $significance,
                sprintf('%+.2f%%', $lift * 100),
            );
        }

        return new TestResult(
            control: $control,
            treatment: $treatment,
            chiSquared: $chi2,
            pValue: $pValue,
            significanceLevel: $significance,
            isSignificant: $isSignificant,
            winner: $winner,
            lift: $lift,
            reason: $reason,
        );
    }

    /**
     * Evaluate multiple treatments against a single control.
     * Returns results sorted by p-value (most significant first).
     *
     * When Bonferroni correction is enabled (default), each p-value is multiplied
     * by the number of comparisons to control the family-wise error rate.
     *
     * @param Variant   $control     The baseline variant
     * @param Variant[] $treatments  Array of challenger variants
     * @param float     $significance Significance level (default 0.05)
     * @param int       $minSampleSize Minimum observations per variant
     * @param bool      $bonferroni  Apply Bonferroni correction (default true)
     * @return TestResult[]
     */
    public static function evaluateMultiple(
        Variant $control,
        array $treatments,
        float $significance = self::DEFAULT_SIGNIFICANCE,
        int $minSampleSize = self::DEFAULT_MIN_SAMPLE,
        bool $bonferroni = true,
    ): array {
        $results = array_map(
            fn (Variant $treatment) => self::evaluate($control, $treatment, $significance, $minSampleSize),
            $treatments,
        );

        if ($bonferroni) {
            $k = count($treatments);
            $results = array_map(static function (TestResult $r) use ($k, $significance): TestResult {
                $correctedP = min($r->pValue * $k, 1.0);
                $isSignificant = $correctedP < $significance;

                $winner = null;
                $reason = sprintf(
                    'p=%.4f (Bonferroni-corrected x%d), not significant at α=%.2f',
                    $correctedP,
                    $k,
                    $significance,
                );

                if ($isSignificant) {
                    $winner = $r->treatment->conversionRate > $r->control->conversionRate
                        ? $r->treatment->name
                        : $r->control->name;

                    $reason = sprintf(
                        '%s wins with %.2f%% vs %.2f%% (p=%.4f Bonferroni-corrected x%d, α=%.2f, lift=%s)',
                        $winner,
                        ($winner === $r->treatment->name ? $r->treatment->conversionRate : $r->control->conversionRate) * 100,
                        ($winner === $r->treatment->name ? $r->control->conversionRate : $r->treatment->conversionRate) * 100,
                        $correctedP,
                        $k,
                        $significance,
                        sprintf('%+.2f%%', $r->lift * 100),
                    );
                }

                // Handle insufficient data case — preserve original reason
                if ($r->pValue === 1.0 && str_contains($r->reason, 'Insufficient data')) {
                    $reason = $r->reason;
                    $isSignificant = false;
                    $winner = null;
                }

                // Handle identical rates case
                if ($r->reason === 'Conversion rates are identical') {
                    $reason = $r->reason;
                }

                return new TestResult(
                    control: $r->control,
                    treatment: $r->treatment,
                    chiSquared: $r->chiSquared,
                    pValue: $correctedP,
                    significanceLevel: $significance,
                    isSignificant: $isSignificant,
                    winner: $winner,
                    lift: $r->lift,
                    reason: $reason,
                );
            }, $results);
        }

        usort($results, fn (TestResult $a, TestResult $b) => $a->pValue <=> $b->pValue);

        return $results;
    }
}
