<?php

namespace PhpIntegrator\Linting;

use PhpIntegrator\Utility\SourceCodeHelpers;

use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ErrorHandler;
use PhpParser\NodeTraverser;

/**
 * Lints a file syntactically as well as semantically to indicate various problems with its contents.
 */
class Linter
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var DocblockCorrectnessAnalyzerFactory
     */
    protected $docblockCorrectnessAnalyzerFactory;

    /**
     * @var UnknownClassAnalyzerFactory
     */
    protected $unknownClassAnalyzerFactory;

    /**
     * @var UnknownGlobalConstantAnalyzerFactory
     */
    protected $unknownGlobalConstantAnalyzerFactory;

    /**
     * @var UnknownGlobalFunctionAnalyzerFactory
     */
    protected $unknownGlobalFunctionAnalyzerFactory;

    /**
     * @var UnknownMemberAnalyzerFactory
     */
    protected $unknownMemberAnalyzerFactory;

    /**
     * @var UnusedUseStatementAnalyzerFactory
     */
    protected $unusedUseStatementAnalyzerFactory;

    /**
     * @param Parser                               $parser
     * @param DocblockCorrectnessAnalyzerFactory   $docblockCorrectnessAnalyzerFactory
     * @param UnknownClassAnalyzerFactory          $unknownClassAnalyzerFactory
     * @param UnknownGlobalConstantAnalyzerFactory $unknownGlobalConstantAnalyzerFactory
     * @param UnknownGlobalFunctionAnalyzerFactory $unknownGlobalFunctionAnalyzerFactory
     * @param UnknownMemberAnalyzerFactory         $unknownMemberAnalyzerFactory
     * @param UnusedUseStatementAnalyzerFactory    $unusedUseStatementAnalyzerFactory
     */
    public function __construct(
        Parser $parser,
        DocblockCorrectnessAnalyzerFactory $docblockCorrectnessAnalyzerFactory,
        UnknownClassAnalyzerFactory $unknownClassAnalyzerFactory,
        UnknownGlobalConstantAnalyzerFactory $unknownGlobalConstantAnalyzerFactory,
        UnknownGlobalFunctionAnalyzerFactory $unknownGlobalFunctionAnalyzerFactory,
        UnknownMemberAnalyzerFactory $unknownMemberAnalyzerFactory,
        UnusedUseStatementAnalyzerFactory $unusedUseStatementAnalyzerFactory
    ) {
        $this->parser = $parser;
        $this->docblockCorrectnessAnalyzerFactory = $docblockCorrectnessAnalyzerFactory;
        $this->unknownClassAnalyzerFactory = $unknownClassAnalyzerFactory;
        $this->unknownGlobalConstantAnalyzerFactory = $unknownGlobalConstantAnalyzerFactory;
        $this->unknownGlobalFunctionAnalyzerFactory = $unknownGlobalFunctionAnalyzerFactory;
        $this->unknownMemberAnalyzerFactory = $unknownMemberAnalyzerFactory;
        $this->unusedUseStatementAnalyzerFactory = $unusedUseStatementAnalyzerFactory;
    }

    /**
     * @param string          $file
     * @param string          $code
     * @param LintingSettings $settings
     *
     * @return array
     */
    public function lint(string $file, string $code, LintingSettings $settings): array
    {
        // Parse the file to fetch the information we need.
        $nodes = [];
        $parser = $this->parser;

        $handler = new ErrorHandler\Collecting();

        $nodes = $parser->parse($code, $handler);

        $output = [
            'errors'   => [],
            'warnings' => []
        ];

        foreach ($handler->getErrors() as $e) {
            $startLine = $e->getStartLine() >= 0 ? ($e->getStartLine() - 1) : 0;
            $endLine   = $e->getEndLine() >= 0 ? ($e->getEndLine() - 1) : 0;

            $startColumn = $e->hasColumnInfo() ? ($e->getStartColumn($code) - 1) : 0;
            $endColumn   = $e->hasColumnInfo() ? ($e->getEndColumn($code) - 1) : 0;

            $output['errors'][] = [
                'message'     => $e->getMessage(),
                'start'       => SourceCodeHelpers::calculateOffsetByLineCharacter($code, $startLine, $startColumn),
                'end'         => SourceCodeHelpers::calculateOffsetByLineCharacter($code, $endLine, $endColumn)
            ];
        }

        if ($nodes === null) {
            return $output;
        }

        $traverser = new NodeTraverser();
        $analyzers = $this->getAnalyzersForRequest($file, $code, $settings);

        foreach ($analyzers as $analyzer) {
            foreach ($analyzer->getVisitors() as $visitor) {
                $traverser->addVisitor($visitor);
            }
        }

        try {
            $traverser->traverse($nodes);
        } catch (Error $e) {
            $output['errors'][] = [
                'message' => "Something is semantically wrong. Is there perhaps a duplicate use statement?",
                'start'   => 0,
                'end'     => 0
            ];

            return $output;
        }

        foreach ($analyzers as $analyzer) {
            $output['errors']   = array_merge($output['errors'], $analyzer->getErrors());
            $output['warnings'] = array_merge($output['warnings'], $analyzer->getWarnings());
        }

        return $output;
    }

    /**
     * @param string          $file
     * @param string          $code
     * @param LintingSettings $settings
     *
     * @return AnalyzerInterface[]
     */
    protected function getAnalyzersForRequest(string $file, string $code, LintingSettings $settings): array
    {
        /** @var AnalyzerInterface[] $analyzers */
        $analyzers = [];

        if ($settings->getLintUnknownClasses()) {
            $analyzers[] = $this->unknownClassAnalyzerFactory->create($file);
        }

        if ($settings->getLintUnknownMembers()) {
            $analyzers[] = $this->unknownMemberAnalyzerFactory->create($file, $code);
        }

        if ($settings->getLintUnusedUseStatements()) {
            $analyzers[] = $this->unusedUseStatementAnalyzerFactory->create();
        }

        if ($settings->getLintDocblockCorrectness()) {
            $analyzers[] = $this->docblockCorrectnessAnalyzerFactory->create($file, $code);
        }

        if ($settings->getLintUnknownGlobalConstants()) {
            $analyzers[] = $this->unknownGlobalConstantAnalyzerFactory->create();
        }

        if ($settings->getLintUnknownGlobalFunctions()) {
            $analyzers[] = $this->unknownGlobalFunctionAnalyzerFactory->create();
        }

        return $analyzers;
    }
}