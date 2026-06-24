<?php

namespace ApiPerformanceAnalyzer\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 */
class RequestProfile extends ApaModel
{
    protected $table = 'apa_request_profiles';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'duration_ms' => 'float',
        'db_time_ms' => 'float',
        'external_time_ms' => 'float',
        'db_query_count' => 'int',
        'peak_memory_kb' => 'int',
        'external_call_count' => 'int',
        'is_slow' => 'bool',
        'sampled' => 'bool',
        'has_n_plus_one' => 'bool',
        'created_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class, 'profile_id');
    }

    public function httpCalls(): HasMany
    {
        return $this->hasMany(HttpCall::class, 'profile_id');
    }
}
