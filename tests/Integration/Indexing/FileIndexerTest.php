<?php

namespace PhpIntegrator\Tests\Integration\Tooltips;

use PhpIntegrator\Tests\Integration\AbstractIntegrationTest;

use PhpParser\Node;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class FileIndexerTest extends AbstractIntegrationTest
{
    /**
     * @return void
     */
    public function testNewImportsAreInsertedOnReindex(): void
    {
        $afterIndex = function (ContainerBuilder $container, string $path, string $source) {
            $file = $container->get('storage')->findFileByPath($path);

            $this->assertCount(3, $file->getNamespaces());
            $this->assertEmpty($file->getNamespaces()[2]->getImports());

            return str_replace('// ', '', $source);
        };

        $afterReindex = function (ContainerBuilder $container, string $path, string $source) {
            $file = $container->get('storage')->findFileByPath($path);

            $this->assertCount(3, $file->getNamespaces());
            $this->assertCount(1, $file->getNamespaces()[2]->getImports());
        };

        $path = $this->getPathFor('NewImportsAreAdded.phpt');

        $this->assertReindexingChanges($path, $afterIndex, $afterReindex);
    }

    /**
     * @return void
     */
    public function testOldImportsAreRemovedOnReindex(): void
    {
        $afterIndex = function (ContainerBuilder $container, string $path, string $source) {
            $file = $container->get('storage')->findFileByPath($path);

            $this->assertCount(3, $file->getNamespaces());
            $this->assertCount(1, $file->getNamespaces()[2]->getImports());

            return str_replace('use N\A', '// use N\A', $source);
        };

        $afterReindex = function (ContainerBuilder $container, string $path, string $source) {
            $file = $container->get('storage')->findFileByPath($path);

            $this->assertCount(3, $file->getNamespaces());
            $this->assertEmpty($file->getNamespaces()[2]->getImports());
        };

        $path = $this->getPathFor('OldImportsAreRemoved.phpt');

        $this->assertReindexingChanges($path, $afterIndex, $afterReindex);
    }

    /**
     * @return void
     */
    public function testStaticMethodTypes(): void
    {
        $container = $this->index('StaticMethodTypes.phpt');

        $types = $container->get('metadataProvider')->getMetaStaticMethodTypesFor('\A\Foo', 'get');

        $this->assertCount(2, $types);

        $this->assertEquals(0, $types[0]->getArgumentIndex());
        $this->assertEquals('bar', $types[0]->getValue());
        $this->assertEquals(Node\Scalar\String_::class, $types[0]->getValueNodeType());
        $this->assertEquals('\B\Bar', $types[0]->getReturnType());

        $this->assertEquals(0, $types[1]->getArgumentIndex());
        $this->assertEquals('car', $types[1]->getValue());
        $this->assertEquals(Node\Scalar\String_::class, $types[1]->getValueNodeType());
        $this->assertEquals('\B\Car', $types[1]->getReturnType());
    }

    /**
     * @param string $file
     *
     * @return ContainerBuilder
     */
    protected function index(string $file): ContainerBuilder
    {
        $path = $this->getPathFor($file);

        $this->indexTestFile($this->container, $path);

        return $this->container;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    protected function getPathFor(string $file): string
    {
        return __DIR__ . '/FileIndexerTest/' . $file;
    }
}