<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\ABTest;
use Jekabs\AbStats\ChiSquared;
use Jekabs\AbStats\NormalDistribution;
use Jekabs\AbStats\Variant;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end scenarios simulating real A/B test workflows.
 */
final class EndToEndTest extends TestCase
{
    // ──────────────────────────────────────
    // Scenario 1: Landing page CTA button color test
    // ──────────────────────────────────────
    public function test_cta_button_color_test(): void
    {
        // Blue button: 3.2% conversion (320/10000)
        // Green button: 3.8% conversion (380/10000)
        $result = ABTest::evaluate(
            control:   Variant::make('Blue CTA', successes: 320, total: 10000),
            treatment: Variant::make('Green CTA', successes: 380, total: 10000),
        );

        $this->assertTrue($result->isSignificant, "With 10K samples per variant and 0.6pp difference, should be significant");
        $this->assertSame('Green CTA', $result->winner);
        $this->assertLessThan(0.05, $result->pValue);

        // Lift should be ~18.75% ((3.8-3.2)/3.2)
        $this->assertEqualsWithDelta(0.1875, $result->lift, 0.01);
        $this->assertSame('+18.75%', $result->liftPercent());
    }

    // ──────────────────────────────────────
    // Scenario 2: Too early to call — need more data
    // ──────────────────────────────────────
    public function test_too_early_to_call(): void
    {
        // Only 50 visitors each — below minimum sample
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 5, total: 50),
            treatment: Variant::make('B', successes: 8, total: 50),
            minSampleSize: 100,
        );

        $this->assertFalse($result->isSignificant);
        $this->assertNull($result->winner);
        $this->assertStringContainsString('Insufficient data', $result->reason);
        $this->assertSame(1.0, $result->pValue, "P-value should be 1.0 when insufficient data");
    }

    // ──────────────────────────────────────
    // Scenario 3: Control actually wins (treatment is worse)
    // ──────────────────────────────────────
    public function test_control_wins_treatment_is_worse(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('Original', successes: 500, total: 5000),  // 10%
            treatment: Variant::make('Redesign', successes: 350, total: 5000),  // 7%
        );

        $this->assertTrue($result->isSignificant);
        $this->assertSame('Original', $result->winner);
        $this->assertLessThan(0.0, $result->lift); // Negative lift = treatment is worse
    }

    // ──────────────────────────────────────
    // Scenario 4: Multi-variant email subject line test
    // ──────────────────────────────────────
    public function test_multivariant_email_subject_lines(): void
    {
        $results = ABTest::evaluateMultiple(
            control: Variant::make('Boring Subject', successes: 200, total: 5000),  // 4%
            treatments: [
                Variant::make('Emoji Subject 🎉', successes: 220, total: 5000),    // 4.4%
                Variant::make('Question Subject?', successes: 300, total: 5000),    // 6%
                Variant::make('CAPS SUBJECT', successes: 195, total: 5000),         // 3.9%
                Variant::make('Personalized', successes: 280, total: 5000),         // 5.6%
            ],
        );

        $this->assertCount(4, $results);

        // Results sorted by p-value (most significant first)
        // Question Subject (6% vs 4%) should be the most significant winner
        $this->assertSame('Question Subject?', $results[0]->treatment->name);
        $this->assertTrue($results[0]->isSignificant);

        // CAPS SUBJECT (3.9% vs 4%) should be the least significant (no difference)
        $lastResult = end($results);
        $this->assertSame('CAPS SUBJECT', $lastResult->treatment->name);
        $this->assertFalse($lastResult->isSignificant);
    }

    // ──────────────────────────────────────
    // Scenario 5: toArray() for API response
    // ──────────────────────────────────────
    public function test_api_response_format(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 100, total: 1000),
            treatment: Variant::make('B', successes: 150, total: 1000),
        );

        $array = $result->toArray();

        // Should be JSON-serializable
        $json = json_encode($array);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertSame('A', $decoded['control']['name']);
        $this->assertSame('B', $decoded['treatment']['name']);
        $this->assertSame(100, $decoded['control']['successes']);
        $this->assertSame(1000, $decoded['control']['total']);
        $this->assertSame(0.1, $decoded['control']['conversion_rate']);
        $this->assertSame(0.15, $decoded['treatment']['conversion_rate']);
        $this->assertIsBool($decoded['is_significant']);
        $this->assertIsString($decoded['lift_percent']);
        $this->assertIsString($decoded['reason']);
    }

    // ──────────────────────────────────────
    // Scenario 6: Edge case — zero conversions both sides
    // ──────────────────────────────────────
    public function test_zero_conversions_both_sides(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 0, total: 1000),
            treatment: Variant::make('B', successes: 0, total: 1000),
        );

        $this->assertFalse($result->isSignificant);
        $this->assertSame('Conversion rates are identical', $result->reason);
    }

    // ──────────────────────────────────────
    // Scenario 7: 100% conversion both sides
    // ──────────────────────────────────────
    public function test_perfect_conversion_both_sides(): void
    {
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 1000, total: 1000),
            treatment: Variant::make('B', successes: 1000, total: 1000),
        );

        $this->assertFalse($result->isSignificant);
    }

    // ──────────────────────────────────────
    // Scenario 8: Stricter significance level (99% confidence)
    // ──────────────────────────────────────
    public function test_stricter_significance_level(): void
    {
        // This is significant at 95% but maybe not at 99%
        $control = Variant::make('A', successes: 100, total: 1000);
        $treatment = Variant::make('B', successes: 130, total: 1000);

        $result95 = ABTest::evaluate($control, $treatment, significance: 0.05);
        $result99 = ABTest::evaluate($control, $treatment, significance: 0.01);

        // At 95% confidence with 3pp difference over 1000 samples, should be significant
        $this->assertTrue($result95->isSignificant);
        // Same p-value should be computed in both
        $this->assertSame($result95->pValue, $result99->pValue);
        $this->assertSame($result95->chiSquared, $result99->chiSquared);
    }

    // ──────────────────────────────────────
    // Scenario 9: Mathematical correctness — chi-squared critical values
    // ──────────────────────────────────────
    public function test_chi_squared_critical_values_df1(): void
    {
        // Known critical values for chi-squared with df=1:
        // α=0.10 → 2.706, α=0.05 → 3.841, α=0.01 → 6.635, α=0.001 → 10.828

        $this->assertEqualsWithDelta(0.10, ChiSquared::pValue(2.706), 0.015);
        $this->assertEqualsWithDelta(0.05, ChiSquared::pValue(3.841), 0.010);
        $this->assertEqualsWithDelta(0.01, ChiSquared::pValue(6.635), 0.005);
        $this->assertEqualsWithDelta(0.001, ChiSquared::pValue(10.828), 0.002);
    }

    // ──────────────────────────────────────
    // Scenario 10: NormalCDF precision against known NIST values
    // ──────────────────────────────────────
    public function test_normal_cdf_nist_precision(): void
    {
        // High-precision reference values from NIST (tuples to avoid float key coercion)
        $nist = [
            [0.0, 0.5000000000],
            [0.5, 0.6914624613],
            [1.0, 0.8413447461],
            [1.5, 0.9331927987],
            [2.0, 0.9772498681],
            [2.5, 0.9937903347],
            [3.0, 0.9986501020],
        ];

        foreach ($nist as [$z, $expected]) {
            $actual = NormalDistribution::cdf((float) $z);
            $error = abs($expected - $actual);
            $this->assertLessThan(
                1e-6,
                $error,
                sprintf("CDF(%.1f): expected %.10f, got %.10f, error=%.2e", $z, $expected, $actual, $error),
            );
        }
    }

    // ──────────────────────────────────────
    // Scenario 11: Batch processing — simulating production auto-winner detection
    // ──────────────────────────────────────
    public function test_batch_auto_winner_detection(): void
    {
        $campaigns = [
            ['control' => [120, 1000], 'treatment' => [180, 1000]],  // Clear winner
            ['control' => [50, 500],   'treatment' => [52, 500]],    // No winner
            ['control' => [300, 3000], 'treatment' => [250, 3000]],  // Control wins
        ];

        $winners = [];
        foreach ($campaigns as $i => $c) {
            $result = ABTest::evaluate(
                Variant::make("control-{$i}", $c['control'][0], $c['control'][1]),
                Variant::make("treatment-{$i}", $c['treatment'][0], $c['treatment'][1]),
            );
            $winners[$i] = $result->winner;
        }

        $this->assertSame("treatment-0", $winners[0]);  // Treatment wins
        $this->assertNull($winners[1]);                   // No winner
        $this->assertSame("control-2", $winners[2]);     // Control wins
    }

    // ──────────────────────────────────────
    // Scenario 12: Asymmetric sample sizes
    // ──────────────────────────────────────
    public function test_asymmetric_sample_sizes(): void
    {
        // Real scenario: 90/10 traffic split
        $result = ABTest::evaluate(
            control:   Variant::make('A', successes: 900, total: 9000),   // 10%
            treatment: Variant::make('B', successes: 150, total: 1000),   // 15%
        );

        $this->assertTrue($result->isSignificant);
        $this->assertSame('B', $result->winner);
        // Lift should be 50%
        $this->assertEqualsWithDelta(0.5, $result->lift, 0.01);
    }
}
