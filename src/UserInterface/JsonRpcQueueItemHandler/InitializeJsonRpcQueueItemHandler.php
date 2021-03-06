<?php

namespace Serenata\UserInterface\JsonRpcQueueItemHandler;

use React\Promise;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;

use UnexpectedValueException;

use Serenata\Indexing\IndexFilePruner;
use Serenata\Indexing\ManagerRegistry;
use Serenata\Indexing\PathNormalizer;
use Serenata\Indexing\IndexerInterface;
use Serenata\Indexing\SchemaInitializer;
use Serenata\Indexing\StorageVersionChecker;

use Serenata\Sockets\JsonRpcRequest;
use Serenata\Sockets\JsonRpcResponse;
use Serenata\Sockets\JsonRpcQueueItem;
use Serenata\Sockets\JsonRpcMessageSenderInterface;

use Serenata\Utility\MessageType;
use Serenata\Utility\SaveOptions;
use Serenata\Utility\MessageLogger;
use Serenata\Utility\InitializeParams;
use Serenata\Utility\InitializeResult;
use Serenata\Utility\LogMessageParams;
use Serenata\Utility\CompletionOptions;
use Serenata\Utility\ServerCapabilities;
use Serenata\Utility\SignatureHelpOptions;
use Serenata\Utility\TextDocumentSyncOptions;

use Serenata\Workspace\Workspace;
use Serenata\Workspace\ActiveWorkspaceManager;

use Serenata\Workspace\Configuration\Parsing\WorkspaceConfigurationParserInterface;

/**
 * JsonRpcQueueItemHandlerthat initializes a project.
 */
final class InitializeJsonRpcQueueItemHandler extends AbstractJsonRpcQueueItemHandler
{
    /**
     * @var ActiveWorkspaceManager
     */
    private $activeWorkspaceManager;

    /**
     * @var WorkspaceConfigurationParserInterface
     */
    private $workspaceConfigurationParser;

    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var StorageVersionChecker
     */
    private $storageVersionChecker;

    /**
     * @var IndexerInterface
     */
    private $indexer;

    /**
     * @var SchemaInitializer
     */
    private $schemaInitializer;

    /**
     * @var IndexFilePruner
     */
    private $indexFilePruner;

    /**
     * @var MessageLogger
     */
    private $messageLogger;

    /**
     * @var PathNormalizer
     */
    private $pathNormalizer;

    /**
     * @param ActiveWorkspaceManager                $activeWorkspaceManager
     * @param WorkspaceConfigurationParserInterface $workspaceConfigurationParser
     * @param ManagerRegistry                       $managerRegistry
     * @param StorageVersionChecker                 $storageVersionChecker
     * @param IndexerInterface                      $indexer
     * @param SchemaInitializer                     $schemaInitializer
     * @param IndexFilePruner                       $indexFilePruner
     * @param MessageLogger                         $messageLogger
     */
    public function __construct(
        ActiveWorkspaceManager $activeWorkspaceManager,
        WorkspaceConfigurationParserInterface $workspaceConfigurationParser,
        ManagerRegistry $managerRegistry,
        StorageVersionChecker $storageVersionChecker,
        IndexerInterface $indexer,
        SchemaInitializer $schemaInitializer,
        IndexFilePruner $indexFilePruner,
        MessageLogger $messageLogger,
        PathNormalizer $pathNormalizer
    ) {
        $this->activeWorkspaceManager = $activeWorkspaceManager;
        $this->workspaceConfigurationParser = $workspaceConfigurationParser;
        $this->managerRegistry = $managerRegistry;
        $this->storageVersionChecker = $storageVersionChecker;
        $this->indexer = $indexer;
        $this->schemaInitializer = $schemaInitializer;
        $this->indexFilePruner = $indexFilePruner;
        $this->messageLogger = $messageLogger;
        $this->pathNormalizer = $pathNormalizer;
    }

    /**
     * @inheritDoc
     */
    public function execute(JsonRpcQueueItem $queueItem): ExtendedPromiseInterface
    {
        $params = $queueItem->getRequest()->getParams();

        if ($params === null || $params === []) {
            throw new InvalidArgumentsException('Missing parameters for initialize request');
        }

        return $this->initialize(
            $this->createInitializeParamsFromRawArray($params),
            $queueItem->getJsonRpcMessageSender(),
            $queueItem->getRequest()
        );
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return InitializeParams
     */
    private function createInitializeParamsFromRawArray(array $params): InitializeParams
    {
        return new InitializeParams(
            $params['processId'],
            $params['rootPath'] ?? null,
            $params['rootUri'],
            $params['initializationOptions'] ?? null,
            $params['capabilities'],
            $params['trace'] ?? 'off',
            $params['workspaceFolders'] ?? null
        );
    }

    /**
     * @param InitializeParams               $initializeParams
     * @param JsonRpcMessageSenderInterface $jsonRpcMessageSender
     * @param JsonRpcRequest                 $jsonRpcRequest
     * @param bool                           $initializeIndexForProject
     *
     * @throws InvalidArgumentsException
     *
     * @return ExtendedPromiseInterface
     */
    public function initialize(
        InitializeParams $initializeParams,
        JsonRpcMessageSenderInterface $jsonRpcMessageSender,
        JsonRpcRequest $jsonRpcRequest,
        bool $initializeIndexForProject = true
    ): ExtendedPromiseInterface {
        if ($this->activeWorkspaceManager->getActiveWorkspace() !== null) {
            throw new UnexpectedValueException(
                'Initialize was already called, send a shutdown request first if you want to initialize another project'
            );
        }

        $initializationOptions = $initializeParams->getInitializationOptions();

        $configuration = $initializationOptions['configuration'] ?? null;

        if ($configuration === null) {
            $uris = [];

            if ($initializeParams->getWorkspaceFolders() !== null && $initializeParams->getWorkspaceFolders() !== []) {
                $uris = array_map(function (array $folder) {
                    return $folder['uri'];
                }, $initializeParams->getWorkspaceFolders());
            } else {
                if ($initializeParams->getRootUri() === null) {
                    throw new InvalidArgumentsException(
                        'Need a valid "rootUri" in InitializeParams if no explicit "configuration" is passed and no ' .
                        'workspace folders have been explicitly configured'
                    );
                }

                $uris[] = $initializeParams->getRootUri();
            }

            $configuration = $this->getDefaultProjectConfiguration($uris);

            $this->messageLogger->log(new LogMessageParams(
                MessageType::INFO,
                'No explicit project configuration found, automatically generating one and using the ' .
                'system\'s temp folder to store the index database. You should consider setting up a ' .
                'Serenata configuration file, see also ' .
                'https://gitlab.com/Serenata/Serenata/wikis/Setting%20Up%20Your%20Project for more ' .
                'information.'
            ), $jsonRpcMessageSender);
        }

        $workspaceConfiguration = $this->workspaceConfigurationParser->parse($configuration);

        $this->managerRegistry->setDatabaseUri($workspaceConfiguration->getIndexDatabaseUri());
        $this->activeWorkspaceManager->setActiveWorkspace(new Workspace($workspaceConfiguration));

        if (!$this->storageVersionChecker->isUpToDate()) {
            $this->resetIndexDatabase();
        }

        $this->indexFilePruner->prune();

        $indexingPromise = null;

        if ($initializeIndexForProject) {
            $urisToIndex = $workspaceConfiguration->getUris();
            $urisToIndex[] = $this->getStubsUri();

            $promises = [];

            foreach ($urisToIndex as $uri) {
                $promises[] = $this->indexer->index($uri, false, $jsonRpcMessageSender);
            }

            $indexingPromise = Promise\all($promises);
        } else {
            $deferredIndexing = new Deferred();
            $deferredIndexing->resolve();

            $indexingPromise = $deferredIndexing->promise();
        }

        $promise = $indexingPromise->then(function () use ($jsonRpcRequest): JsonRpcResponse {
            return new JsonRpcResponse(
                $jsonRpcRequest->getId(),
                new InitializeResult(
                    new ServerCapabilities(
                        new TextDocumentSyncOptions(
                            false,
                            1,
                            false,
                            false,
                            new SaveOptions(true)
                        ),
                        true,
                        // '>' should be '->' and ':' should be '::', but some clients such as VSCode do not support
                        // multi-character triggers.
                        new CompletionOptions(false, ['>', '$', ':']),
                        new SignatureHelpOptions(['(', ',']),
                        true,
                        false,
                        false,
                        [
                            'workDoneProgress' => true,
                        ],
                        true,
                        true,
                        false,
                        false,
                        [
                            'resolveProvider' => true,
                        ],
                        false,
                        false,
                        null,
                        false,
                        null,
                        false,
                        false,
                        null,
                        [
                            'workspaceFolders' => [
                                'supported'           => false,
                                'changeNotifications' => false,
                            ],
                        ],
                        null
                    )
                )
            );
        });

        assert($promise instanceof ExtendedPromiseInterface);

        return $promise;
    }

    /**
     * @return string
     */
    private function getStubsUri(): string
    {
        $path = __DIR__ . '/../../../vendor/jetbrains/phpstorm-stubs/';

        if (mb_strpos(__DIR__, '://') === false) {
            // When not in the PHAR, __DIR__ will yield "/path/to/Serenata/src/..." (Linux and macOS) or
            // "C:\path\to\Serenata\src\..." (Windows). The input must be a URI, or indexed items will also not be a
            // URI.
            return 'file://' . $path;
        }

        // When in the PHAR, __DIR__ will yield "phar:///path/to/distribution.phar/absolute/path/to/Serenata/src/...".
        // so there's nothing we need to do here.
        return $path;
    }

    /**
     * @param string[] $uris
     *
     * @return array<string,mixed>
     */
    private function getDefaultProjectConfiguration(array $uris): array
    {
        return [
            'uris'             => $uris,
            'indexDatabaseUri' => $this->pathNormalizer->normalize(
                'file://' . sys_get_temp_dir() . '/' . md5(implode('-', $uris))
            ),
            'phpVersion'              => 7.3,
            'excludedPathExpressions' => [],
            'fileExtensions'          => ['php'],
        ];
    }

    /**
     * @return void
     */
    private function resetIndexDatabase(): void
    {
        $this->ensureIndexDatabaseDoesNotExist();

        $this->schemaInitializer->initialize();
    }

    /**
     * @return void
     */
    private function ensureIndexDatabaseDoesNotExist(): void
    {
        $this->managerRegistry->ensureConnectionClosed();

        $databaseUri = $this->managerRegistry->getDatabaseUri();

        if ($databaseUri === '') {
            return;
        }

        if (file_exists($databaseUri)) {
            unlink($databaseUri);
        }

        if (file_exists($databaseUri . '-shm')) {
            unlink($databaseUri . '-shm');
        }

        if (file_exists($databaseUri . '-wal')) {
            unlink($databaseUri . '-wal');
        }
    }
}
