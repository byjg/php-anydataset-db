<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\Serializer\PropertyHandler\PropertyHandlerInterface;
use Psr\SimpleCache\CacheInterface;

class SqlStatement
{
    protected string $sql;

    protected ?CacheInterface $cache = null;

    protected ?int $cacheTime = null;

    protected ?string $cacheKey = null;

    protected ?array $params = null;

    public function __construct(string $sql, ?array $params = null)
    {
        $this->sql = $sql;
        $this->cache = null;
        $this->cacheTime = null;
        $this->cacheKey = null;
        $this->params = $params;
    }

    public static function from(string $sql, ?array $params = null): static
    {
        return new static($sql, $params);
    }

    public function withCache(CacheInterface $cache, string $cacheKey, int $cacheTime = 60): static
    {
        $this->cache = $cache;
        $this->cacheTime = $cacheTime;
        $this->cacheKey = $cacheKey;
        return $this;
    }

    public function withoutCache(): static
    {
        $this->cache = null;
        $this->cacheTime = null;
        $this->cacheKey = null;
        return $this;
    }

    public function withParams(?array $params): static
    {
        $this->params = $params;
        return $this;
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

    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * Get an iterator for this SQL statement with the specified parameters
     *
     * @param DbDriverInterface $dbDriver The database driver
     * @param array|null $param Parameters for the SQL query (overrides any stored parameters)
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericIterator The iterator containing the results
     */
    public function getIterator(
        DbDriverInterface         $dbDriver,
        ?array $param = null,
        int                       $preFetch = 0,
        ?string                   $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ): GenericIterator
    {
        $useParams = $param ?? $this->params;
        return $dbDriver->getIterator($this, $useParams, $preFetch, $entityClass, $entityTransformer);
    }

    /**
     * Get a scalar value from the database by executing this SQL statement
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $array Parameters for the SQL query (overrides any stored parameters)
     * @return mixed
     */
    public function getScalar(DbDriverInterface $dbDriver, ?array $array = null): mixed
    {
        $useParams = $array ?? $this->params;
        return $dbDriver->getScalar($this, $useParams);
    }

    /**
     * Execute this SQL statement on the database
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $array Parameters for the SQL query (overrides any stored parameters)
     */
    public function execute(DbDriverInterface $dbDriver, ?array $array = null): void
    {
        $useParams = $array ?? $this->params;
        $dbDriver->execute($this, $useParams);
    }

    /**
     * Prepares this SQL statement for execution via the driver and returns the prepared statement
     *
     * @param DbDriverInterface $dbDriver The database driver
     * @param array|null $params Parameters for the SQL query (overrides any stored parameters)
     * @param array|null $cacheInfo Optional cache info for statement reuse
     * @return mixed The prepared statement (driver-specific type)
     */
    public function prepare(DbDriverInterface $dbDriver, ?array $params = null, ?array &$cacheInfo = []): mixed
    {
        $useParams = $params ?? $this->params;
        return $dbDriver->prepareStatement($this->sql, $useParams, $cacheInfo);
    }
}