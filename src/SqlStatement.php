<?php

namespace ByJG\AnyDataset\Db;

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

}