<?php

namespace ApiPerformanceAnalyzer\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RequestProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'method' => $this->method,
            'route_name' => $this->route_name,
            'uri' => $this->uri,
            'raw_uri' => $this->raw_uri,
            'status_code' => $this->status_code,
            'duration_ms' => $this->duration_ms,
            'db_query_count' => $this->db_query_count,
            'db_time_ms' => $this->db_time_ms,
            'peak_memory_kb' => $this->peak_memory_kb,
            'is_slow' => $this->is_slow,
            'has_n_plus_one' => $this->has_n_plus_one,
            'external_call_count' => $this->external_call_count,
            'external_time_ms' => $this->external_time_ms,
            'user_id' => $this->user_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'queries' => $this->whenLoaded('queries', fn () => $this->queries->map(fn ($q) => [
                'sql' => $q->sql,
                'sql_hash' => $q->sql_hash,
                'bindings_count' => $q->bindings_count,
                'time_ms' => $q->time_ms,
                'connection' => $q->connection,
                'is_slow' => $q->is_slow,
            ])),
            'http_calls' => $this->whenLoaded('httpCalls', fn () => $this->httpCalls->map(fn ($h) => [
                'host' => $h->host,
                'method' => $h->method,
                'url' => $h->url,
                'status_code' => $h->status_code,
                'duration_ms' => $h->duration_ms,
            ])),
        ];
    }
}
