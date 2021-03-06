<?php

namespace Serenata\Autocompletion\Providers;

use PhpParser\Node;

use Serenata\Analysis\NodeAtOffsetLocatorInterface;

use Serenata\Autocompletion\CompletionItemKind;
use Serenata\Autocompletion\CompletionItem;
use Serenata\Autocompletion\SnippetInsertionTextEscaper;

use Serenata\Common\Position;

use Serenata\Utility\TextEdit;

/**
 * Provides parameter name autocompletion suggestions at a specific location in a file.
 */
final class ParameterNameAutocompletionProvider implements AutocompletionProviderInterface
{
    /**
     * @var NodeAtOffsetLocatorInterface
     */
    private $nodeAtOffsetLocator;

    /**
     * @param NodeAtOffsetLocatorInterface $nodeAtOffsetLocator
     */
    public function __construct(NodeAtOffsetLocatorInterface $nodeAtOffsetLocator)
    {
        $this->nodeAtOffsetLocator = $nodeAtOffsetLocator;
    }

    /**
     * @inheritDoc
     */
    public function provide(AutocompletionProviderContext $context): iterable
    {
        $paramNode = $this->findParamNode($context);

        if ($paramNode === null) {
            return [];
        }

        return $this->createSuggestionsForParamNode($paramNode, $context);
    }

    /**
     * @param Node\Param                    $node
     * @param AutocompletionProviderContext $context
     *
     * @return CompletionItem[]
     */
    private function createSuggestionsForParamNode(Node\Param $node, AutocompletionProviderContext $context): array
    {
        if ($node->type === null) {
            return [];
        }

        $typeName = $this->determineTypeNameOfNode($node->type);

        if ($typeName === null) {
            return [];
        }

        $suggestions = $this->generateSuggestionsForName($typeName, $context);

        $typeNameParts = array_filter(explode('\\', $typeName));

        if (count($typeNameParts) > 1) {
            $lastTypeNamePart = array_pop($typeNameParts);

            $suggestions = array_merge($suggestions, $this->generateSuggestionsForName($lastTypeNamePart, $context));
        }

        return $suggestions;
    }

    /**
     * @param string                        $name
     * @param AutocompletionProviderContext $context
     *
     * @return CompletionItem[]
     */
    private function generateSuggestionsForName(string $name, AutocompletionProviderContext $context): array
    {
        $suggestions = [];

        // "MyNamespace\Foo" -> "$myNamespaceFoo", "MyClass" -> "$myClass"
        $bestTypeNameApproximation = lcfirst(str_replace('\\', '', $name));

        $suggestions[] = $this->createSuggestion('$' . $bestTypeNameApproximation, $context);

        // "MyNamespace\FooInterface" -> "$myNamespaceFoo", "SomeTrait" -> "Some", "MyClass" -> "$my"
        $bestTypeNameApproximationWithoutLastWord = $this->generateNameWithoutLastWord($bestTypeNameApproximation);

        if ($bestTypeNameApproximationWithoutLastWord !== '') {
            $suggestions[] = $this->createSuggestion('$' . $bestTypeNameApproximationWithoutLastWord, $context);
        }

        return $suggestions;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function generateNameWithoutLastWord(string $name): string
    {
        $words = preg_split(
            '/([A-Z][^A-Z]+)/',
            $name,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
        );

        if ($words === false || count($words) === 0) {
            return '';
        }

        array_pop($words);

        return implode('', $words);
    }

    /**
     * @param Node $node
     *
     * @return string|null
     */
    private function determineTypeNameOfNode(Node $node): ?string
    {
        if ($node instanceof Node\NullableType) {
            return $this->determineTypeNameOfNode($node->type);
        } elseif ($node instanceof Node\Identifier) {
            return $node->toString();
        } elseif ($node instanceof Node\Name) {
            return $node->toString();
        }

        return null;
    }

    /**
     * @param string                        $name
     * @param AutocompletionProviderContext $context
     *
     * @return CompletionItem
     */
    private function createSuggestion(string $name, AutocompletionProviderContext $context): CompletionItem
    {
        return new CompletionItem(
            $name,
            CompletionItemKind::VARIABLE,
            SnippetInsertionTextEscaper::escape($name),
            $this->getTextEditForSuggestion($name, $context),
            mb_substr($name, 1),
            null,
            [],
            false
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
     * @param string                        $name
     * @param AutocompletionProviderContext $context
     *
     * @return TextEdit
     */
    private function getTextEditForSuggestion(string $name, AutocompletionProviderContext $context): TextEdit
    {
        return new TextEdit($context->getPrefixRange(), SnippetInsertionTextEscaper::escape($name));
    }

    /**
     * @param AutocompletionProviderContext $context
     *
     * @return Node\Param|null
     */
    private function findParamNode(AutocompletionProviderContext $context): ?Node\Param
    {
        $position = new Position(
            $context->getPosition()->getLine(),
            max($context->getPosition()->getCharacter() - 1, 0)
        );

        $nodeResult = $this->nodeAtOffsetLocator->locate($context->getTextDocumentItem(), $position);

        $node = $nodeResult->getNode();

        if ($node instanceof Node\Expr\Variable) {
            $parent = $node->getAttribute('parent', false);

            return $parent instanceof Node\Param ? $parent : null;
        }

        return $node instanceof Node\Param ? $node : null;
    }
}
