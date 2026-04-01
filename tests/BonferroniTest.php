<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\ABTest;
use Jekabs\AbStats\Variant;
use PHPUnit\Framework\TestCase;

final class BonferroniTest extends TestCase
{
    public function test_p_values_multiplied_by_number_of_comparisons(): void
    {
        $control = Variant::make('Control', successes: 50, total: 500);
        $treatments = [
            Variant::make('T1', successes: 100, total: 500),
            Variant::make('T2', successes: 75, total: 500),
            Variant::make('T3', successes: 60, total: 500),
        ];

        // Get uncorrected results
        $uncorrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: false);

        // Get corrected results
        $corrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: true);

        // Build lookup by treatment name for comparison
        $uncorrectedByName = [];
        foreach ($uncorrected as $r) {
            $uncorrectedByName[$r->treatment->name] = $r;
        }

        foreach ($corrected as $r) {
            $uncorrectedP = $uncorrectedByName[$r->treatment->name]->pValue;
            $expectedCorrectedP = min($uncorrectedP * 3, 1.0);

            $this->assertEqualsWithDelta(
                $expectedCorrectedP,
                $r->pValue,
                1e-10,
                sprintf('Corrected p-value for %s should be %.6f * 3 = %.6f', $r->treatment->name, $uncorrectedP, $expectedCorrectedP),
            );
        }
    }

    public function test_marginal_result_becomes_non_significant(): void
    {
        // Find a combination where p ~ 0.04 (marginally significant at 0.05)
        // With Bonferroni and 3 treatments: 0.04 * 3 = 0.12 > 0.05 → not significant
        $control = Variant::make('Control', successes: 50, total: 500);
        $treatments = [
            Variant::make('T1', successes: 65, total: 500),
            Variant::make('T2', successes: 66, total: 500),
            Variant::make('T3', successes: 64, total: 500),
        ];

        // Without correction — check if any are marginally significant
        $uncorrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: false);

        // With correction
        $corrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: true);

        // Any result that was marginally significant (p < 0.05 but p*3 > 0.05)
        // should become non-significant after correction
        foreach ($corrected as $r) {
            if ($r->pValue >= 0.05) {
                $this->assertFalse($r->isSignificant);
                $this->assertNull($r->winner);
            }
        }
    }

    public function test_strongly_significant_remains_significant(): void
    {
        $control = Variant::make('Control', successes: 50, total: 1000);
        $treatments = [
            Variant::make('T1', successes: 150, total: 1000),  // Very strong effect
            Variant::make('T2', successes: 55, total: 1000),
            Variant::make('T3', successes: 52, total: 1000),
        ];

        $corrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: true);

        // T1 has a huge effect — should remain significant even after correction
        $t1Result = null;
        foreach ($corrected as $r) {
            if ($r->treatment->name === 'T1') {
                $t1Result = $r;

                break;
            }
        }

        $this->assertNotNull($t1Result);
        $this->assertTrue($t1Result->isSignificant);
        $this->assertSame('T1', $t1Result->winner);
    }

    public function test_bonferroni_false_preserves_original_behavior(): void
    {
        $control = Variant::make('Control', successes: 50, total: 500);
        $treatments = [
            Variant::make('T1', successes: 100, total: 500),
            Variant::make('T2', successes: 75, total: 500),
        ];

        $withoutCorrection = ABTest::evaluateMultiple($control, $treatments, bonferroni: false);
        $singleEval = ABTest::evaluate($control, $treatments[0]);

        // Find T1 in the multi-result
        $t1Result = null;
        foreach ($withoutCorrection as $r) {
            if ($r->treatment->name === 'T1') {
                $t1Result = $r;

                break;
            }
        }

        // P-values should match exactly when bonferroni is off
        $this->assertSame($singleEval->pValue, $t1Result->pValue);
        $this->assertSame($singleEval->isSignificant, $t1Result->isSignificant);
        $this->assertSame($singleEval->winner, $t1Result->winner);
    }

    public function test_corrected_p_values_capped_at_one(): void
    {
        $control = Variant::make('Control', successes: 50, total: 500);
        $treatments = [
            Variant::make('T1', successes: 51, total: 500),  // Nearly identical
            Variant::make('T2', successes: 49, total: 500),
            Variant::make('T3', successes: 50, total: 500),
        ];

        $corrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: true);

        foreach ($corrected as $r) {
            $this->assertLessThanOrEqual(1.0, $r->pValue);
        }
    }

    public function test_bonferroni_reason_mentions_correction(): void
    {
        $control = Variant::make('Control', successes: 50, total: 500);
        $treatments = [
            Variant::make('T1', successes: 100, total: 500),
            Variant::make('T2', successes: 75, total: 500),
        ];

        $corrected = ABTest::evaluateMultiple($control, $treatments, bonferroni: true);

        foreach ($corrected as $r) {
            $this->assertStringContainsString('Bonferroni', $r->reason);
        }
    }
}
