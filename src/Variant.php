<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Immutable value object representing one variant in an A/B test.
 */
final readonly class Variant
{
    public int $failures;
    public float $conversionRate;

    public function __construct(
        public string $name,
        public int $successes,
        public int $total,
    ) {
        if ($total < 0) {
            throw new \InvalidArgumentException("Total must be non-negative, got {$total}");
        }

        if ($successes < 0 || $successes > $total) {
            throw new \InvalidArgumentException(
                "Successes must be between 0 and total ({$total}), got {$successes}"
            );
        }

        $this->failures = $total - $successes;
        $this->conversionRate = $total > 0 ? $successes / $total : 0.0;
    }

    public static function make(string $name, int $successes, int $total): self
    {
        return new self($name, $successes, $total);
    }
}
