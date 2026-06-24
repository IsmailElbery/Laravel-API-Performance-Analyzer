<?php

namespace ApiPerformanceAnalyzer\Alerts;

use ApiPerformanceAnalyzer\Models\AlertEvent;
use ApiPerformanceAnalyzer\Models\AlertRule;
use ApiPerformanceAnalyzer\Repositories\MetricsRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Collection;

/**
 * Evaluates enabled alert rules over their window, with per-rule dedup + cooldown.
 * Mirrors a simple threshold rule engine: when an endpoint metric crosses the
 * configured threshold, fire the configured channels and stamp last_triggered_at.
 */
class AlertEvaluator
{
    public function __construct(
        protected Config $config,
        protected MetricsRepository $metrics,
        protected AlertNotifier $notifier,
    ) {}

    /** @return Collection<int, AlertEvent> events fired this run */
    public function evaluate(): Collection
    {
        $fired = collect();

        if (! (bool) $this->config->get('apa.alerts.enabled', false)) {
            return $fired;
        }

        AlertRule::query()->where('enabled', true)->get()->each(function (AlertRule $rule) use ($fired) {
            if ($rule->inCooldown()) {
                return;
            }

            $observed = $this->observe($rule);
            if ($observed === null) {
                return;
            }

            if ($this->crosses($observed, $rule->operator, $rule->threshold)) {
                $event = $this->fire($rule, $observed);
                $fired->push($event);
            }
        });

        return $fired;
    }

    protected function observe(AlertRule $rule): ?float
    {
        $from = now()->subMinutes($rule->window_minutes)->toDateTimeString();
        $filters = ['from' => $from];
        if ($rule->uri) {
            $filters['uri'] = $rule->uri;
        }

        $overview = $this->metrics->overview($filters);

        return match ($rule->metric) {
            'p95_ms' => (float) $overview['p95_ms'],
            'error_rate' => (float) $overview['error_rate'],
            'n_plus_one_count' => (float) $overview['n_plus_one_count'],
            'query_count', 'avg_queries' => (float) $overview['avg_queries'],
            default => null,
        };
    }

    protected function crosses(float $observed, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $observed > $threshold,
            '>=' => $observed >= $threshold,
            '<' => $observed < $threshold,
            '<=' => $observed <= $threshold,
            default => false,
        };
    }

    protected function fire(AlertRule $rule, float $observed): AlertEvent
    {
        $event = AlertEvent::create([
            'rule_id' => $rule->id,
            'uri' => $rule->uri,
            'metric' => $rule->metric,
            'observed_value' => $observed,
            'threshold' => $rule->threshold,
            'context' => ['window_minutes' => $rule->window_minutes],
            'created_at' => now(),
        ]);

        $rule->forceFill(['last_triggered_at' => now()])->save();

        $this->notifier->send($rule, $event);

        return $event;
    }
}
