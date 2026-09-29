<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Immutable result of a Bayesian A/B test comparison.
 */
final class BayesianResult
{
    /**
     * @param Variant       $control
     * @param Variant       $treatment
     * @param float         $probabilityTreatmentWins  P(treatment > control) in [0, 1]
     * @param array{float, float} $controlCredibleInterval   [lower, upper] bounds
     * @param array{float, float} $treatmentCredibleInterval [lower, upper] bounds
     * @param float         $expectedLift              Mean(treatment) - Mean(control)
     * @param array{float, float} $liftCredibleInterval      [lower, upper] bounds
     * @param bool          $isCredible                Whether the result exceeds the credible threshold
     * @param string        $reason                    Human-readable explanation
     */
    public function __construct(
        public readonly Variant $control,
        public readonly Variant $treatment,
        public readonly float $probabilityTreatmentWins,
        public readonly array $controlCredibleInterval,
        public readonly array $treatmentCredibleInterval,
        public readonly float $expectedLift,
        public readonly array $liftCredibleInterval,
        public readonly bool $isCredible,
        public readonly string $reason,
    ) {}

    public function toArray(): array
    {
        return [
            'control' => [
                'name' => $this->control->name,
                'successes' => $this->control->successes,
                'total' => $this->control->total,
                'conversion_rate' => round($this->control->conversionRate, 6),
            ],
            'treatment' => [
                'name' => $this->treatment->name,
                'successes' => $this->treatment->successes,
                'total' => $this->treatment->total,
                'conversion_rate' => round($this->treatment->conversionRate, 6),
            ],
            'probability_treatment_wins' => round($this->probabilityTreatmentWins, 6),
            'control_credible_interval' => [
                round($this->controlCredibleInterval[0], 6),
                round($this->controlCredibleInterval[1], 6),
            ],
            'treatment_credible_interval' => [
                round($this->treatmentCredibleInterval[0], 6),
                round($this->treatmentCredibleInterval[1], 6),
            ],
            'expected_lift' => round($this->expectedLift, 6),
            'lift_credible_interval' => [
                round($this->liftCredibleInterval[0], 6),
                round($this->liftCredibleInterval[1], 6),
            ],
            'is_credible' => $this->isCredible,
            'reason' => $this->reason,
        ];
    }
}
