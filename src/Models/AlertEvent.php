<?php

namespace ApiPerformanceAnalyzer\Models;

class AlertEvent extends ApaModel
{
    protected $table = 'apa_alert_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'observed_value' => 'float',
        'threshold' => 'float',
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
