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

- `$cache`: A PSR-16 compliant cache implementation (implementing `Psr\SimpleCache\CacheInterface`)
- `$cacheKey`: A unique key prefix for the cache entry
- `$cacheTime`: Time-to-live in seconds (default: 60)

### withoutCache

Disables caching for the SQL statement.

```php
$sql->withoutCache();
```

## Accessor Methods

The `SqlStatement` class also provides getter methods to access cache information:

```php
// Get the cache implementation
$cacheInstance = $sql->getCache();

// Get the cache TTL
$ttl = $sql->getCacheTime();

// Get the cache key prefix
$key = $sql->getCacheKey();
```

## Implementation Details

The caching mechanism is implemented in the `SqlStatement` class. When caching is enabled:

1. A unique cache key is generated based on the base key and the query parameters:
   ```php
   // Internal key generation (simplified)
   $cacheKey = $this->cacheKey . ':' . md5(json_encode($param));
   ```

2. Before executing the query, the cache is checked for an existing entry.
3. If a cache entry exists, the results are returned directly from the cache.
4. If no cache entry exists, the query is executed, and the results are stored in the cache.

## Mutex Locking Mechanism

To prevent cache stampede (when multiple processes try to generate the same cache entry simultaneously), the library
implements a mutex locking mechanism:

1. Before regenerating a cache entry, the system checks for an existing lock:
   ```php
   $lock = $this->mutexIsLocked($cacheKey);
   ```

2. If a lock exists, the process waits briefly and tries again:
   ```php
   if ($lock !== false) {
       usleep(200); // Wait 200 microseconds
       continue;
   }
   ```

3. If no lock exists, the process acquires a lock, generates the cache, and then releases the lock:
   ```php
   $this->mutexLock($cacheKey);
   try {
       // Generate cache content
   } finally {
       $this->mutexRelease($cacheKey);
   }
   ```

4. The lock uses the same cache implementation with a `.lock` suffix:
   ```php
   $this->cache->set($cacheKey . ".lock", time(), DateInterval::createFromDateString('5 min'));
   ```

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

## Cache Key Generation

The actual cache key used is a combination of:

1. The base key you provide (`$cacheKey`)
2. A colon (`:`)
3. An MD5 hash of the JSON-encoded parameters

For example:

```php
// If you specify this cache key and parameters:
$sql->withCache($cache, 'user_list', 60);
$iterator = $sql->getIterator($dbDriver, ['status' => 'active', 'role' => 'admin']);

// The actual cache key used internally will be:
// 'user_list:' + md5(json_encode(['status' => 'active', 'role' => 'admin']))
// Something like: 'user_list:a1b2c3d4e5f6...'
```

Note: Parameters are sorted by key before hashing to ensure consistent cache keys regardless of parameter order.

## Notes

- **One cache entry per parameter set:** A separate cache entry will be created for each unique set of parameters.  
  For example:
  - `['param' => 'value']` and `['param' => 'value2']` will result in two distinct cache entries.

- **Key uniqueness:** If you use the same cache key for different SQL statements, they will not be differentiated. This
  may lead to unexpected results. Always use distinct base cache keys for different queries.

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
