<?php

namespace Serenata\Tests\Performance;

/**
 * @group Performance
 */
class AutocompletionPerformanceTest extends AbstractPerformanceTest
{
    /**
     * @return void
     */
    public function testProvideAllFromStubs(): void
    {
        $pathToIndex = __DIR__ . '/../../vendor/jetbrains/phpstorm-stubs';
        $dummyDatabasePath = $this->getOutputDirectory() . '/test-stubs.sqlite';

        @unlink($dummyDatabasePath);

        $this->container->get('managerRegistry')->setDatabasePath($dummyDatabasePath);
        $this->container->get('initializeCommand')->initialize(
            $this->mockJsonRpcResponseSenderInterface(),
            false
        );

        $this->indexPath($this->container, $pathToIndex);

        $testFilePath = $pathToIndex . '/Core/Core_d.php';
        $code = $this->container->get('sourceCodeStreamReader')->getSourceCodeFromFile($testFilePath);

        $positionThatWillGenerateNonEmptyPrefix = mb_strpos($code, "define ('E_ERROR', 1);");

        // Empty prefixes are a specially optimized case that we don't want to trigger to have more realistic results.
        static::assertTrue(
            $positionThatWillGenerateNonEmptyPrefix !== false,
            'No location found that would generate a non-empty prefix'
        );

        $positionThatWillGenerateNonEmptyPrefix += mb_strlen('d');

        $time = $this->time(function () use ($testFilePath, $code, $positionThatWillGenerateNonEmptyPrefix) {
            $suggestions = $this->container->get('autocompletionProvider')->provide(
                $this->container->get('storage')->getFileByUri($testFilePath),
                $code,
                $positionThatWillGenerateNonEmptyPrefix
            );
        });

        unlink($dummyDatabasePath);

        $this->finish($time);
    }
}
