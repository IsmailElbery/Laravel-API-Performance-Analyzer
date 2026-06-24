<?php

namespace ApiPerformanceAnalyzer\Models;

class IndexRecommendation extends ApaModel
{
    protected $table = 'apa_index_recommendations';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'frequency' => 'int',
        'avg_time_ms' => 'float',
        'endpoints' => 'array',
        'created_at' => 'datetime',
    ];
}
