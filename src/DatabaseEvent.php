<?php

namespace ByJG\AnyDataset\Db;

/**
 * Immutable value object describing an event fired by the DatabaseExecutor.
 */
class DatabaseEvent
{
    public function __construct(
        protected DatabaseEventTypeEnum $type,
        protected DatabaseExecutor $executor,
        protected SqlStatement $statement,
        protected mixed $result = null
    ) {
    }

    public function getType(): DatabaseEventTypeEnum
    {
        return $this->type;
    }

    /**
     * The executor that fired the event. Observers can use it to run
     * additional queries (the driver connection - and transaction - is shared).
     */
    public function getExecutor(): DatabaseExecutor
    {
        return $this->executor;
    }

    public function getStatement(): SqlStatement
    {
        return $this->statement;
    }

    /**
     * The result of the operation (only for AFTER_* events).
     * For AFTER_EXECUTE it is `true` (execute) or the operation result when available.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }
}