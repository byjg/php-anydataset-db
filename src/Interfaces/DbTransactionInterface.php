<?php

namespace ByJG\AnyDataset\Db\Interfaces;

use ByJG\AnyDataset\Db\IsolationLevelEnum;

interface DbTransactionInterface
{
    public function beginTransaction(IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false);

    public function commitTransaction(): void;

    public function rollbackTransaction(): void;

    public function hasActiveTransaction(): bool;

    public function activeIsolationLevel(): ?IsolationLevelEnum;

    public function remainingCommits(): int;

    public function requiresTransaction(): void;
}
