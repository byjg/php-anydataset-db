<?php

namespace ByJG\AnyDataset\Db\Traits;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use ByJG\XmlUtil\Exception\FileException;
use DateInterval;
use Exception;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

trait DatabaseExecutorTrait
{
    /**
     * Abstract method to handle driver-specific statement types
     *
     * @param mixed $statement The statement to check and handle (PDOStatement, resource, etc.)
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericIterator Returns GenericIterator for the statement
     */
    abstract protected function getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericIterator;

    /**
     * Execute a SQL statement with or without cache
     *
     * @param string|SqlStatement $sql The SQL statement to execute
     * @param array|null $param Parameters for the SQL query
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericIterator The iterator containing the results
     * @throws Exception
     * @throws FileException
     * @throws PsrInvalidArgumentException
     * @throws InvalidArgumentException
     */
    protected function executeStatement(
        string|SqlStatement $sql,
        ?array                    $param = [],
        int                       $preFetch = 0,
        ?string                   $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ): GenericIterator
    {
        // Convert string to SqlStatement if needed
        if (is_string($sql)) {
            $sql = new SqlStatement($sql);
        }

        $cache = $sql->getCache();
        $sqlText = $sql->getSql();

        // If no cache is configured, directly execute the query
        if (empty($cache)) {
            $statement = $this->prepareStatement($sqlText, $param);
            $this->executeCursor($statement);
            return $this->getDriverIterator($statement, $preFetch, $entityClass, $entityTransformer);
        }

        // Cache is configured - try to get from cache first
        ksort($param);
        $cacheKey = $sql->getCacheKey() . ':' . md5(json_encode($param));

        if ($cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cache, $cacheKey, $entityClass);
        }

        // Wait until no other process is generating this cache
        while ($this->mutexIsLocked($cache, $cacheKey) !== false) {
            usleep(100000); // 100ms
        }

        // Double-check if cache is available after waiting
        if ($cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cache, $cacheKey, $entityClass);
        }

        // Create a mutex lock while we generate the cache
        $this->mutexLock($cache, $cacheKey);

        try {
            // Execute the query
            $statement = $this->prepareStatement($sqlText, $param);
            $this->executeCursor($statement);
            $iterator = $this->getDriverIterator($statement, preFetch: $preFetch, entityClass: $entityClass, entityTransformer: $entityTransformer);

            // Cache the results
            $arrayData = [];
            foreach ($iterator as $item) {
                $arrayData[] = $item->toArray();
            }
            $cache->set($cacheKey, $arrayData, $sql->getCacheTime() ?? 60);

            // Now we can create a new iterator from the cached data
            return $this->getIteratorFromCache($cache, $cacheKey, $entityClass);
        } finally {
            // Always release the mutex lock
            $this->mutexRelease($cache, $cacheKey);
        }
    }

    /**
     * Get iterator from cache
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @param string|null $entityClass
     * @return GenericIterator
     * @throws FileException
     * @throws InvalidArgumentException
     */
    protected function getIteratorFromCache(
        CacheInterface $cache,
        string         $cacheKey,
        ?string        $entityClass = null
    ): GenericIterator
    {
        // Get data from cache
        $data = $cache->get($cacheKey);

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
     * Check if a mutex lock exists for the cache key
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function mutexIsLocked(CacheInterface $cache, string $cacheKey): mixed
    {
        return $cache->get($cacheKey . ".lock", false);
    }

    /**
     * Create a mutex lock for the cache key
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @throws InvalidArgumentException
     */
    protected function mutexLock(CacheInterface $cache, string $cacheKey): void
    {
        $cache->set($cacheKey . ".lock", time(), DateInterval::createFromDateString('5 min'));
    }

    /**
     * Release a mutex lock for the cache key
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @throws InvalidArgumentException
     */
    protected function mutexRelease(CacheInterface $cache, string $cacheKey): void
    {
        $cache->delete($cacheKey . ".lock");
    }
} 