<?php

namespace ApiPerformanceAnalyzer\Models;

class AlertRule extends ApaModel
{
    protected $table = 'apa_alert_rules';

    protected $guarded = [];

    protected $casts = [
        'threshold' => 'float',
        'window_minutes' => 'int',
        'cooldown_minutes' => 'int',
        'channels' => 'array',
        'enabled' => 'bool',
        'last_triggered_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(AlertEvent::class, 'rule_id');
    }

    public function inCooldown(): bool
    {
        return $this->last_triggered_at !== null
            && $this->last_triggered_at->gt(now()->subMinutes($this->cooldown_minutes));
    }
}
