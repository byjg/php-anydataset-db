# Cache results

You can easily cache your results to speed up the results of long queries;
You need to add to your project an implementation of PSR-16. We suggested you add "byjg/cache".

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');
$cache = new \ByJG\Cache\Psr16\ArrayCacheEngine()

// Query using the PSR16 cache interface.
// If not exists, will cache. If exists will get from cache.
$iterator = $dbDriver>getIterator('select * from table where field = :param', ['param' => 'value'], $cache, 60);
```
