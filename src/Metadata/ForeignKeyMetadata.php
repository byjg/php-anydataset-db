<?php

namespace ByJG\AnyDataset\Db\Metadata;

/**
 * A foreign key of a table, as returned by SqlDialectInterface::getTableForeignKeys().
 * columns[i] references parentColumns[i] in parentTable.
 */
readonly class ForeignKeyMetadata
{
    /**
     * @param list<string> $columns The columns of this table, in key order
     * @param string $parentTable The referenced table
     * @param list<string> $parentColumns The referenced columns, in key order
     */
    public function __construct(
        public array $columns,
        public string $parentTable,
        public array $parentColumns,
    ) {
    }
}
