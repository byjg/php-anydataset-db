<?php

namespace ByJG\AnyDataset\Db\Metadata;

/**
 * An index of a table, as returned by SqlDialectInterface::getTableIndexes().
 */
readonly class IndexMetadata
{
    /**
     * @param string $name The index name, as the database reports it
     * @param list<string> $columns The indexed columns, in index order
     * @param bool $unique The index is unique
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
    ) {
    }
}
