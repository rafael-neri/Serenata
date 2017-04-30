<?php

namespace PhpIntegrator\Indexing\Visiting;

use PhpIntegrator\Analysis\Visiting\UseStatementFetchingVisitor;

use PhpIntegrator\Indexing\StorageInterface;

use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Visitor that traverses a set of nodes and indexes use statements and namespaces in the process.
 */
class UseStatementIndexingVisitor implements NodeVisitor
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var int
     */
    private $fileId;

    /**
     * @var UseStatementFetchingVisitor
     */
    private $useStatementFetchingVisitor;

    /**
     * @param StorageInterface $storage
     * @param int              $fileId
     * @param string           $code
     */
    public function __construct(StorageInterface $storage, int $fileId, string $code)
    {
        $this->storage = $storage;
        $this->fileId = $fileId;

        $this->useStatementFetchingVisitor = new UseStatementFetchingVisitor($code);
    }

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        $this->useStatementFetchingVisitor->beforeTraverse($nodes);
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $this->useStatementFetchingVisitor->enterNode($node);
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        $this->useStatementFetchingVisitor->leaveNode($node);
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        $this->useStatementFetchingVisitor->afterTraverse($nodes);

        foreach ($this->useStatementFetchingVisitor->getNamespaces() as $namespace) {
            $this->indexNamespace($namespace);
        }
    }

    /**
     * @param array $namespace
     *
     * @return void
     */
    protected function indexNamespace(array $namespace): void
    {
        $namespaceId = $this->storage->insertNamespace([
            'start_line'  => $namespace['startLine'],
            'end_line'    => $namespace['endLine'],
            'namespace'   => $namespace['name'],
            'file_id'     => $this->fileId
        ]);

        foreach ($namespace['useStatements'] as $useStatement) {
            $this->indexUseStatement($useStatement, $namespaceId);
        }
    }

    /**
     * @param array $useStatement
     * @param int   $namespaceId
     *
     * @return void
     */
    protected function indexUseStatement(array $useStatement, int $namespaceId): void
    {
        $this->storage->insertImport([
            'line'               => $useStatement['line'],
            'alias'              => $useStatement['alias'] ?: null,
            'name'               => $useStatement['name'],
            'kind'               => $useStatement['kind'],
            'files_namespace_id' => $namespaceId
        ]);
    }
}