<?php

namespace ApiPerformanceAnalyzer\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HttpCall extends ApaModel
{
    protected $table = 'apa_http_calls';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status_code' => 'int',
        'duration_ms' => 'float',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RequestProfile::class, 'profile_id');
    }
}
