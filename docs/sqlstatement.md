---
sidebar_position: 3
---

# SQL Statement

The `SqlStatement` class provides an abstraction for executing SQL queries on the database. It allows you to prepare SQL
statements once and execute them multiple times with different parameters.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlStatement;

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```

## Available Methods

### Constructor

```php
$sql = new SqlStatement("select * from table where field = :param");
```

### getIterator

Executes a SELECT query and returns an iterator to navigate through the results.

```php
$iterator = $sql->getIterator($dbDriver, ['param' => 'value'], $preFetch = 0);
```

Parameters:

- `$dbDriver`: The database driver instance
- `$param`: An associative array of parameters to bind to the query
- `$preFetch`: Number of records to pre-fetch (default: 0)

### getScalar

Executes a query and returns a single value (first column of the first row).

```php
$value = $sql->getScalar($dbDriver, ['param' => 'value']);
```

### execute

Executes a non-query SQL statement (INSERT, UPDATE, DELETE).

```php
$sql->execute($dbDriver, ['param' => 'value']);
```

## Caching Support

The SqlStatement class supports caching query results for improved performance.

```php
<?php
use ByJG\Cache\Psr16\ArrayCacheEngine;

// Create a cache instance (any PSR-16 compliant cache)
$cache = new ArrayCacheEngine();

// Define the SQL statement
$sql = new SqlStatement("select * from table where field = :param");

// Enable caching with a specific key and TTL (time-to-live in seconds)
$sql->withCache($cache, 'my_cache_key', 60);

// Execute the query (results will be cached)
$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);

// Disable caching if needed
$sql->withoutCache();
```

## Advantages of Using SqlStatement

- **Reusability**: The same SQL statement can be reused with different parameters, reducing the overhead of preparing
  new queries.
- **Performance**: Reusing statements helps optimize performance by leveraging caching mechanisms.
- **Caching Support**: Queries can be cached for even faster retrieval (see [Cache results](cache.md)).
- **Mutex Locking**: Prevents cache stampede by implementing mutex locking when multiple requests try to generate the
  same cached content simultaneously.

```php
<?php

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $sql->getIterator($dbDriver, ['param' => 'value']);
```
