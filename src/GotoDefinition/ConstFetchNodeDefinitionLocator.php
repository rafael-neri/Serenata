<?php

namespace Serenata\GotoDefinition;

use PhpParser\Node;

use Serenata\Analysis\ConstantListProviderInterface;

use Serenata\Analysis\Node\ConstFetchNodeFqsenDeterminer;

use Serenata\Common\Position;

use Serenata\Utility\Location;
use Serenata\Utility\TextDocumentItem;

/**
 * Locates the definition of the constant called in {@see Node\Expr\ConstFetch} nodes.
 */
final class ConstFetchNodeDefinitionLocator
{
    /**
     * @var ConstFetchNodeFqsenDeterminer
     */
    private $constFetchNodeFqsenDeterminer;

    /**
     * @var ConstantListProviderInterface
     */
    private $constantListProvider;

    /**
     * @param ConstFetchNodeFqsenDeterminer  $constFetchNodeFqsenDeterminer
     * @param ConstantListProviderInterface $constantListProvider
     */
    public function __construct(
        ConstFetchNodeFqsenDeterminer $constFetchNodeFqsenDeterminer,
        ConstantListProviderInterface $constantListProvider
    ) {
        $this->constFetchNodeFqsenDeterminer = $constFetchNodeFqsenDeterminer;
        $this->constantListProvider = $constantListProvider;
    }

    /**
     * @param Node\Expr\ConstFetch $node
     * @param TextDocumentItem     $textDocumentItem
     * @param Position             $position
     *
     * @throws DefinitionLocationFailedException when the constant was not found.
     *
     * @return GotoDefinitionResponse
     */
    public function generate(
        Node\Expr\ConstFetch $node,
        TextDocumentItem $textDocumentItem,
        Position $position
    ): GotoDefinitionResponse {
        $fqsen = $this->constFetchNodeFqsenDeterminer->determine($node, $textDocumentItem, $position);

        $info = $this->getConstantInfo($fqsen);

        return new GotoDefinitionResponse(new Location($info['uri'], $info['range']));
    }

    /**
     * @param string $fullyQualifiedName
     *
     * @throws DefinitionLocationFailedException
     *
     * @return array<string,mixed>
     */
    private function getConstantInfo(string $fullyQualifiedName): array
    {
        $functions = $this->constantListProvider->getAll();

        if (!isset($functions[$fullyQualifiedName])) {
            throw new DefinitionLocationFailedException('No data found for function with name ' . $fullyQualifiedName);
        }

        return $functions[$fullyQualifiedName];
    }
}
