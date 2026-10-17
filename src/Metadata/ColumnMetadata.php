<?php

namespace ByJG\AnyDataset\Db\Metadata;

/**
 * A column of a table, as returned by SqlDialectInterface::getTableMetadata().
 */
readonly class ColumnMetadata
{
    /**
     * @param string $name The column name, as the database reports it
     * @param string $dbType The declared type, in lower case (e.g. "varchar(50)")
     * @param bool $required The column is NOT NULL
     * @param mixed $default The default value, as the database reports it
     * @param string $phpType "integer", "float", "bool" or "string"
     * @param int|null $length The declared length or numeric precision, when there is one
     * @param int|null $precision The declared numeric scale, when there is one
     * @param bool $primaryKey The column is part of the primary key
     * @param bool $autoIncrement The database fills in the value (auto_increment, identity, serial, rowid)
     * @param bool $generated The database computes the value from an expression; it cannot be written
     */
    public function __construct(
        public string $name,
        public string $dbType,
        public bool $required,
        public mixed $default,
        public string $phpType,
        public ?int $length,
        public ?int $precision,
        public bool $primaryKey,
        public bool $autoIncrement,
        public bool $generated,
    ) {
    }
}
