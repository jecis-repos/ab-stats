# jekabs/ab-stats

Zero-dependency PHP A/B testing statistics. Pure math, no frameworks, no external libraries.

## What's Inside

- **Chi-squared test** with Yates' continuity correction for 2×2 contingency tables
- **Wilson-Hilferty** cube-root approximation for chi-squared → z-score conversion
- **Abramowitz & Stegun** polynomial approximation for normal CDF (max error: 7.5×10⁻⁸)
- **Minimum sample guard** — won't declare significance below configurable threshold
- **Auto-winner detection** — identifies which variant wins and by how much
- **Multi-variant support** — test multiple treatments against one control

## Installation

Install directly from the public Git repository with Composer. Packagist registration is pending.

```bash
composer config repositories.ab-stats vcs https://github.com/jecis-repos/ab-stats.git
composer require jekabs/ab-stats:dev-main
```

## Usage

```php
use Jekabs\AbStats\ABTest;
use Jekabs\AbStats\Variant;

$result = ABTest::evaluate(
    control:   Variant::make('Original', successes: 120, total: 1000),
    treatment: Variant::make('Redesign', successes: 150, total: 1000),
);

$result->isSignificant;  // false (p >= 0.05)
$result->winner;          // null
$result->pValue;          // 0.054628...
$result->liftPercent();   // '+25.00%'
$result->chiSquared;      // 3.600942...
$result->reason;          // 'p=0.0546, not significant at α=0.05'
```

### Custom Significance Level

```php
// 99% confidence (α = 0.01)
$result = ABTest::evaluate($control, $treatment, significance: 0.01);
```

### Minimum Sample Size

```php
// Require at least 500 observations per variant
$result = ABTest::evaluate($control, $treatment, minSampleSize: 500);

if (!$result->isSignificant) {
    echo $result->reason; // "Insufficient data: need at least 500..."
}
```

### Multiple Treatments

```php
$results = ABTest::evaluateMultiple(
    control: Variant::make('Control', successes: 50, total: 500),
    treatments: [
        Variant::make('Blue CTA',  successes: 65, total: 500),
        Variant::make('Green CTA', successes: 80, total: 500),
        Variant::make('Red CTA',   successes: 52, total: 500),
    ],
);

// Results sorted by p-value (most significant first)
foreach ($results as $r) {
    echo "{$r->treatment->name}: {$r->liftPercent()} (p={$r->pValue})\n";
}
```

### JSON/Array Export

```php
$array = $result->toArray();
// Returns structured array with all metrics — ready for API responses or logging
```

## How It Works

### Statistical Method

1. Build a 2×2 contingency table (success/failure × control/treatment)
2. Compute expected frequencies under the null hypothesis (no difference)
3. Apply **Yates' continuity correction** (conservative for small samples)
4. Convert chi-squared statistic to p-value via **Wilson-Hilferty approximation** (df=1)
5. The Wilson-Hilferty converts chi-squared to a z-score, then **Abramowitz & Stegun** gives the tail probability

### Why Not Just Use a Library?

Most PHP statistics packages either:
- Pull in Python/R via FFI (heavy, requires runtime)
- Use lookup tables (limited precision)
- Depend on `ext-stats` (not available on most hosts)

This package is **pure PHP math** with known error bounds. The Abramowitz & Stegun approximation for the normal CDF has a maximum error of 7.5×10⁻⁸ — more accurate than a lookup table and fast enough for real-time evaluation.

## Requirements

- PHP 8.1+
- No extensions required
- No dependencies

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
