<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use DateInterval;
use InvalidArgumentException;
use PDOStatement;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * DatabaseExecutor handles high-level database operations (queries, commands)
 * while delegating low-level operations (connections, statement preparation) to DbDriverInterface
 */
class DatabaseExecutor implements Interfaces\DbTransactionInterface
{
    protected DbDriverInterface $driver;

    /**
     * @param DbDriverInterface $driver The database driver to use for low-level operations
     */
    public function __construct(DbDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Static factory method to create a DatabaseExecutor
     *
     * @param DbDriverInterface $driver The database driver to use
     * @return static
     */
    public static function using(DbDriverInterface $driver): static
    {
        return new static($driver);
    }

    /**
     * Get the underlying database driver
     *
     * @return DbDriverInterface
     */
    public function getDriver(): DbDriverInterface
    {
        return $this->driver;
    }

    /**
     * Execute a SQL statement and return an iterator over the results
     *
     * @param string|SqlStatement $sql The SQL statement to execute
     * @param array|null $params Parameters for the SQL query
     * @param int $preFetch Number of rows to prefetch
     * @return GenericDbIterator|GenericIterator The iterator containing the results
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws PsrInvalidArgumentException
     * @throws XmlUtilException
     */
    public function getIterator(string|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator|GenericIterator
    {
        // Convert string to SqlStatement if needed
        if (is_string($sql)) {
            $sql = new SqlStatement($sql, $params);
        } else {
            // If parameters were provided, they override any in the SqlStatement
            if ($params !== null) {
                $sql = $sql->withParams($params);
            }
        }

        $cache = $sql->getCache();
        $sqlText = $sql->getSql();
        $params = $sql->withParams($params)->getParams();

        // If no cache is configured, directly execute the query
        if (empty($cache)) {
            $statement = $this->driver->prepareStatement($sqlText, $params);
            $this->driver->executeCursor($statement);
            return $this->driver->getDriverIterator($statement, $preFetch, $sql->getEntityClass(), $sql->getEntityTransformer());
        }

        // Cache is configured - try to get from cache first
        ksort($params);
        $cacheKey = $sql->getCacheKey() . ':' . md5(json_encode($params));

        if ($cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cache, $cacheKey);
        }

        // Wait until no other process is generating this cache
        while ($this->mutexIsLocked($cache, $cacheKey) !== false) {
            usleep(100000); // 100ms
        }

        // Double-check if cache is available after waiting
        if ($cache->has($cacheKey)) {
            return $this->getIteratorFromCache($cache, $cacheKey);
        }

        // Create a mutex lock while we generate the cache
        $this->mutexLock($cache, $cacheKey);

        try {
            // Execute the query
            $statement = $this->driver->prepareStatement($sqlText, $params);
            $this->driver->executeCursor($statement);
            $iterator = $this->driver->getDriverIterator($statement, preFetch: $preFetch, entityClass: $sql->getEntityClass(), entityTransformer: $sql->getEntityTransformer());

            // Cache the results
            $arrayData = [];
            foreach ($iterator as $item) {
                $arrayData[] = $item->toArray();
            }
            $cache->set($cacheKey, $arrayData, $sql->getCacheTime() ?? 60);

            // Now we can create a new iterator from the cached data
            return $this->getIteratorFromCache($cache, $cacheKey);
        } finally {
            // Always release the mutex lock
            $this->mutexRelease($cache, $cacheKey);
        }
    }

    /**
     * Execute a SQL statement and return a single scalar value
     *
     * @param string|SqlStatement $sql The SQL statement to execute
     * @param array|null $array Parameters for the SQL query
     * @return mixed The scalar value from the first column of the first row, or false if no results
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws PsrInvalidArgumentException
     */
    public function getScalar(string|SqlStatement $sql, ?array $array = null): mixed
    {
        $iterator = $this->getIterator($sql, $array);

        if (!$iterator->valid()) {
            return false;
        }

        $row = $iterator->current();
        $rowArray = $row->toArray();

        if (empty($rowArray)) {
            return false;
        }

        return array_values($rowArray)[0];
    }

    /**
     * Get all field names from a table
     *
     * @param string $tablename The table name
     * @return array Array of field names
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws PsrInvalidArgumentException
     */
    public function getAllFields(string $tablename): array
    {
        // Use the helper method which is driver-specific and optimized
        return $this->getAllFieldsFromStatement($tablename);
    }

    /**
     * Get all field names from a table by executing a query
     *
     * @param string $tablename The table name
     * @return array Array of field names
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    protected function getAllFieldsFromStatement(string $tablename): array
    {
        // Build a SQL query that returns 0 rows but includes all columns
        // Use the driver's helper to apply the correct syntax (LIMIT vs TOP vs WHERE 1=0)
        $sql = "SELECT * FROM $tablename";
        $helper = $this->driver->getDbHelper();

        // Use TOP or LIMIT depending on what the database supports
        if ($helper->hasTop()) {
            $sql = $helper->top($sql, 0);
        } elseif ($helper->hasLimit()) {
            $sql = $helper->limit($sql, 0, 0);
        } else {
            // Fallback to WHERE 1=0 for databases that don't support TOP or LIMIT
            $sql .= " WHERE 1=0";
        }

        $statement = $this->driver->prepareStatement($sql, []);
        $this->driver->executeCursor($statement);

        // For PDO-based drivers, we can get column metadata directly from the statement
        if ($statement instanceof PDOStatement) {
            $fields = [];
            $columnCount = $statement->columnCount();
            for ($i = 0; $i < $columnCount; $i++) {
                $meta = $statement->getColumnMeta($i);
                $fields[] = strtolower($meta['name']);
            }
            return $fields;
        }

        // For OCI8 and other drivers, we need to use their specific methods
        // Fall back to getting an iterator and extracting field names
        $iterator = $this->driver->getDriverIterator($statement);

        // Force a fetch to get the structure
        $iterator->rewind();
        if ($iterator->valid()) {
            $row = $iterator->current();
            return array_keys($row->toArray());
        }

        // Final fallback: return empty array
        return [];
    }

    /**
     * Execute a SQL statement without returning results
     *
     * @param string|SqlStatement $sql The SQL statement to execute
     * @param array|null $array Parameters for the SQL query
     * @return bool True on success
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function execute(string|SqlStatement $sql, ?array $array = null): bool
    {
        if (is_string($sql)) {
            $sql = new SqlStatement($sql, $array);
        } else {
            $sql = $sql->withParams($array);
        }

        $statement = $this->driver->prepareStatement($sql->getSql(), $sql->getParams());
        $this->driver->executeCursor($statement);
        $this->driver->processMultiRowset($statement);

        return true;
    }

    /**
     * Execute a SQL INSERT statement and return the generated ID
     *
     * @param string|SqlStatement $sql The SQL statement to execute
     * @param array|null $array Parameters for the SQL query
     * @return mixed The generated ID
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     */
    public function executeAndGetId(string|SqlStatement $sql, ?array $array = null): mixed
    {
        if ($sql instanceof SqlStatement) {
            $sql = $sql->withParams($array);
            return $this->driver->getDbHelper()->executeAndGetInsertedId($this->driver, $sql->getSql(), $sql->getParams());
        }

        return $this->driver->getDbHelper()->executeAndGetInsertedId($this->driver, $sql, $array);
    }

    /**
     * Begin a database transaction
     *
     * @param IsolationLevelEnum|null $isolationLevel The isolation level for the transaction
     * @param bool $allowJoin Whether to allow joining an existing transaction
     * @return void
     */
    public function beginTransaction(?IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void
    {
        $this->driver->beginTransaction($isolationLevel, $allowJoin);
    }

    /**
     * Commit the current transaction
     *
     * @return void
     */
    public function commitTransaction(): void
    {
        $this->driver->commitTransaction();
    }

    /**
     * Rollback the current transaction
     *
     * @return void
     */
    public function rollbackTransaction(): void
    {
        $this->driver->rollbackTransaction();
    }

    /**
     * Check if there is an active transaction
     *
     * @return bool
     */
    public function hasActiveTransaction(): bool
    {
        return $this->driver->hasActiveTransaction();
    }

    /**
     * Get the active isolation level
     *
     * @return IsolationLevelEnum|null
     */
    public function activeIsolationLevel(): ?IsolationLevelEnum
    {
        return $this->driver->activeIsolationLevel();
    }

    /**
     * Get the number of remaining commits needed
     *
     * @return int
     */
    public function remainingCommits(): int
    {
        return $this->driver->remainingCommits();
    }

    /**
     * Require that a transaction is active (throw exception if not)
     *
     * @return void
     */
    public function requiresTransaction(): void
    {
        $this->driver->requiresTransaction();
    }

    /**
     * Get iterator from cache
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @return GenericDbIterator|GenericIterator
     * @throws FileException
     * @throws PsrInvalidArgumentException
     * @throws XmlUtilException
     */
    protected function getIteratorFromCache(
        CacheInterface $cache,
        string         $cacheKey
    ): GenericDbIterator|GenericIterator
    {
        // Get data from cache
        $data = $cache->get($cacheKey);

        // Use AnyDataset to get the raw data
        $anyDataset = new AnyDataset($data);
        return $anyDataset->getIterator();
    }

    /**
     * Check if a mutex lock exists for the cache key
     *
     * @param CacheInterface $cache
     * @param string $cacheKey
     * @return mixed
     * @throws PsrInvalidArgumentException
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
