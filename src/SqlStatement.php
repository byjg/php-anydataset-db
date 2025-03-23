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

    public function __construct(string $sql)
    {
        $this->sql = $sql;
        $this->cache = null;
        $this->cacheTime = null;
        $this->cacheKey = null;
    }

    public static function from(string $sql): static
    {
        return new static($sql);
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
     * @param DbDriverInterface $dbDriver The database driver
     * @param array|null $param Parameters for the SQL query
     * @param int $preFetch Number of rows to prefetch
     * @param string|null $entityClass Optional entity class name to return rows as objects
     * @param PropertyHandlerInterface|null $entityTransformer Optional transformation function for customizing entity mapping
     * @return GenericIterator The iterator containing the results
     */
    public function getIterator(
        DbDriverInterface         $dbDriver,
        ?array                    $param = [],
        int                       $preFetch = 0,
        ?string                   $entityClass = null,
        ?PropertyHandlerInterface $entityTransformer = null
    ): GenericIterator
    {
        return $dbDriver->getIterator($this, $param, $preFetch, $entityClass, $entityTransformer);
    }

    /**
     * Get a scalar value from the database by executing this SQL statement
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $array
     * @return mixed
     */
    public function getScalar(DbDriverInterface $dbDriver, ?array $array = null): mixed
    {
        return $dbDriver->getScalar($this, $array);
    }

    /**
     * Execute this SQL statement on the database
     *
     * @param DbDriverInterface $dbDriver
     * @param array|null $array
     */
    public function execute(DbDriverInterface $dbDriver, ?array $array = null): void
    {
        $dbDriver->execute($this, $array);
    }
}