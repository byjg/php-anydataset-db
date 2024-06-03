<?php

namespace ByJG\AnyDataset\Db\Interfaces;

interface DbTransactionInterface
{
    public function beginTransaction($isolationLevel = null, $allowJoin = false);

    public function commitTransaction();

    public function rollbackTransaction();

    public function hasActiveTransaction();

    public function activeIsolationLevel();

    public function remainingCommits();

    public function requiresTransaction();
}