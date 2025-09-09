<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Lists\ArrayDataset;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class SqlStatement
{
    protected string $sql;

    protected array $cachedStatement = [];

    protected ?CacheInterface $cache = null;

    protected ?int $cacheTime = null;

    protected ?string $cacheKey = null;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
        $this->cache = null;
        $this->cacheTime = null;
        $this->cacheKey = null;
    }

    public function withCache(CacheInterface $cache, string $cacheKey, int $cacheTime = 60): SqlStatement
    {
        $this->cache = $cache;
        $this->cacheTime = $cacheTime;
        $this->cacheKey = $cacheKey;
        return $this;
    }

    public function withoutCache()
    {
        $this->cache = null;
        $this->cacheTime = null;
        $this->cacheKey = null;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }

    public function getCacheTime(): ?int
    {
        return $this->cacheTime;
    }

    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }


    public function getIterator(DbDriverInterface $dbDriver, ?array $param = [], int $preFetch = 0): GenericIterator
    {
        $cacheKey = "";
        if (!empty($this->cache)) {
            ksort($param);
            $cacheKey = $this->cacheKey . ':' . md5(json_encode($param));
            if ($this->cache->has($cacheKey)) {
                return (new ArrayDataset($this->cache->get($cacheKey)))->getIterator();
            }
        }

        do {
            $lock = $this->mutexIsLocked($cacheKey);
            if ($lock !== false) {
                usleep(200);
                continue;
            }

            $this->mutexLock($cacheKey);
            try {
                $statement = $dbDriver->prepareStatement($this->sql, $param, $this->cachedStatement);

                $dbDriver->executeCursor($statement);
                $iterator = $dbDriver->getIterator($statement, preFetch: $preFetch);

                if (!empty($this->cache)) {
                    $cachedItem = $iterator->toArray();
                    $this->cache->set($cacheKey, $cachedItem, $this->cacheTime);
                    return (new ArrayDataset($cachedItem))->getIterator();
                }

                return $iterator;
            } finally {
                $this->mutexRelease($cacheKey);
            }
        } while (true);
    }

    public function getScalar(DbDriverInterface $dbDriver, ?array $array = null): mixed
    {
        $stmt = $dbDriver->prepareStatement($this->sql, $array, $this->cachedStatement);
        $dbDriver->executeCursor($stmt);

        return $dbDriver->getScalar($stmt);
    }

    public function execute(DbDriverInterface $dbDriver, ?array $array = null): void
    {
        $stmt = $dbDriver->prepareStatement($this->sql, $array, $this->cachedStatement);
        $dbDriver->executeCursor($stmt);
        $dbDriver->processMultiRowset($stmt);
    }

    public function executeAndGetId(DbDriverInterface $dbDriver, ?array $array = null): mixed
    {
        return $dbDriver->getDbHelper()->executeAndGetInsertedId($dbDriver, $this->sql, $array);
    }

    /**
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function mutexIsLocked(string $cacheKey): mixed
    {
        if (empty($this->cache)) {
            return false;
        }
        return $this->cache->get($cacheKey . ".lock", false);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function mutexLock(string $cacheKey): void
    {
        if (empty($this->cache)) {
            return;
        }
        $this->cache->set($cacheKey . ".lock", time(), DateInterval::createFromDateString('5 min'));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function mutexRelease(string $cacheKey): void
    {
        if (empty($this->cache)) {
            return;
        }
        $this->cache->delete($cacheKey . ".lock");
    }

}