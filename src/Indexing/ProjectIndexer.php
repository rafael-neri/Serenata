<?php

namespace PhpIntegrator\Indexing;

use UnexpectedValueException;

use PhpIntegrator\Utility\SourceCodeStreamReader;

/**
 * Handles project and folder indexing.
 */
class ProjectIndexer
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var FileIndexerFactoryInterface
     */
    private $fileIndexerFactory;

    /**
     * @var SourceCodeStreamReader
     */
    private $sourceCodeStreamReader;

    /**
     * @var resource|null
     */
    private $loggingStream;

    /**
     * @var callable|null
     */
    private $progressStreamingCallback;

    /**
     * @param StorageInterface            $storage
     * @param FileIndexerFactoryInterface $fileIndexerFactory
     * @param SourceCodeStreamReader      $sourceCodeStreamReader
     */
    public function __construct(
        StorageInterface $storage,
        FileIndexerFactoryInterface $fileIndexerFactory,
        SourceCodeStreamReader $sourceCodeStreamReader
    ) {
        $this->storage = $storage;
        $this->fileIndexerFactory = $fileIndexerFactory;
        $this->sourceCodeStreamReader = $sourceCodeStreamReader;
    }

    /**
     * @return resource|null
     */
    public function getLoggingStream()
    {
        return $this->loggingStream;
    }

    /**
     * @param resource|null $loggingStream
     *
     * @return static
     */
    public function setLoggingStream($loggingStream)
    {
        $this->loggingStream = $loggingStream;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getProgressStreamingCallback(): ?callable
    {
        return $this->progressStreamingCallback;
    }

    /**
     * @param callable|null $progressStreamingCallback
     *
     * @return static
     */
    public function setProgressStreamingCallback(?callable $progressStreamingCallback)
    {
        $this->progressStreamingCallback = $progressStreamingCallback;
        return $this;
    }

    /**
     * Logs a single message for debugging purposes.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logMessage($message): void
    {
        if (!$this->loggingStream) {
            return;
        }

        fwrite($this->loggingStream, $message . PHP_EOL);
    }

    /**
     * Logs progress for streaming progress.
     *
     * @param int $itemNumber
     * @param int $totalItemCount
     *
     * @return void
     */
    protected function sendProgress(int $itemNumber, int $totalItemCount): void
    {
        $callback = $this->getProgressStreamingCallback();

        if (!$callback) {
            return;
        }

        if ($totalItemCount) {
            $progress = ($itemNumber / $totalItemCount) * 100;
        } else {
            $progress = 100;
        }

        $callback($progress);
    }

    /**
     * Indexes the specified project.
     *
     * @param string[] $items
     * @param string[] $extensionsToIndex
     * @param string[] $excludedPaths
     * @param array    $sourceOverrideMap
     *
     * @return void
     */
    public function index(
        array $items,
        array $extensionsToIndex,
        array $excludedPaths = [],
        array $sourceOverrideMap = []
    ): void {
        $fileModifiedMap = $this->storage->getFileModifiedMap();

        // The modification time doesn't matter for files we have direct source code for, as this source code always
        // needs to be indexed (e.g it may simply not have been saved to disk yet).
        foreach ($sourceOverrideMap as $filePath => $source) {
            unset($fileModifiedMap[$filePath]);
        }

        $iterator = new Iterating\MultiRecursivePathIterator($items);
        $iterator = new Iterating\ExtensionFilterIterator($iterator, $extensionsToIndex);
        $iterator = new Iterating\ExclusionFilterIterator($iterator, $excludedPaths);
        $iterator = new Iterating\ModificationTimeFilterIterator($iterator, $fileModifiedMap);

        $this->logMessage('Scanning and indexing files that need (re)indexing...');

        $totalItems = iterator_count($iterator);

        $this->sendProgress(0, $totalItems);

        $i = 0;

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $filePath = $fileInfo->getPathname();

            $this->logMessage('  - Indexing ' . $filePath);

            $code = null;

            if (isset($sourceOverrideMap[$filePath])) {
                $code = $sourceOverrideMap[$filePath];
            } else {
                try {
                    $code = $this->sourceCodeStreamReader->getSourceCodeFromFile($filePath);
                } catch (UnexpectedValueException $e) {
                    $code = null; // Skip files that we can't read.
                }
            }

            if ($code !== null) {
                try {
                    $this->fileIndexerFactory->create($filePath)->index($filePath, $code);
                } catch (IndexingFailedException $e) {
                    $this->logMessage('    - ERROR: Indexing failed due to parsing errors!');
                }
            }

            $this->sendProgress(++$i, $totalItems);
        }
    }

    /**
     * Prunes removed files from the index.
     *
     * @return void
     */
    public function pruneRemovedFiles(): void
    {
        foreach ($this->storage->getFileModifiedMap() as $fileName => $indexedTime) {
            if (!file_exists($fileName)) {
                $this->logMessage('  - ' . $fileName);

                $this->storage->deleteFile($fileName);
            }
        }
    }
}
