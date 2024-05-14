<?php

namespace ByJG\AnyDataset\Db\Traits;

use PDOStatement;

trait DbCacheTrait
{
    protected array $stmtCache = [];

    protected int $maxStmtCache = 10;

    protected bool $useCache = false;

    /**
     * @return int
     */
    public function getMaxStmtCache(): int
    {
        return $this->maxStmtCache;
    }

    public function getCountStmtCache(): int
    {
        return count($this->stmtCache);
    }

    /**
     * @param int $maxStmtCache
     */
    public function setMaxStmtCache(int $maxStmtCache): void
    {
        $this->maxStmtCache = $maxStmtCache;
    }

    protected function enableCache(): void
    {
        $this->useCache = true;
    }

    protected function clearCache(): void
    {
        $this->stmtCache = [];
    }

    protected function isCachingStmt(): bool
    {
        return $this->useCache;
    }

    protected function getOrSetSqlCacheStmt($sql): PDOStatement
    {
        if (!isset($this->stmtCache[$sql])) {
            $this->stmtCache[$sql] = $this->getInstance()->prepare($sql);
            if ($this->getCountStmtCache() > $this->getMaxStmtCache()) { //Kill old cache to get waste memory
                array_shift($this->stmtCache);
            }
        }

        return $this->stmtCache[$sql];
    }
}