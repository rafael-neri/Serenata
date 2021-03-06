<?php

namespace Serenata\UserInterface\JsonRpcQueueItemHandler;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;

use Serenata\Common\Position;

use Serenata\Indexing\TextDocumentContentRegistry;
use Serenata\Indexing\FileNotFoundStorageException;

use Serenata\NameQualificationUtilities\PositionOutOfBoundsPositionalNamespaceDeterminerException;

use Serenata\Sockets\JsonRpcResponse;
use Serenata\Sockets\JsonRpcQueueItem;

use Serenata\Tooltips\TooltipResult;
use Serenata\Tooltips\TooltipProvider;

use Serenata\Utility\MessageType;
use Serenata\Utility\MessageLogger;
use Serenata\Utility\LogMessageParams;
use Serenata\Utility\TextDocumentItem;

/**
 * JsonRpcQueueItemHandlerthat fetches tooltip information for a specific location.
 */
final class HoverJsonRpcQueueItemHandler extends AbstractJsonRpcQueueItemHandler
{
    /**
     * @var TextDocumentContentRegistry
     */
    private $textDocumentContentRegistry;

    /**
     * @var TooltipProvider
     */
    private $tooltipProvider;

    /**
     * @var MessageLogger
     */
    private $messageLogger;

    /**
     * @param TextDocumentContentRegistry $textDocumentContentRegistry
     * @param TooltipProvider             $tooltipProvider
     * @param MessageLogger               $messageLogger
     */
    public function __construct(
        TextDocumentContentRegistry $textDocumentContentRegistry,
        TooltipProvider $tooltipProvider,
        MessageLogger $messageLogger
    ) {
        $this->textDocumentContentRegistry = $textDocumentContentRegistry;
        $this->tooltipProvider = $tooltipProvider;
        $this->messageLogger = $messageLogger;
    }

    /**
     * @inheritDoc
     */
    public function execute(JsonRpcQueueItem $queueItem): ExtendedPromiseInterface
    {
        $parameters = $queueItem->getRequest()->getParams() !== null ?
            $queueItem->getRequest()->getParams() :
            [];

        try {
            $result = $this->getTooltip(
                $parameters['textDocument']['uri'],
                $this->textDocumentContentRegistry->get($parameters['textDocument']['uri']),
                new Position($parameters['position']['line'], $parameters['position']['character'])
            );
        } catch (FileNotFoundStorageException|PositionOutOfBoundsPositionalNamespaceDeterminerException $e) {
            $this->messageLogger->log(
                new LogMessageParams(MessageType::WARNING, $e->getMessage()),
                $queueItem->getJsonRpcMessageSender()
            );

            $result = null;
        }

        $response = new JsonRpcResponse($queueItem->getRequest()->getId(), $result);

        $deferred = new Deferred();
        $deferred->resolve($response);

        return $deferred->promise();
    }

    /**
     * @param string   $uri
     * @param string   $code
     * @param Position $position
     *
     * @return TooltipResult|null
     */
    public function getTooltip(string $uri, string $code, Position $position): ?TooltipResult
    {
        return $this->tooltipProvider->get(new TextDocumentItem($uri, $code), $position);
    }
}
