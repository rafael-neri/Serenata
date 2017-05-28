<?php

namespace PhpIntegrator\Indexing;

use DateTime;
use Exception;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Analysis\Typing\Deduction\NodeTypeDeducerInterface;

use PhpIntegrator\NameQualificationUtilities\StructureAwareNameResolverFactoryInterface;

use PhpIntegrator\Parsing\DocblockParser;

use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ErrorHandler;
use PhpParser\NodeTraverser;

/**
 * Handles indexation of PHP code in a single file.
 *
 * The index only contains "direct" data, meaning that it only contains data that is directly attached to an element.
 * For example, classes will only have their direct members attached in the index. The index will also keep track of
 * links between structural elements and parents, implemented interfaces, and more, but it will not duplicate data,
 * meaning parent methods will not be copied and attached to child classes.
 *
 * The index keeps track of 'outlines' that are confined to a single file. It in itself does not do anything
 * "intelligent" such as automatically inheriting docblocks from overridden methods.
 */
class FileIndexer implements FileIndexerInterface
{
    /**
     * The storage to use for index data.
     *
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var DocblockParser
     */
    private $docblockParser;

    /**
     * @var TypeAnalyzer
     */
    private $typeAnalyzer;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var NodeTypeDeducerInterface
     */
    private $nodeTypeDeducer;

    /**
     * @var array
     */
    private $accessModifierMap;

    /**
     * @var array
     */
    private $structureTypeMap;

    /**
     * @var StructureAwareNameResolverFactoryInterface
     */
    private $structureAwareNameResolverFactory;

    /**
     * @param StorageInterface                           $storage
     * @param TypeAnalyzer                               $typeAnalyzer
     * @param DocblockParser                             $docblockParser
     * @param NodeTypeDeducerInterface                   $nodeTypeDeducer
     * @param Parser                                     $parser
     * @param StructureAwareNameResolverFactoryInterface $structureAwareNameResolverFactory
     */
    public function __construct(
        StorageInterface $storage,
        TypeAnalyzer $typeAnalyzer,
        DocblockParser $docblockParser,
        NodeTypeDeducerInterface $nodeTypeDeducer,
        Parser $parser,
        StructureAwareNameResolverFactoryInterface $structureAwareNameResolverFactory
    ) {
        $this->storage = $storage;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->docblockParser = $docblockParser;
        $this->nodeTypeDeducer = $nodeTypeDeducer;
        $this->parser = $parser;
        $this->structureAwareNameResolverFactory = $structureAwareNameResolverFactory;
    }

    /**
     * @inheritDoc
     */
    public function index(string $filePath, string $code): void
    {
        $handler = new ErrorHandler\Collecting();

        try {
            $nodes = $this->parser->parse($code, $handler);

            if ($nodes === null) {
                throw new Error('Unknown syntax error encountered');
            }
        } catch (Error $e) {
            throw new IndexingFailedException($e->getMessage(), 0, $e);
        }

        $this->storage->beginTransaction();

        $file = $this->storage->findFileByPath($filePath);

        if ($file !== null) {
            $this->storage->delete($file);

            // TODO: Rewrite indexing to update the file instead of delete it in its entirety later on. Flushing to remove
            // should then be obsolete.
            $this->storage->commitTransaction();
            $this->storage->beginTransaction();
        }

        $file = new Structures\File($filePath, new DateTime(), []);

        $this->storage->persist($file);

        try {
            $traverser = $this->createTraverser($nodes, $filePath, $code, $file);
            $traverser->traverse($nodes);

            $this->storage->commitTransaction();
        } catch (Error $e) {
            $this->storage->rollbackTransaction();

            throw new IndexingFailedException($e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->storage->rollbackTransaction();

            throw $e;
        }
    }

    /**
     * @param array           $nodes
     * @param string          $filePath
     * @param string          $code
     * @param Structures\File $file
     *
     * @return NodeTraverser
     */
    protected function createTraverser(array $nodes, string $filePath, string $code, Structures\File $file): NodeTraverser
    {
        $visitors = $this->getVisitorsForFile($filePath, $code, $file);

        $useStatementIndexingVisitor = array_shift($visitors);

        // TODO: Refactor to traverse once.
        $this->storage->commitTransaction();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($useStatementIndexingVisitor);
        $traverser->traverse($nodes);
        $this->storage->beginTransaction();

        $traverser = new NodeTraverser();

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        return $traverser;
    }

    /**
     * @param string          $filePath
     * @param string          $code
     * @param Structures\File $file
     *
     * @return array
     */
    protected function getVisitorsForFile(string $filePath, string $code, Structures\File $file): array
    {
        $visitors = [
            new Visiting\UseStatementIndexingVisitor($this->storage, $file, $code),

            new Visiting\GlobalConstantIndexingVisitor(
                $this->storage,
                $this->docblockParser,
                $this->structureAwareNameResolverFactory,
                $this->typeAnalyzer,
                $this->nodeTypeDeducer,
                $file,
                $code,
                $filePath
            ),

            new Visiting\GlobalDefineIndexingVisitor(
                $this->storage,
                $this->nodeTypeDeducer,
                $file,
                $code,
                $filePath
            ),

            new Visiting\GlobalFunctionIndexingVisitor(
                $this->structureAwareNameResolverFactory,
                $this->storage,
                $this->docblockParser,
                $this->typeAnalyzer,
                $file,
                $code,
                $filePath
            ),

            new Visiting\ClasslikeIndexingVisitor(
                $this->storage,
                $this->typeAnalyzer,
                $this->docblockParser,
                $this->nodeTypeDeducer,
                $this->structureAwareNameResolverFactory,
                $file,
                $code,
                $filePath
            ),

            new Visiting\MetaFileIndexingVisitor(
                $this->storage,
                $file
            )
        ];

        return $visitors;
    }
}
