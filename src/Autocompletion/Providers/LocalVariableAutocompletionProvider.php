<?php

namespace Serenata\Autocompletion\Providers;

use UnexpectedValueException;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ErrorHandler;

use Serenata\Analysis\VariableScanner;

use Serenata\Autocompletion\CompletionItemKind;
use Serenata\Autocompletion\CompletionItem;
use Serenata\Autocompletion\SnippetInsertionTextEscaper;
use Serenata\Autocompletion\CompletionItemDetailFormatter;

use Serenata\Utility\TextEdit;

/**
 * Provides local variable autocompletion suggestions at a specific location in a file.
 */
final class LocalVariableAutocompletionProvider implements AutocompletionProviderInterface
{
    /**
     * @var VariableScanner
     */
    private $variableScanner;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var CompletionItemDetailFormatter
     */
    private $completionItemDetailFormatter;

    /**
     * @param VariableScanner                         $variableScanner
     * @param Parser                                  $parser
     * @param CompletionItemDetailFormatter   $completionItemDetailFormatter
     */
    public function __construct(
        VariableScanner $variableScanner,
        Parser $parser,
        CompletionItemDetailFormatter $completionItemDetailFormatter
    ) {
        $this->variableScanner = $variableScanner;
        $this->parser = $parser;
        $this->completionItemDetailFormatter = $completionItemDetailFormatter;
    }

    /**
     * @inheritDoc
     */
    public function provide(AutocompletionProviderContext $context): iterable
    {
        $handler = new ErrorHandler\Collecting();

        try {
            $nodes = $this->parse($context->getTextDocumentItem()->getText(), $handler);
        } catch (UnexpectedValueException $e) {
            return [];
        }

        $availableVariables = $this->variableScanner->getAvailableVariables(
            $context->getTextDocumentItem(),
            $context->getPosition()
        );

        foreach ($availableVariables as $variable) {
            yield $this->createSuggestion($variable, $context);
        }
    }

    /**
     * @param array<string,mixed>           $variable
     * @param AutocompletionProviderContext $context
     *
     * @return CompletionItem
     */
    private function createSuggestion(array $variable, AutocompletionProviderContext $context): CompletionItem
    {
        $typeArray = array_map(function (string $type): array {
            return [
                'type' => $type,
            ];
        }, explode('|', $variable['type']));

        return new CompletionItem(
            $variable['name'],
            CompletionItemKind::VARIABLE,
            SnippetInsertionTextEscaper::escape($variable['name']),
            $this->getTextEditForSuggestion($variable, $context),
            mb_substr($variable['name'], 1),
            null,
            [],
            false,
            $this->completionItemDetailFormatter->format(null, null, $typeArray)
        );
    }

    /**
     * Generate a {@see TextEdit} for the suggestion.
     *
     * Some clients automatically determine the prefix to replace on their end (e.g. Atom) and just paste the insertText
     * we send back over this prefix. This prefix sometimes differs from what we see as prefix as the namespace
     * separator (the backslash \) whilst these clients don't. Using a {@see TextEdit} rather than a simple insertText
     * ensures that the entire prefix is replaced along with the insertion.
     *
     * @param array<string,mixed>           $variable
     * @param AutocompletionProviderContext $context
     *
     * @return TextEdit
     */
    private function getTextEditForSuggestion(array $variable, AutocompletionProviderContext $context): TextEdit
    {
        return new TextEdit($context->getPrefixRange(), SnippetInsertionTextEscaper::escape($variable['name']));
    }

    /**
     * @param string            $code
     * @param ErrorHandler|null $errorHandler
     *
     * @throws UnexpectedValueException
     *
     * @return Node[]
     */
    private function parse(string $code, ?ErrorHandler $errorHandler = null): array
    {
        try {
            $nodes = $this->parser->parse($code, $errorHandler);
        } catch (Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        return $nodes;
    }
}
