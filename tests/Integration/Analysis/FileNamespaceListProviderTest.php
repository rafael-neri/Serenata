<?php

namespace Serenata\Tests\Integration\Analysis;

use Serenata\Common\Range;
use Serenata\Common\Position;

use Serenata\Tests\Integration\AbstractIntegrationTest;

final class FileNamespaceListProviderTest extends AbstractIntegrationTest
{
    /**
     * @return void
     */
    public function testNamespaceListForSpecificFile(): void
    {
        $path = 'file://' . __DIR__ . '/FileNamespaceListProviderTest/';
        $normalized = $this->normalizePath($path . 'NamespaceA.phpt');

        $this->indexTestFile($this->container, $path);

        $file = $this->container->get('storage')->getFileByUri($path . 'NamespaceA.phpt');

        $output = $this->container->get('fileNamespaceListProvider')->getAllForFile($file);

        static::assertCount(2, $output);

        $firstItem = array_shift($output);

        static::assertSame(null, $firstItem['name']);
        static::assertSame($normalized, $firstItem['uri']);

        static::assertEquals(
            new Range(
                new Position(0, 0),
                new Position(2, 0)
            ),
            $firstItem['range']
        );

        $secondItem = array_shift($output);

        static::assertSame('NamespaceA', $secondItem['name']);
        static::assertSame($normalized, $secondItem['uri']);

        static::assertEquals(
            new Range(
                new Position(2, 0),
                new Position(9, 0)
            ),
            $secondItem['range']
        );
    }
}
