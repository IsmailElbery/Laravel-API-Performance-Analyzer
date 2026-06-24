<?php

namespace ApiPerformanceAnalyzer\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model. Resolves the configured profiler connection at runtime so all APA
 * models read/write the isolated profiler DB when one is configured.
 */
abstract class ApaModel extends Model
{
    public function getConnectionName()
    {
        return config('apa.storage.connection') ?? parent::getConnectionName();
    }
}
