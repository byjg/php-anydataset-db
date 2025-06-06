---
sidebar_position: 4
---

# Cache Results

You can easily cache query results to improve performance, especially for long-running queries.
To enable caching, you need to include a PSR-16 compliant caching library in your project.
We recommend using the `byjg/cache` library.

Additionally, you must use the `SqlStatement` class to prepare the query and cache the results.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\ArrayCacheEngine;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$cache = new ArrayCacheEngine();

// Define the SqlStatement object
$sql = new SqlStatement("select * from table where field = :param");
$sql->withCache($cache, 'my_cache_key', 60);

// Query using the PSR16 cache interface.
// If not exists, will cache. If exists will get from cache.
$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```

## Cache Methods

### withCache

Enables caching for the SQL statement.

```php
$sql->withCache($cache, 'my_cache_key', 60);
```

Parameters:

- `$cache`: A PSR-16 compliant cache implementation
- `$cacheKey`: A unique key for the cache entry
- `$cacheTime`: Time-to-live in seconds (default: 60)

### withoutCache

Disables caching for the SQL statement.

```php
$sql->withoutCache();
```

## Implementation Details

The caching mechanism is implemented in the `SqlStatement` class and uses the `DbCacheTrait` trait. When caching is
enabled:

1. A unique cache key is generated based on the SQL statement and the parameters.
2. Before executing the query, the cache is checked for an existing entry.
3. If a cache entry exists, the results are returned directly from the cache.
4. If no cache entry exists, the query is executed, and the results are stored in the cache.
5. A mutex locking mechanism is used to prevent cache stampede (multiple processes trying to generate the same cache
   entry simultaneously).

## Advanced Examples

### Using Different Cache Backends

You can use any PSR-16 compliant cache implementation:

```php
<?php
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\FileSystemCacheEngine;
use ByJG\Cache\Psr16\MemcachedEngine;
use ByJG\Cache\Psr16\RedisCacheEngine;

// File system cache
$fileCache = new FileSystemCacheEngine('/path/to/cache');
$sql = new SqlStatement("select * from table");
$sql->withCache($fileCache, 'file_cache_key', 300); // 5 minutes

// Memcached
$memcached = new MemcachedEngine('localhost');
$sql = new SqlStatement("select * from table");
$sql->withCache($memcached, 'memcached_key', 600); // 10 minutes

// Redis
$redis = new RedisCacheEngine('localhost');
$sql = new SqlStatement("select * from table");
$sql->withCache($redis, 'redis_key', 1800); // 30 minutes
```

### Caching Different Queries

Each SQL statement can have its own cache configuration:

```php
<?php
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\Cache\Psr16\ArrayCacheEngine;

$cache = new ArrayCacheEngine();

// Cache frequently accessed data for longer periods
$userQuery = new SqlStatement("select * from users where status = :status");
$userQuery->withCache($cache, 'active_users', 3600); // 1 hour
$activeUsers = $userQuery->getIterator($dbDriver, ['status' => 'active']);

// Cache volatile data for shorter periods
$orderQuery = new SqlStatement("select * from orders where date > :date");
$orderQuery->withCache($cache, 'recent_orders', 300); // 5 minutes
$recentOrders = $orderQuery->getIterator($dbDriver, ['date' => '2023-01-01']);
```

## Notes

- **One cache entry per parameter set:** A separate cache entry will be created for each unique set of parameters.  
  For example:
  - `['param' => 'value']` and `['param' => 'value2']` will result in two distinct cache entries.

- **Key uniqueness:** If you use the same cache key for different SQL statements, they will not be differentiated. This
  may lead to unexpected results.

- **Cache invalidation:** The library does not automatically invalidate cache entries when data changes. You need to
  manage cache invalidation yourself by:
  1. Using appropriate TTL values
  2. Using different cache keys for different queries
  3. Manually clearing the cache when data changes

```php
<?php
// Manually clear cache entries when data changes
$cache->delete('my_cache_key');
```

- **Mutex locking:** The library uses a mutex locking mechanism to prevent cache stampede. This ensures that only one
  process generates the cache entry while others wait for it to be completed.
