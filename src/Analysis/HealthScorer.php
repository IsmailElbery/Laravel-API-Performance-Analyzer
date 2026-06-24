<?php

namespace ApiPerformanceAnalyzer\Analysis;

/**
 * Per-endpoint 0–100 composite health score.
 *
 *   score = 100 − (w1·norm(p95) + w2·error_rate% + w3·norm(query_count) + w4·n_plus_one_rate)
 *
 * Guardrails (from the spec):
 *  - Endpoints below `health.min_samples` return null ("insufficient data"),
 *    NOT an F — one error on a rarely-called endpoint must not read as catastrophic.
 *  - The component values are returned alongside the score so it is interpretable
 *    rather than a black box.
 */
class HealthScorer
{
    public function __construct(
        protected int $minSamples,
        protected array $weights,
        protected array $norm,
    ) {}

    /**
     * @param array{count:int,p95_ms:float,error_rate:float,avg_queries:float,n_plus_one_rate:float} $metrics
     * @return array{score:?float, grade:?string, components:array, insufficient_data:bool}
     */
    public function score(array $metrics): array
    {
        $count = (int) ($metrics['count'] ?? 0);

        if ($count < $this->minSamples) {
            return [
                'score' => null,
                'grade' => null,
                'insufficient_data' => true,
                'components' => $this->components($metrics),
            ];
        }

        $p95Pen = $this->normalize($metrics['p95_ms'] ?? 0, $this->norm['p95_ms'] ?? 2000);
        $errPen = min(1.0, (float) ($metrics['error_rate'] ?? 0));   // already 0..1
        $qPen = $this->normalize($metrics['avg_queries'] ?? 0, $this->norm['query_count'] ?? 50);
        $nPen = min(1.0, (float) ($metrics['n_plus_one_rate'] ?? 0));

        $penalty = 100 * (
            ($this->weights['p95'] ?? 0) * $p95Pen
            + ($this->weights['error_rate'] ?? 0) * $errPen
            + ($this->weights['query_count'] ?? 0) * $qPen
            + ($this->weights['n_plus_one'] ?? 0) * $nPen
        );

        $score = max(0.0, min(100.0, round(100 - $penalty, 1)));

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'insufficient_data' => false,
            'components' => $this->components($metrics),
        ];
    }

    protected function normalize(float $value, float $ceiling): float
    {
        if ($ceiling <= 0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $value / $ceiling));
    }

    protected function grade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    protected function components(array $metrics): array
    {
        return [
            'p95_ms' => round((float) ($metrics['p95_ms'] ?? 0), 2),
            'error_rate' => round((float) ($metrics['error_rate'] ?? 0), 4),
            'avg_queries' => round((float) ($metrics['avg_queries'] ?? 0), 2),
            'n_plus_one_rate' => round((float) ($metrics['n_plus_one_rate'] ?? 0), 4),
            'count' => (int) ($metrics['count'] ?? 0),
        ];
    }
}
