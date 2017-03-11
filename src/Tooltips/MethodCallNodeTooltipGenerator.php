<?php

namespace PhpIntegrator\Tooltips;

use UnexpectedValueException;

use PhpIntegrator\Analysis\Node\MethodCallMethodInfoRetriever;

use PhpParser\Node;

/**
 * Provides tooltips for {@see Node\Expr\MethodCall} nodes.
 */
class MethodCallNodeTooltipGenerator
{
    /**
     * @var MethodCallMethodInfoRetriever
     */
    protected $methodCallMethodInfoRetriever;

    /**
     * @var FunctionTooltipGenerator
     */
    protected $functionTooltipGenerator;

    /**
     * @param MethodCallMethodInfoRetriever $methodCallMethodInfoRetriever
     * @param FunctionTooltipGenerator      $functionTooltipGenerator
     */
    public function __construct(
        MethodCallMethodInfoRetriever $methodCallMethodInfoRetriever,
        FunctionTooltipGenerator $functionTooltipGenerator
    ) {
        $this->methodCallMethodInfoRetriever = $methodCallMethodInfoRetriever;
        $this->functionTooltipGenerator = $functionTooltipGenerator;
    }

    /**
     * @param Node\Expr\MethodCall $node
     * @param string               $file
     * @param string               $code
     * @param int                  $offset
     *
     * @throws UnexpectedValueException
     *
     * @return string
     */
    public function generate(Node\Expr\MethodCall $node, string $file, string $code, int $offset): string
    {
        $infoElements = $this->methodCallMethodInfoRetriever->retrieve($node, $file, $code, $offset);

        if (empty($infoElements)) {
            throw new UnexpectedValueException('No method call information was found for node');
        }

        // Fetch the first tooltip. In theory, multiple tooltips are possible, but we don't support these at the moment.
        $info = array_shift($infoElements);

        return $this->functionTooltipGenerator->generate($info);
    }
}
