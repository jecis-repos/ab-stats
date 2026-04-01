<?php

declare(strict_types=1);

namespace Jekabs\AbStats\Tests;

use Jekabs\AbStats\BayesianResult;
use Jekabs\AbStats\BayesianTest;
use Jekabs\AbStats\Variant;
use PHPUnit\Framework\TestCase;

final class BayesianTestTest extends TestCase
{
    protected function setUp(): void
    {
        mt_srand(42);
    }

    public function test_clearly_better_variant_has_high_probability(): void
    {
        $a = Variant::make('A', successes: 300, total: 1000);
        $b = Variant::make('B', successes: 500, total: 1000);

        $prob = BayesianTest::probabilityBBeatsA($a, $b);

        $this->assertGreaterThan(0.99, $prob);
    }

    public function test_identical_variants_have_equal_probability(): void
    {
        $a = Variant::make('A', successes: 200, total: 1000);
        $b = Variant::make('B', successes: 200, total: 1000);

        $prob = BayesianTest::probabilityBBeatsA($a, $b);

        $this->assertEqualsWithDelta(0.5, $prob, 0.05);
    }

    public function test_credible_intervals_contain_true_rate(): void
    {
        $control = Variant::make('A', successes: 100, total: 1000);   // True rate: 0.10
        $treatment = Variant::make('B', successes: 150, total: 1000); // True rate: 0.15

        $result = BayesianTest::evaluate($control, $treatment);

        // Control CI lower bound should be below 0.10, upper should be above 0.10
        $this->assertLessThan(0.10, $result->controlCredibleInterval[0]);
        $this->assertGreaterThan(0.10, $result->controlCredibleInterval[1]);

        // Treatment CI lower bound should be below 0.15, upper should be above 0.15
        $this->assertLessThan(0.15, $result->treatmentCredibleInterval[0]);
        $this->assertGreaterThan(0.15, $result->treatmentCredibleInterval[1]);
    }

    public function test_evaluate_returns_credible_result_for_clear_winner(): void
    {
        $result = BayesianTest::evaluate(
            control: Variant::make('A', successes: 300, total: 1000),
            treatment: Variant::make('B', successes: 500, total: 1000),
        );

        $this->assertTrue($result->isCredible);
        $this->assertGreaterThan(0.95, $result->probabilityTreatmentWins);
        $this->assertGreaterThan(0.0, $result->expectedLift);
        $this->assertStringContainsString('B wins', $result->reason);
    }

    public function test_evaluate_returns_non_credible_for_similar_variants(): void
    {
        $result = BayesianTest::evaluate(
            control: Variant::make('A', successes: 100, total: 1000),
            treatment: Variant::make('B', successes: 102, total: 1000),
        );

        $this->assertFalse($result->isCredible);
        $this->assertStringContainsString('No credible difference', $result->reason);
    }

    public function test_evaluate_multiple_returns_sorted_results(): void
    {
        $results = BayesianTest::evaluateMultiple(
            control: Variant::make('Control', successes: 100, total: 1000),
            treatments: [
                Variant::make('Slight', successes: 105, total: 1000),
                Variant::make('Clear', successes: 200, total: 1000),
                Variant::make('Medium', successes: 140, total: 1000),
            ],
        );

        $this->assertCount(3, $results);

        // Sorted by probability descending — "Clear" should be first
        $this->assertSame('Clear', $results[0]->treatment->name);
        $this->assertGreaterThan(0.95, $results[0]->probabilityTreatmentWins);

        // Each subsequent result should have equal or lower probability
        for ($i = 1; $i < count($results); $i++) {
            $this->assertGreaterThanOrEqual(
                $results[$i]->probabilityTreatmentWins,
                $results[$i - 1]->probabilityTreatmentWins,
            );
        }
    }

    public function test_to_array_serialization(): void
    {
        $result = BayesianTest::evaluate(
            control: Variant::make('A', successes: 100, total: 1000),
            treatment: Variant::make('B', successes: 150, total: 1000),
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('control', $array);
        $this->assertArrayHasKey('treatment', $array);
        $this->assertArrayHasKey('probability_treatment_wins', $array);
        $this->assertArrayHasKey('control_credible_interval', $array);
        $this->assertArrayHasKey('treatment_credible_interval', $array);
        $this->assertArrayHasKey('expected_lift', $array);
        $this->assertArrayHasKey('lift_credible_interval', $array);
        $this->assertArrayHasKey('is_credible', $array);
        $this->assertArrayHasKey('reason', $array);

        // Should be JSON-serializable
        $json = json_encode($array);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertSame('A', $decoded['control']['name']);
        $this->assertSame('B', $decoded['treatment']['name']);
        $this->assertIsFloat($decoded['probability_treatment_wins']);
        $this->assertIsArray($decoded['control_credible_interval']);
        $this->assertCount(2, $decoded['control_credible_interval']);
        $this->assertIsBool($decoded['is_credible']);
    }

    public function test_expected_lift_sign_matches_direction(): void
    {
        // Treatment is better
        $result = BayesianTest::evaluate(
            control: Variant::make('A', successes: 100, total: 1000),
            treatment: Variant::make('B', successes: 200, total: 1000),
        );
        $this->assertGreaterThan(0.0, $result->expectedLift);

        // Treatment is worse
        mt_srand(42);
        $result = BayesianTest::evaluate(
            control: Variant::make('A', successes: 200, total: 1000),
            treatment: Variant::make('B', successes: 100, total: 1000),
        );
        $this->assertLessThan(0.0, $result->expectedLift);
    }
}
