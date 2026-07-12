<?php

namespace ByJG\AnyDataset\Db\Journal;

/**
 * A single recorded DML operation with the row values
 * before (oldRows) and after (newRows) the operation.
 */
class JournalEntry
{
    /**
     * @param JournalOperationEnum $operation
     * @param string $table
     * @param array<int, array<string, mixed>> $oldRows Rows as they were BEFORE the operation (empty for INSERT)
     * @param array<int, array<string, mixed>> $newRows Rows as they are AFTER the operation (empty for DELETE)
     * @param string $sql The original SQL statement
     * @param array $params The original parameters
     */
    public function __construct(
        protected JournalOperationEnum $operation,
        protected string $table,
        protected array $oldRows,
        protected array $newRows,
        protected string $sql,
        protected array $params
    ) {
    }

    public function getOperation(): JournalOperationEnum
    {
        return $this->operation;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOldRows(): array
    {
        return $this->oldRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getNewRows(): array
    {
        return $this->newRows;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}