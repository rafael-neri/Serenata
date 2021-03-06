<?php

namespace Serenata\Tests\Integration\Autocompletion\Providers;

use Serenata\Autocompletion\CompletionItemKind;
use Serenata\Autocompletion\CompletionItem;

use Serenata\Common\Range;
use Serenata\Common\Position;

use Serenata\Indexing\Structures\ClasslikeTypeNameValue;

use Serenata\Utility\TextEdit;

final class ClassAutocompletionProviderTest extends AbstractAutocompletionProviderTest
{
    /**
     * @return void
     */
    public function testRetrievesAllClasslikes(): void
    {
        $output = $this->provide('Class.phpt');

        $suggestions = [
            new CompletionItem(
                '\Foo',
                CompletionItemKind::CLASS_,
                'Foo',
                new TextEdit(
                    new Range(new Position(7, 0), new Position(7, 1)),
                    'Foo'
                ),
                'Foo',
                null,
                [],
                false,
                ClasslikeTypeNameValue::CLASS_ . ' — Foo'
            ),
        ];

        self::assertEquals($suggestions, $output);
    }

    /**
     * @return void
     */
    public function testMarksDeprecatedClasslikeAsDeprecated(): void
    {
        $output = $this->provide('DeprecatedClass.phpt');

        $suggestions = [
            new CompletionItem(
                '\Foo',
                CompletionItemKind::CLASS_,
                'Foo',
                new TextEdit(
                    new Range(new Position(10, 0), new Position(10, 1)),
                    'Foo'
                ),
                'Foo',
                null,
                [],
                true,
                ClasslikeTypeNameValue::CLASS_ . ' — Foo'
            ),
        ];

        self::assertEquals($suggestions, $output);
    }

    /**
     * @return void
     */
    public function testSuggestsFullyQualifiedNameIfPrefixStartsWithSlash(): void
    {
        $output = $this->provide('PrefixWithSlash.phpt');

        $suggestions = [
            new CompletionItem(
                '\Foo',
                CompletionItemKind::CLASS_,
                '\\\\Foo',
                new TextEdit(
                    new Range(new Position(7, 0), new Position(7, 2)),
                    '\\\\Foo'
                ),
                'Foo',
                null,
                [],
                false,
                ClasslikeTypeNameValue::CLASS_ . ' — Foo'
            ),
        ];

        self::assertEquals($suggestions, $output);
    }

    /**
     * @return void
     */
    public function testIncludesUseStatementImportInSuggestion(): void
    {
        $output = $this->provide('NamespacedClass.phpt');

        $suggestions = [
            new CompletionItem(
                '\Foo\Bar\Baz',
                CompletionItemKind::CLASS_,
                'Baz',
                new TextEdit(
                    new Range(new Position(10, 4), new Position(10, 5)),
                    'Baz'
                ),
                'Baz',
                null,
                [
                    new TextEdit(
                        new Range(new Position(10, 0), new Position(10, 0)),
                        "use Foo\Bar\Baz;\n\n"
                    ),
                ],
                false,
                ClasslikeTypeNameValue::CLASS_ . ' — Foo\Bar\Baz'
            ),
        ];

        self::assertEquals($suggestions, $output);
    }

    /**
     * @return void
     */
    public function testSkipsUseStatementImportWhenAutocompletingUseStatement(): void
    {
        $output = $this->provide('UseStatement.phpt');

        $suggestions = [
            new CompletionItem(
                '\Foo\Bar\Baz\Qux',
                CompletionItemKind::CLASS_,
                'Foo\\\\Bar\\\\Baz\\\\Qux',
                new TextEdit(
                    new Range(new Position(10, 8), new Position(10, 15)),
                    'Foo\\\\Bar\\\\Baz\\\\Qux'
                ),
                'Qux',
                null,
                [],
                false,
                ClasslikeTypeNameValue::CLASS_ . ' — Foo\Bar\Baz\Qux'
            ),
        ];

        self::assertEquals($suggestions, $output);
    }

    /**
     * @inheritDoc
     */
    protected function getFolderName(): string
    {
        return 'ClasslikeAutocompletionProviderTest';
    }

    /**
     * @inheritDoc
     */
    protected function getProviderName(): string
    {
        return 'classAutocompletionProvider';
    }
}
