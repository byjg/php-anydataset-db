<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
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

    /**
     * Get an iterator for this SQL statement.
     *
     * If cache is enabled, tries to get results from cache first.
     * Uses mutex locking to prevent multiple processes from generating the same cached results.
     *
     * @param DbDriverInterface $dbDriver The database driver
     * @param array|null $param Parameters for the SQL query
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @return GenericIterator The iterator containing the results
     * @throws XmlUtilException
     * @throws FileException
     * @throws InvalidArgumentException
     */
    public function getIterator(DbDriverInterface $dbDriver, ?array $param = [], int $preFetch = 0, ?string $entityClass = null): GenericIterator
    {
        // If no cache is configured, just execute the query
        if (empty($this->cache)) {
            return $this->executeQuery($dbDriver, $param, $preFetch, $entityClass);
        }

        // Prepare cache key
        ksort($param);
        $cacheKey = $this->cacheKey . ':' . md5(json_encode($param));

        // Try to get from cache first
        if ($this->cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cacheKey, $entityClass);
        }

        // Wait until no other process is generating this cache
        while ($this->mutexIsLocked($cacheKey) !== false) {
            usleep(200);
        }

        // Lock the mutex to prevent other processes from generating the same cache
        $this->mutexLock($cacheKey);

        try {
            // Check again if cache was created while waiting for lock
            if ($this->cache->has($cacheKey)) {
                return $this->getIteratorFromCache($cacheKey, $entityClass);
            }

            // Execute the query and cache the results
            return $this->executeAndCacheQuery($dbDriver, $param, $preFetch, $cacheKey, $entityClass);
        } finally {
            $this->mutexRelease($cacheKey);
        }
    }

    /**
     * Execute the query without caching
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $param
     * @param int $preFetch
     * @param string|null $entityClass
     * @return GenericIterator
     */
    protected function executeQuery(DbDriverInterface $dbDriver, ?array $param, int $preFetch, ?string $entityClass = null): GenericIterator
    {
        $statement = $dbDriver->prepareStatement($this->sql, $param, $this->cachedStatement);
        $dbDriver->executeCursor($statement);
        return $dbDriver->getIterator($statement, preFetch: $preFetch, entityClass: $entityClass);
    }

    /**
     * Get iterator from cache
     *
     * @param string $cacheKey
     * @param string|null $entityClass
     * @return GenericIterator
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     */
    protected function getIteratorFromCache(string $cacheKey, ?string $entityClass = null): GenericIterator
    {
        // Get data from cache
        $data = $this->cache->get($cacheKey);

        // Use AnyDataset to get the raw data
        $anyDataset = new AnyDataset($data);
        $iterator = $anyDataset->getIterator(null);

        if ($entityClass === null) {
            return $iterator;
        }

        // When an entity class is specified, we need to manually instantiate the objects
        // We'll leave that to the caller by returning the raw data iterator
        return $iterator;
    }

    /**
     * Execute query and cache the results
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $param
     * @param int $preFetch
     * @param string $cacheKey
     * @param string|null $entityClass
     * @return GenericIterator
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     */
    protected function executeAndCacheQuery(DbDriverInterface $dbDriver, ?array $param, int $preFetch, string $cacheKey, ?string $entityClass = null): GenericIterator
    {
        $iterator = $this->executeQuery($dbDriver, $param, $preFetch, $entityClass);

        // Convert to array for caching and cache it
        $cachedItem = $iterator->toArray();
        $this->cache->set($cacheKey, $cachedItem, $this->cacheTime);

        // When we get from cache, we lose the entity class information
        // Return the cached iterator, the caller will have to handle entity conversion
        return $this->getIteratorFromCache($cacheKey, $entityClass);
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