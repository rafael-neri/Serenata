<?php

namespace PhpIntegrator\Analysis;

use LogicException;

use PhpIntegrator\Utility\NodeHelpers;

use PhpParser\Node;

/**
 * Determines the FQSEN of a name used in a name node.
 */
class NameNodeFqsenDeterminer
{
    /**
     * @var ClasslikeExistanceChecker
     */
    protected $classLikeExistenceChecker;

    /**
     * @param ClasslikeExistanceChecker $classLikeExistenceChecker
     */
    public function __construct(ClasslikeExistanceChecker $classLikeExistenceChecker)
    {
        $this->classLikeExistenceChecker = $classLikeExistenceChecker;
    }

    /**
     * @param Node\Name $node
     *
     * @return string
     */
    public function determine(Node\Name $node): string
    {
        // False must be used rather than null as the namespace can actually be null.
        $namespaceNode = $node->getAttribute('namespace', false);

        if ($namespaceNode === false) {
            throw new LogicException('Namespace must be attached to node in order to determine FQSEN');
        }

        $namespace = null;

        if ($namespaceNode !== null) {
            $namespace = NodeHelpers::fetchClassName($namespaceNode);
        }

        if ($node->isFullyQualified()) {
            return NodeHelpers::fetchClassName($node);
        } elseif ($node->isQualified()) {
            return '\\' . $namespace . '\\' . $node->toString();
        }

        // Unqualified global function calls, such as "array_walk", could refer to "array_walk" in the current
        // namespace (e.g. "\A\array_walk") or, if not present in the current namespace, the root namespace
        // (e.g. "\array_walk").
        $fqcnForCurrentNamespace = '\\' . $namespace . '\\' . $node->toString();

        if ($this->classLikeExistenceChecker->doesClassExist($fqcnForCurrentNamespace)) {
            return $fqcnForCurrentNamespace;
        }

        return '\\' . $node->toString();
    }
}