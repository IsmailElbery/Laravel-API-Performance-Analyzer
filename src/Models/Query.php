<?php

namespace ApiPerformanceAnalyzer\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Query extends ApaModel
{
    protected $table = 'apa_queries';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'bindings' => 'array',
        'bindings_count' => 'int',
        'time_ms' => 'float',
        'is_slow' => 'bool',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RequestProfile::class, 'profile_id');
    }
}
