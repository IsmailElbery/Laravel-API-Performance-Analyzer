<?php

namespace ApiPerformanceAnalyzer\Detection;

use ApiPerformanceAnalyzer\Support\ProfileContext;

/**
 * Within a single request, groups queries by normalized sql_hash; if the same
 * statement runs more than the threshold (differing only in bindings), the
 * profile is flagged and the offending statements recorded. Because sql_hash is
 * also stored on retained child rows, the same pattern can later be surfaced in
 * aggregate across requests (MetricsRepository) — cheap, since the hash exists.
 */
class NPlusOneDetector
{
    public function __construct(protected int $threshold = 5) {}

    public function inspect(ProfileContext $context): void
    {
        if ($context->queries === []) {
            return;
        }

        $groups = [];
        foreach ($context->queries as $q) {
            $hash = $q['sql_hash'] ?? null;
            if ($hash === null) {
                continue;
            }
            if (! isset($groups[$hash])) {
                $groups[$hash] = ['sql' => $q['sql'] ?? '', 'count' => 0];
            }
            $groups[$hash]['count']++;
        }

        $suspects = [];
        foreach ($groups as $hash => $g) {
            if ($g['count'] > $this->threshold) {
                $suspects[$hash] = $g;
            }
        }

        if ($suspects !== []) {
            $context->hasNPlusOne = true;
            $context->nPlusOneSuspects = $suspects;
        }
    }
}
