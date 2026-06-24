<?php

namespace ApiPerformanceAnalyzer\Tests\Unit;

use ApiPerformanceAnalyzer\Detection\NPlusOneDetector;
use ApiPerformanceAnalyzer\Support\ProfileContext;
use PHPUnit\Framework\TestCase;

class NPlusOneDetectorTest extends TestCase
{
    public function test_flags_repeated_statement_above_threshold(): void
    {
        $context = new ProfileContext;
        for ($i = 0; $i < 7; $i++) {
            $context->addQuery(['sql_hash' => 'abc', 'sql' => 'select * from posts where user_id = ?']);
        }
        $context->addQuery(['sql_hash' => 'other', 'sql' => 'select * from users']);

        (new NPlusOneDetector(5))->inspect($context);

        $this->assertTrue($context->hasNPlusOne);
        $this->assertArrayHasKey('abc', $context->nPlusOneSuspects);
        $this->assertSame(7, $context->nPlusOneSuspects['abc']['count']);
        $this->assertArrayNotHasKey('other', $context->nPlusOneSuspects);
    }

    public function test_does_not_flag_below_threshold(): void
    {
        $context = new ProfileContext;
        for ($i = 0; $i < 3; $i++) {
            $context->addQuery(['sql_hash' => 'abc', 'sql' => 'select 1']);
        }

        (new NPlusOneDetector(5))->inspect($context);

        $this->assertFalse($context->hasNPlusOne);
    }
}
