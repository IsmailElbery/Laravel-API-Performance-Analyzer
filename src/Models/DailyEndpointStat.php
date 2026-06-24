<?php

namespace ApiPerformanceAnalyzer\Models;

class DailyEndpointStat extends ApaModel
{
    protected $table = 'apa_daily_endpoint_stats';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'count' => 'int',
        'avg_ms' => 'float',
        'p95_ms' => 'float',
        'error_rate' => 'float',
        'avg_queries' => 'float',
        'n_plus_one_count' => 'int',
        'health_score' => 'float',
    ];
}
