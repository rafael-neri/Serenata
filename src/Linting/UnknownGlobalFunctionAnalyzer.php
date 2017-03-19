<?php

namespace PhpIntegrator\Linting;

use PhpIntegrator\Analysis\GlobalFunctionExistenceCheckerInterface;

use PhpIntegrator\Analysis\Node\FunctionNameNodeFqsenDeterminer;

use PhpIntegrator\Analysis\Visiting\NamespaceAttachingVisitor;
use PhpIntegrator\Analysis\Visiting\GlobalFunctionUsageFetchingVisitor;

/**
 * Looks for unknown global function names (i.e. used during calls).
 */
class UnknownGlobalFunctionAnalyzer implements AnalyzerInterface
{
    /**
     * @var NamespaceAttachingVisitor
     */
    protected $namespaceAttachingVisitor;

    /**
     * @var GlobalFunctionUsageFetchingVisitor
     */
    protected $globalFunctionUsageFetchingVisitor;

    /**
     * @var GlobalFunctionExistenceCheckerInterface
     */
    protected $globalFunctionExistenceChecker;

    /**
     * @param GlobalFunctionExistenceCheckerInterface $globalFunctionExistenceChecker
     */
    public function __construct(GlobalFunctionExistenceCheckerInterface $globalFunctionExistenceChecker)
    {
        $this->globalFunctionExistenceChecker = $globalFunctionExistenceChecker;

        $this->namespaceAttachingVisitor = new NamespaceAttachingVisitor();
        $this->globalFunctionUsageFetchingVisitor = new GlobalFunctionUsageFetchingVisitor();
    }

    /**
     * @inheritDoc
     */
    public function getVisitors(): array
    {
        return [
            $this->namespaceAttachingVisitor,
            $this->globalFunctionUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        $globalFunctions = $this->globalFunctionUsageFetchingVisitor->getGlobalFunctionCallList();

        $unknownGlobalFunctions = [];

        // TODO: Inject this.
        $determiner = new FunctionNameNodeFqsenDeterminer($this->globalFunctionExistenceChecker);

        foreach ($globalFunctions as $node) {
            $fqsen = $determiner->determine($node->name);

            if ($this->globalFunctionExistenceChecker->exists($fqsen)) {
                continue;
            }

            $unknownGlobalFunctions[] = [
                'message' => "Function **{$fqsen}** is not defined or imported anywhere.",
                'start'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'     => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];
        }

        return $unknownGlobalFunctions;
    }

    /**
     * @inheritDoc
     */
    public function getWarnings(): array
    {
        return [];
    }
}