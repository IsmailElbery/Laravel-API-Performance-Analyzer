<?php

namespace ApiPerformanceAnalyzer\Storage;

use ApiPerformanceAnalyzer\Models\HttpCall;
use ApiPerformanceAnalyzer\Models\Query;
use ApiPerformanceAnalyzer\Models\RequestProfile;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * The single place that turns profile arrays into rows. Used by every storage
 * driver (sync inline, queued job, batch flush) so the persistence contract is
 * identical regardless of timing. Children join on the parent's auto id, which
 * is why even the batch driver inserts parents first, then children.
 */
class ProfileWriter
{
    public function __construct(
        protected Config $config,
        protected ConnectionResolverInterface $db,
    ) {}

    /**
     * Persist one profile and (when present) its child rows in a transaction.
     */
    public function write(array $profile, array $children): void
    {
        $connection = $this->config->get('apa.storage.connection');

        $this->db->connection($connection)->transaction(function () use ($profile, $children, $connection) {
            $profileId = $this->db->connection($connection)
                ->table((new RequestProfile)->getTable())
                ->insertGetId($this->castBooleans($profile));

            $this->insertChildren($connection, $profileId, $children);
        });
    }

    /**
     * Bulk-write many profiles (batch driver). Parents are inserted one-by-one
     * to obtain ids for FK linkage, but children are chunk-bulk-inserted.
     *
     * @param  array<int, array{profile: array, children: array}>  $items
     */
    public function writeMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        $connection = $this->config->get('apa.storage.connection');
        $conn = $this->db->connection($connection);

        $conn->transaction(function () use ($conn, $connection, $items) {
            $profileTable = (new RequestProfile)->getTable();
            $allQueries = [];
            $allHttp = [];

            foreach ($items as $item) {
                $profileId = $conn->table($profileTable)
                    ->insertGetId($this->castBooleans($item['profile']));

                foreach ($item['children']['queries'] ?? [] as $q) {
                    $allQueries[] = $this->queryRow($profileId, $q);
                }
                foreach ($item['children']['http_calls'] ?? [] as $h) {
                    $allHttp[] = $this->httpRow($profileId, $h);
                }
            }

            if ($allQueries !== []) {
                foreach (array_chunk($allQueries, 500) as $chunk) {
                    $conn->table((new Query)->getTable())->insert($chunk);
                }
            }
            if ($allHttp !== []) {
                foreach (array_chunk($allHttp, 500) as $chunk) {
                    $conn->table((new HttpCall)->getTable())->insert($chunk);
                }
            }
        });
    }

    protected function insertChildren(?string $connection, int $profileId, array $children): void
    {
        $conn = $this->db->connection($connection);

        $queries = array_map(
            fn ($q) => $this->queryRow($profileId, $q),
            $children['queries'] ?? []
        );
        if ($queries !== []) {
            $conn->table((new Query)->getTable())->insert($queries);
        }

        $http = array_map(
            fn ($h) => $this->httpRow($profileId, $h),
            $children['http_calls'] ?? []
        );
        if ($http !== []) {
            $conn->table((new HttpCall)->getTable())->insert($http);
        }
    }

    protected function queryRow(int $profileId, array $q): array
    {
        return [
            'profile_id' => $profileId,
            'sql_hash' => $q['sql_hash'] ?? null,
            'sql' => $q['sql'] ?? null,
            'bindings_count' => $q['bindings_count'] ?? 0,
            'bindings' => isset($q['bindings']) && $q['bindings'] !== null
                ? json_encode($q['bindings'])
                : null,
            'time_ms' => $q['time_ms'] ?? 0,
            'connection' => $q['connection'] ?? null,
            'is_slow' => ! empty($q['is_slow']),
        ];
    }

    protected function httpRow(int $profileId, array $h): array
    {
        return [
            'profile_id' => $profileId,
            'host' => $h['host'] ?? null,
            'method' => $h['method'] ?? null,
            'url' => $h['url'] ?? null,
            'status_code' => $h['status_code'] ?? null,
            'duration_ms' => $h['duration_ms'] ?? 0,
        ];
    }

    /** Normalize PHP bools to ints for portable inserts across MySQL/Postgres. */
    protected function castBooleans(array $row): array
    {
        foreach (['is_slow', 'sampled', 'has_n_plus_one'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = (int) (bool) $row[$key];
            }
        }

        return $row;
    }
}
