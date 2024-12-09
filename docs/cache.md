---
sidebar_position: 4
---

# Cache Results

You can easily cache query results to improve performance, especially for long-running queries.
To enable caching, you need to include a PSR-16 compliant caching library in your project.
We recommend using the `byjg/cache` library.

Additionally, you must use the `SqlStatement` class to prepare the query and cache the results.

```php
<?php
$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$cache = new \ByJG\Cache\Psr16\ArrayCacheEngine()

// Define the SqlStatement object
$sql = new SqlStatement("select * from table where field = :param");
$sql->withCache($cache, 'my_cache_key', 60);

// Query using the PSR16 cache interface.
// If not exists, will cache. If exists will get from cache.
$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```

## Notes

- **One cache entry per parameter set:** A separate cache entry will be created for each unique set of parameters.  
  For example:
  - `['param' => 'value']` and `['param' => 'value2']` will result in two distinct cache entries.

- **Key uniqueness:** If you use the same cache key for different SQL statements, they will not be differentiated. This
  may lead to unexpected results.
