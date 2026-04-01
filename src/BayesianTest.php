<?php

declare(strict_types=1);

namespace Jekabs\AbStats;

/**
 * Bayesian A/B testing using the Beta-Binomial model with Monte Carlo simulation.
 *
 * Usage:
 *   $result = BayesianTest::evaluate(
 *       control:   Variant::make('A', successes: 120, total: 1000),
 *       treatment: Variant::make('B', successes: 150, total: 1000),
 *   );
 *
 *   $result->probabilityTreatmentWins; // 0.97...
 *   $result->isCredible;               // true/false
 */
final class BayesianTest
{
    // Default uninformative prior: Beta(1, 1) = uniform
    private const DEFAULT_ALPHA_PRIOR = 1.0;
    private const DEFAULT_BETA_PRIOR = 1.0;
    private const DEFAULT_SIMULATIONS = 50_000;

    /**
     * Generate a random sample from a Gamma distribution using Marsaglia-Tsang's method.
     *
     * For shape >= 1, uses the direct method.
     * For shape < 1, uses the transformation: Gamma(a) = Gamma(a+1) * U^(1/a).
     */
    private static function gammaSample(float $shape): float
    {
        if ($shape < 1.0) {
            // Gamma(a) = Gamma(a+1) * U^(1/a)
            $u = mt_rand() / mt_getrandmax();

            return self::gammaSample($shape + 1.0) * ($u ** (1.0 / $shape));
        }

        // Marsaglia-Tsang method for shape >= 1
        $d = $shape - 1.0 / 3.0;
        $c = 1.0 / sqrt(9.0 * $d);

        while (true) {
            do {
                // Generate standard normal using Box-Muller
                $u1 = mt_rand() / mt_getrandmax();
                $u2 = mt_rand() / mt_getrandmax();

                if ($u1 <= 0.0) {
                    continue;
                }

                $x = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

                break;
            } while (true);

            $v = (1.0 + $c * $x) ** 3;

            if ($v <= 0.0) {
                continue;
            }

            $u = mt_rand() / mt_getrandmax();

            if ($u <= 0.0) {
                continue;
            }

            // Accept/reject step
            if ($u < 1.0 - 0.0331 * ($x * $x) * ($x * $x)) {
                return $d * $v;
            }

            if (log($u) < 0.5 * $x * $x + $d * (1.0 - $v + log($v))) {
                return $d * $v;
            }
        }
    }

    /**
     * Generate a random sample from a Beta(alpha, beta) distribution.
     *
     * Uses the relationship: if X ~ Gamma(alpha, 1) and Y ~ Gamma(beta, 1),
     * then X / (X + Y) ~ Beta(alpha, beta).
     */
    private static function betaSample(float $alpha, float $beta): float
    {
        $x = self::gammaSample($alpha);
        $y = self::gammaSample($beta);

        if ($x + $y === 0.0) {
            return 0.5;
        }

        return $x / ($x + $y);
    }

    /**
     * Compute the probability that variant B beats variant A using Monte Carlo simulation.
     *
     * @param Variant $a           First variant
     * @param Variant $b           Second variant
     * @param float   $alphaPrior  Alpha parameter of the Beta prior
     * @param float   $betaPrior   Beta parameter of the Beta prior
     * @param int     $simulations Number of Monte Carlo simulations
     */
    public static function probabilityBBeatsA(
        Variant $a,
        Variant $b,
        float $alphaPrior = self::DEFAULT_ALPHA_PRIOR,
        float $betaPrior = self::DEFAULT_BETA_PRIOR,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): float {
        $alphaA = $alphaPrior + $a->successes;
        $betaA = $betaPrior + $a->failures;
        $alphaB = $alphaPrior + $b->successes;
        $betaB = $betaPrior + $b->failures;

        $bWins = 0;

        for ($i = 0; $i < $simulations; $i++) {
            $sampleA = self::betaSample($alphaA, $betaA);
            $sampleB = self::betaSample($alphaB, $betaB);

            if ($sampleB > $sampleA) {
                $bWins++;
            }
        }

        return $bWins / $simulations;
    }

    /**
     * Compute the credible interval for a Beta distribution using Monte Carlo samples.
     *
     * @param float $alpha         Alpha parameter of the posterior
     * @param float $beta          Beta parameter of the posterior
     * @param float $credibleLevel Level for the credible interval (e.g. 0.95)
     * @param int   $simulations   Number of samples
     * @return array{float, float} [lower, upper] bounds
     */
    private static function credibleInterval(
        float $alpha,
        float $beta,
        float $credibleLevel,
        int $simulations,
    ): array {
        $samples = [];

        for ($i = 0; $i < $simulations; $i++) {
            $samples[] = self::betaSample($alpha, $beta);
        }

        sort($samples);

        $tail = (1.0 - $credibleLevel) / 2.0;
        $lowerIdx = (int) floor($tail * $simulations);
        $upperIdx = (int) floor((1.0 - $tail) * $simulations) - 1;

        $lowerIdx = max(0, min($lowerIdx, $simulations - 1));
        $upperIdx = max(0, min($upperIdx, $simulations - 1));

        return [$samples[$lowerIdx], $samples[$upperIdx]];
    }

    /**
     * Evaluate a Bayesian A/B test between control and treatment variants.
     *
     * @param Variant $control       The baseline variant
     * @param Variant $treatment     The challenger variant
     * @param float   $alphaPrior    Alpha parameter of the Beta prior (default: 1.0 = uniform)
     * @param float   $betaPrior     Beta parameter of the Beta prior (default: 1.0 = uniform)
     * @param int     $simulations   Number of Monte Carlo simulations
     * @param float   $credibleLevel Level for credible intervals and significance threshold
     */
    public static function evaluate(
        Variant $control,
        Variant $treatment,
        float $alphaPrior = self::DEFAULT_ALPHA_PRIOR,
        float $betaPrior = self::DEFAULT_BETA_PRIOR,
        int $simulations = self::DEFAULT_SIMULATIONS,
        float $credibleLevel = 0.95,
    ): BayesianResult {
        $alphaControl = $alphaPrior + $control->successes;
        $betaControl = $betaPrior + $control->failures;
        $alphaTreatment = $alphaPrior + $treatment->successes;
        $betaTreatment = $betaPrior + $treatment->failures;

        // Generate samples for both variants
        $controlSamples = [];
        $treatmentSamples = [];
        $liftSamples = [];
        $treatmentWins = 0;

        for ($i = 0; $i < $simulations; $i++) {
            $cSample = self::betaSample($alphaControl, $betaControl);
            $tSample = self::betaSample($alphaTreatment, $betaTreatment);

            $controlSamples[] = $cSample;
            $treatmentSamples[] = $tSample;
            $liftSamples[] = $tSample - $cSample;

            if ($tSample > $cSample) {
                $treatmentWins++;
            }
        }

        $probabilityTreatmentWins = $treatmentWins / $simulations;

        // Credible intervals from sorted samples
        sort($controlSamples);
        sort($treatmentSamples);
        sort($liftSamples);

        $tail = (1.0 - $credibleLevel) / 2.0;
        $lowerIdx = max(0, (int) floor($tail * $simulations));
        $upperIdx = max(0, min((int) floor((1.0 - $tail) * $simulations) - 1, $simulations - 1));

        $controlCI = [$controlSamples[$lowerIdx], $controlSamples[$upperIdx]];
        $treatmentCI = [$treatmentSamples[$lowerIdx], $treatmentSamples[$upperIdx]];
        $liftCI = [$liftSamples[$lowerIdx], $liftSamples[$upperIdx]];

        // Expected lift = mean of lift samples
        $expectedLift = array_sum($liftSamples) / $simulations;

        // Credible if probability exceeds the threshold
        $isCredible = $probabilityTreatmentWins > $credibleLevel;

        $reason = $isCredible
            ? sprintf(
                '%s wins with %.2f%% probability (%.2f%% vs %.2f%%, expected lift=%+.4f)',
                $treatment->name,
                $probabilityTreatmentWins * 100,
                $treatment->conversionRate * 100,
                $control->conversionRate * 100,
                $expectedLift,
            )
            : sprintf(
                'No credible difference: P(treatment > control) = %.2f%% (threshold: %.0f%%)',
                $probabilityTreatmentWins * 100,
                $credibleLevel * 100,
            );

        return new BayesianResult(
            control: $control,
            treatment: $treatment,
            probabilityTreatmentWins: $probabilityTreatmentWins,
            controlCredibleInterval: $controlCI,
            treatmentCredibleInterval: $treatmentCI,
            expectedLift: $expectedLift,
            liftCredibleInterval: $liftCI,
            isCredible: $isCredible,
            reason: $reason,
        );
    }

    /**
     * Evaluate multiple treatments against a single control using Bayesian analysis.
     * Returns results sorted by probability of beating control (highest first).
     *
     * @param Variant   $control    The baseline variant
     * @param Variant[] $treatments Array of challenger variants
     * @return BayesianResult[]
     */
    public static function evaluateMultiple(
        Variant $control,
        array $treatments,
        float $alphaPrior = self::DEFAULT_ALPHA_PRIOR,
        float $betaPrior = self::DEFAULT_BETA_PRIOR,
        int $simulations = self::DEFAULT_SIMULATIONS,
        float $credibleLevel = 0.95,
    ): array {
        $results = array_map(
            fn (Variant $treatment) => self::evaluate(
                $control,
                $treatment,
                $alphaPrior,
                $betaPrior,
                $simulations,
                $credibleLevel,
            ),
            $treatments,
        );

        usort(
            $results,
            fn (BayesianResult $a, BayesianResult $b) => $b->probabilityTreatmentWins <=> $a->probabilityTreatmentWins,
        );

        return $results;
    }
}
