<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Lists\ArrayDataset;
use PDOStatement;
use Psr\SimpleCache\CacheInterface;

trait DbCacheTrait
{
    protected array $stmtCache = [];

    protected int $maxStmtCache = 10;

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

    protected function getOrSetSqlCacheStmt(string $sql): PDOStatement
    {
        if (!isset($this->stmtCache[$sql])) {
            $this->stmtCache[$sql] = $this->getInstance()->prepare($sql);
            if ($this->getCountStmtCache() > $this->getMaxStmtCache()) { //Kill old cache to get waste memory
                array_shift($this->stmtCache);
            }
        }

        return $this->stmtCache[$sql];
    }

    public function getIteratorUsingCache($sql, $params, ?CacheInterface $cache, $ttl, \Closure $closure): GenericIterator
    {
        $cacheKey = $this->getQueryKey($cache, $sql, $params);

        do {
            $lock = $this->mutexIsLocked($cache, $cacheKey);
            if ($lock !== false) {
                usleep(1000);
                continue;
            }

            $cachedResult = $this->getCachedResult($cacheKey, $cache);
            if (!empty($cachedResult)) {
                return $cachedResult;
            }
            $this->mutexLock($cache, $cacheKey);
            try {
                $iterator = $closure($sql, $params);
                return $this->cacheResult($cacheKey, $iterator, $cache, $ttl);
            } finally {
                $this->mutexRelease($cache, $cacheKey);
            }
        } while (true);
    }

    protected function mutexIsLocked(?CacheInterface $cache, ?string $cacheKey)
    {
        if (empty($cache)) {
            return false;
        }
        return $cache->get($cacheKey . ".lock", false);
    }

    protected function mutexLock(?CacheInterface  $cache, ?string $cacheKey)
    {
        if (empty($cache)) {
            return;
        }
        $cache->set($cacheKey . ".lock", time(), \DateInterval::createFromDateString('5 min'));;
    }

    protected function mutexRelease(?CacheInterface  $cache, ?string $cacheKey)
    {
        if (empty($cache)) {
            return;
        }
        $cache->delete($cacheKey . ".lock");
    }

    protected function getCachedResult($key, ?CacheInterface $cache): ?GenericIterator
    {
        if (!empty($cache)) {
            // Get the CACHE
            $cachedItem = $cache->get($key);
            if (!is_null($cachedItem)) {
                return (new ArrayDataset($cachedItem))->getIterator();
            }
        }

        return null;
    }

    protected function cacheResult($key, GenericIterator $iterator, ?CacheInterface $cache, $ttl): GenericIterator
    {
        if (!empty($cache)) {
            $cachedItem = $iterator->toArray();
            $cache->set($key, $cachedItem, $ttl);
            return (new ArrayDataset($cachedItem))->getIterator();
        }

        return $iterator;
    }

    protected function array_map_assoc($callback, $array)
    {
        $r = array();
        foreach ($array as $key=>$value) {
            $r[$key] = $callback($key, $value);
        }
        return $r;
    }

    protected function getQueryKey(?CacheInterface $cache, $sql, $array)
    {
        if (empty($cache)) {
            return null;
        }

        $key1 = md5($sql);
        $key2 = "";

        // Check which parameter exists in the SQL
        if (is_array($array)) {
            $key2 = md5(":" . implode(',', $this->array_map_assoc(function($k,$v){return "$k:$v";},$array)));
        }

        return  "qry:" . $key1 . $key2;
    }

}