---
sidebar_position: 4
---

# Cache results

You can easily cache your results to speed up the results of long queries;
You need to add to your project an implementation of PSR-16. We suggested you add "byjg/cache".

Also, you need to use the `SqlStatement` class to prepare the query and cache the results.

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

**NOTES**

- It will be saved one cache entry for each different parameters.
  e.g. `['param' => 'value']` and `['param' => 'value2']` will have one entry for each result.

- If you use the same key for different sql statements it will not differentiate one from another and
  you can get unexpected results