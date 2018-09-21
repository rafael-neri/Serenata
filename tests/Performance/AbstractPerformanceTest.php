<?php

namespace Serenata\Tests\Performance;

use Closure;

use Serenata\Tests\Integration\AbstractIntegrationTest;

/**
 * @group Performance
 */
abstract class AbstractPerformanceTest extends AbstractIntegrationTest
{
    /**
     * @return string
     */
    protected function getOutputDirectory(): string
    {
        return 'file:///' . __DIR__ . '/Output';
    }

    /**
     * @param Closure $closure
     *
     * @return float
     */
    protected function time(Closure $closure): float
    {
        $time = microtime(true);

        $closure();

        return (microtime(true) - $time) * 1000;
    }

    /**
     * @param float $time
     *
     * @return void
     */
    protected function finish(float $time): void
    {
        $this->markTestSkipped("Took {$time} milliseconds (" . ($time / 1000) . " seconds)");
    }
}
