<?php

namespace ByJG\AnyDataset\Db\Traits;

trait DbCacheTrait
{
    protected $stmtCache = [];

    protected $maxStmtCache = 10;

    /**
     * @return int
     */
    public function getMaxStmtCache()
    {
        return $this->maxStmtCache;
    }

    public function getCountStmtCache()
    {
        return count($this->stmtCache);
    }

    /**
     * @param int $maxStmtCache
     */
    public function setMaxStmtCache($maxStmtCache)
    {
        $this->maxStmtCache = $maxStmtCache;
    }

    protected function clearCache()
    {
        $this->stmtCache = [];
    }

    protected function getOrSetSqlCacheStmt($sql)
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