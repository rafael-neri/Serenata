<?php

namespace PhpIntegrator\Tests\Integration\Autocompletion;

use PhpIntegrator\Autocompletion\SuggestionKind;
use PhpIntegrator\Autocompletion\AutocompletionSuggestion;

class NamespaceAutocompletionProviderTest extends AbstractAutocompletionProviderTest
{
    /**
     * @return void
     */
    public function testRetrievesAll(): void
    {
        $output = $this->provide('Namespace.phpt');

        $suggestions = [
            new AutocompletionSuggestion('Foo', SuggestionKind::IMPORT, 'Foo', null, 'Foo', null, [
                'isDeprecated' => false,
                'returnTypes'  => 'namespace'
            ])
        ];

        static::assertEquals($suggestions, $output);
    }

    /**
     * @return void
     */
    public function testOmitsAnonymousNamespaces(): void
    {
        $output = $this->provide('AnonymousNamespace.phpt');

        static::assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testDeduplicatesNames(): void
    {
        $output = $this->provide('Namespaces.phpt');

        $suggestions = [
            new AutocompletionSuggestion('Foo', SuggestionKind::IMPORT, 'Foo', null, 'Foo', null, [
                'isDeprecated' => false,
                'returnTypes'  => 'namespace'
            ])
        ];

        static::assertEquals($suggestions, $output);
    }

    /**
     * @inheritDoc
     */
    protected function getFolderName(): string
    {
        return 'NamespaceAutocompletionProviderTest';
    }

    /**
     * @inheritDoc
     */
    protected function getProviderName(): string
    {
        return 'namespaceAutocompletionProvider';
    }
}