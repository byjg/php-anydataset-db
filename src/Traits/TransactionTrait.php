<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\Exception\TransactionNotStartedException;
use ByJG\AnyDataset\Db\Exception\TransactionStartedException;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\TransactionStageEnum;

/**
 * Trait TransactionTrait
 * @package ByJG\AnyDataset\Db
 *
 */
trait TransactionTrait
{
    protected IsolationLevelEnum|null $isolationLevel = null;

    protected int $transactionCount = 0;

    /**
     * @throws TransactionStartedException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function beginTransaction(?IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void
    {
        if ($this->hasActiveTransaction()) {
            if (!$allowJoin) {
                throw new TransactionStartedException("There is already an active transaction");
            } else if (!empty($isolationLevel) && $this->activeIsolationLevel() != $isolationLevel) {
                throw new TransactionStartedException("You cannot join a transaction with a different isolation level");
            }
            $this->transactionCount++;
            return;
        }

        $this->logger->debug("SQL: Begin transaction");
        $isolLevelCommand = $this->getSqlDialect()->getIsolationLevelCommand($isolationLevel);
        $this->transactionHandler(TransactionStageEnum::begin, $isolLevelCommand);
        $this->transactionCount = 1;
        $this->isolationLevel = $isolationLevel;
    }

    /**
     * @throws DatabaseException
     * @throws TransactionNotStartedException
     * @throws DbDriverNotConnected
     */
    public function commitTransaction(): void
    {
        $this->logger->debug("SQL: Commit transaction");
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("There is no active transaction");
        }
        $this->transactionCount--;
        if ($this->transactionCount > 0) {
            return;
        }
        $this->transactionHandler(TransactionStageEnum::commit);
        $this->isolationLevel = null;
    }

    /**
     * @throws DatabaseException
     * @throws TransactionNotStartedException
     * @throws DbDriverNotConnected
     */
    public function rollbackTransaction(): void
    {
        $this->logger->debug("SQL: Rollback transaction");
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("There is no active transaction");
        }
        $this->transactionHandler(TransactionStageEnum::rollback);
        $this->transactionCount = 0;
        $this->isolationLevel = null;
    }

    public function remainingCommits(): int
    {
        return $this->transactionCount;
    }

    /**
     * @throws TransactionNotStartedException
     */
    public function requiresTransaction(): void
    {
        if (!$this->hasActiveTransaction()) {
            throw new TransactionNotStartedException("A transaction is required.");
        }
    }

    public function hasActiveTransaction(): bool
    {
        return $this->remainingCommits() > 0;
    }

    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        return $this->isolationLevel;
    }

}