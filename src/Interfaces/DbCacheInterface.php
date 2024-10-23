<?php

namespace ByJG\AnyDataset\Db\Interfaces;

interface DbCacheInterface
{
    public function getMaxStmtCache(): int;

    public function getCountStmtCache(): int;

    public function setMaxStmtCache(int $maxStmtCache): void;

}