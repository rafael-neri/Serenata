<?php

namespace Serenata\Indexing;

/**
 * Enumeration of indexing event names.
 */
final class IndexingEventName
{
    /**
     * @var string
     */
    public const NAMESPACE_UPDATED = 'namespaceUpdated';

    /**
     * @var string
     */
    public const NAMESPACE_REMOVED = 'namespaceRemoved';

    /**
     * @var string
     */
    public const IMPORT_INSERTED = 'importInserted';

    /**
     * @var string
     */
    public const CONSTANT_UPDATED = 'constantUpdated';

    /**
     * @var string
     */
    public const CONSTANT_REMOVED = 'constantRemoved';

    /**
     * @var string
     */
    public const FUNCTION_UPDATED = 'functionUpdated';

    /**
     * @var string
     */
    public const FUNCTION_REMOVED = 'functionRemoved';

    /**
     * @var string
     */
    public const CLASSLIKE_UPDATED = 'classlikeUpdated';

    /**
     * @var string
     */
    public const CLASSLIKE_REMOVED = 'classlikeRemoved';

    /**
     * @var string
     */
    public const INDEXING_SUCCEEDED_EVENT = 'indexingSucceeded';
}
