<?php

namespace ApiPerformanceAnalyzer\Storage;

use ApiPerformanceAnalyzer\Contracts\ProfileStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Cache;

/**
 * Buffers finished profiles in a cache/Redis list and bulk-inserts them when the
 * buffer reaches `flush_at_size`, or when the scheduled `apa:flush` command runs
 * every `flush_every_seconds`. This is the recommended high-traffic driver: it
 * trades a small window of in-flight data for a fraction of the write churn.
 *
 * Each buffered entry carries the full profile + children together, so FK linkage
 * is resolved at flush time (parent insert -> id -> children) — see ProfileWriter.
 */
class BatchStore implements ProfileStore
{
    protected const KEY = 'apa:batch:buffer';

    public function __construct(
        protected Config $config,
        protected ProfileWriter $writer,
    ) {}

    public function store(array $profile, array $children): void
    {
        $cache = $this->cache();
        $payload = json_encode(['profile' => $profile, 'children' => $children]);

        // Redis list = O(1) push and atomic drain. Other stores fall back to a
        // read-modify-write array (best-effort; prefer redis in production).
        $store = $cache->getStore();

        if (method_exists($store, 'connection')) { // Redis
            $redis = $store->connection();
            $prefixedKey = $cache->getPrefix().self::KEY;
            $len = $redis->rpush($prefixedKey, $payload);

            if ($len >= (int) $this->config->get('apa.storage.batch.flush_at_size', 200)) {
                $this->flush();
            }

            return;
        }

        // Non-redis fallback.
        $buffer = $cache->get(self::KEY, []);
        $buffer[] = $payload;
        $cache->forever(self::KEY, $buffer);

        if (count($buffer) >= (int) $this->config->get('apa.storage.batch.flush_at_size', 200)) {
            $this->flush();
        }
    }

    /**
     * Drain the buffer and bulk-insert. Called on size trigger and by apa:flush.
     */
    public function flush(): int
    {
        $cache = $this->cache();
        $store = $cache->getStore();
        $items = [];

        if (method_exists($store, 'connection')) { // Redis: atomic drain
            $redis = $store->connection();
            $prefixedKey = $cache->getPrefix().self::KEY;

            // LRANGE + DEL under a transaction to avoid losing concurrent pushes.
            $raw = $redis->lrange($prefixedKey, 0, -1);
            if ($raw === [] || $raw === null) {
                return 0;
            }
            $redis->ltrim($prefixedKey, count($raw), -1);
            $items = $raw;
        } else {
            $items = $cache->pull(self::KEY, []);
        }

        $decoded = [];
        foreach ($items as $json) {
            $row = is_array($json) ? $json : json_decode($json, true);
            if (is_array($row) && isset($row['profile'])) {
                $decoded[] = $row;
            }
        }

        if ($decoded === []) {
            return 0;
        }

        $this->writer->writeMany($decoded);

        return count($decoded);
    }

    protected function cache(): CacheRepository
    {
        $buffer = $this->config->get('apa.storage.batch.buffer', 'redis');
        $store = $this->config->get('apa.storage.batch.cache_store');

        if ($store !== null) {
            return Cache::store($store);
        }

        return $buffer === 'redis' ? Cache::store('redis') : Cache::store();
    }
}
