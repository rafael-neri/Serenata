<?php

namespace Serenata\Indexing\Visiting;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeTraverser;

/**
 * Visitor that causes the body of {@see Node\FunctionLike} nodes to be skipped.
 *
 * This can be used to improve performance or to simplify logic of other visitors.
 */
final class FunctionLikeBodySkippingVisitor implements NodeVisitor
{
    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\FunctionLike) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        return null;
    }
}
