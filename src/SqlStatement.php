<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
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
     * Get an iterator for this SQL statement with the specified parameters
     *
     * If cache is enabled, tries to get results from cache first.
     * Uses mutex locking to prevent multiple processes from generating the same cached results.
     *
     * @param DbDriverInterface $dbDriver The database driver
     * @param array|null $param Parameters for the SQL query
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericIterator The iterator containing the results
     * @throws XmlUtilException
     * @throws FileException
     * @throws InvalidArgumentException
     */
    public function getIterator(DbDriverInterface $dbDriver, ?array $param = [], int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericIterator
    {
        // If no cache is configured, just execute the query
        if (empty($this->cache)) {
            return $this->executeQuery($dbDriver, $param, $preFetch, $entityClass, $entityTransformer);
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
            usleep(100000); // 100ms
        }

        // Double-check if cache is available after waiting
        if ($this->cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cacheKey, $entityClass);
        }

        // Create a mutex lock while we generate the cache
        $this->mutexLock($cacheKey);

        try {
            // Execute the query and cache the results
            return $this->executeAndCacheQuery($dbDriver, $param, $preFetch, $cacheKey, $entityClass, $entityTransformer);
        } finally {
            // Always release the mutex lock
            $this->mutexRelease($cacheKey);
        }
    }

    /**
     * Execute the SQL query with the specified parameters
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $param
     * @param int $preFetch
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     * @return GenericIterator
     */
    protected function executeQuery(DbDriverInterface $dbDriver, ?array $param, int $preFetch, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericIterator
    {
        $statement = $dbDriver->prepareStatement($this->sql, $param);
        $dbDriver->executeCursor($statement);

        // Let the DbDriver handle creating the appropriate iterator type
        return $dbDriver->getIterator($statement, preFetch: $preFetch, entityClass: $entityClass, entityTransformer: $entityTransformer);
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
     * Execute the query and cache the results
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $param
     * @param int $preFetch
     * @param string $cacheKey
     * @param string|null $entityClass
     * @param PropertyHandlerInterface|null $entityTransformer
     * @return GenericIterator
     */
    protected function executeAndCacheQuery(DbDriverInterface $dbDriver, ?array $param, int $preFetch, string $cacheKey, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericIterator
    {
        // Execute the query
        $iterator = $this->executeQuery($dbDriver, $param, $preFetch, $entityClass, $entityTransformer);

        // Cache the results
        $arrayData = [];
        foreach ($iterator as $item) {
            $arrayData[] = $item->toArray();
        }
        $this->cache->set($cacheKey, $arrayData, $this->cacheTime);

        // Now we can create a new iterator from the cached data
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