<?php

namespace ApiPerformanceAnalyzer\Tests\Unit;

use ApiPerformanceAnalyzer\Analysis\HealthScorer;
use PHPUnit\Framework\TestCase;

class HealthScorerTest extends TestCase
{
    protected function scorer(): HealthScorer
    {
        return new HealthScorer(
            minSamples: 50,
            weights: ['p95' => 0.4, 'error_rate' => 0.3, 'query_count' => 0.2, 'n_plus_one' => 0.1],
            norm: ['p95_ms' => 2000, 'query_count' => 50],
        );
    }

    public function test_insufficient_data_returns_null_not_f(): void
    {
        $result = $this->scorer()->score([
            'count' => 3, 'p95_ms' => 5000, 'error_rate' => 1.0, 'avg_queries' => 80, 'n_plus_one_rate' => 1.0,
        ]);

        $this->assertTrue($result['insufficient_data']);
        $this->assertNull($result['score']);
        $this->assertNull($result['grade']);
    }

    public function test_healthy_endpoint_scores_high(): void
    {
        $result = $this->scorer()->score([
            'count' => 1000, 'p95_ms' => 50, 'error_rate' => 0.0, 'avg_queries' => 3, 'n_plus_one_rate' => 0.0,
        ]);

        $this->assertFalse($result['insufficient_data']);
        $this->assertGreaterThanOrEqual(90, $result['score']);
        $this->assertSame('A', $result['grade']);
    }

    public function test_unhealthy_endpoint_scores_low(): void
    {
        $result = $this->scorer()->score([
            'count' => 1000, 'p95_ms' => 2000, 'error_rate' => 0.5, 'avg_queries' => 50, 'n_plus_one_rate' => 1.0,
        ]);

        $this->assertLessThan(40, $result['score']);
        $this->assertContains($result['grade'], ['D', 'F']);
    }
}
