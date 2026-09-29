<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Immutable result of an A/B test comparison.
 */
final class TestResult
{
    public function __construct(
        public readonly Variant $control,
        public readonly Variant $treatment,
        public readonly float $chiSquared,
        public readonly float $pValue,
        public readonly float $significanceLevel,
        public readonly bool $isSignificant,
        public readonly ?string $winner,
        public readonly float $lift,
        public readonly string $reason,
    ) {}

    /**
     * Relative improvement of treatment over control as a percentage string.
     */
    public function liftPercent(): string
    {
        return sprintf('%+.2f%%', $this->lift * 100);
    }

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
            'chi_squared' => round($this->chiSquared, 4),
            'p_value' => round($this->pValue, 6),
            'significance_level' => $this->significanceLevel,
            'is_significant' => $this->isSignificant,
            'winner' => $this->winner,
            'lift' => round($this->lift, 6),
            'lift_percent' => $this->liftPercent(),
            'reason' => $this->reason,
        ];
    }
}
