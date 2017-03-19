<?php

namespace PhpIntegrator\Linting;

use PhpIntegrator\Analysis\ClasslikeInfoBuilder;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Analysis\Typing\Deduction\NodeTypeDeducerInterface;

/**
 * Factory that produces instances of {@see UnknownMemberAnalyzer}.
 */
class UnknownMemberAnalyzerFactory
{
    /**
     * @var NodeTypeDeducerInterface
     */
    protected $nodeTypeDeducer;

    /**
     * @var ClasslikeInfoBuilder
     */
    protected $classlikeInfoBuilder;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param NodeTypeDeducerInterface $nodeTypeDeducer
     * @param ClasslikeInfoBuilder     $classlikeInfoBuilder
     * @param TypeAnalyzer             $typeAnalyzer
     */
    public function __construct(
        NodeTypeDeducerInterface $nodeTypeDeducer,
        ClasslikeInfoBuilder $classlikeInfoBuilder,
        TypeAnalyzer $typeAnalyzer
    ) {
        $this->nodeTypeDeducer = $nodeTypeDeducer;
        $this->classlikeInfoBuilder = $classlikeInfoBuilder;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * @param string $file
     * @param string $code
     *
     * @return UnknownMemberAnalyzer
     */
    public function create(string $file, string $code): UnknownMemberAnalyzer
    {
        return new UnknownMemberAnalyzer(
            $this->nodeTypeDeducer,
            $this->classlikeInfoBuilder,
            $this->typeAnalyzer,
            $file,
            $code
        );
    }
}