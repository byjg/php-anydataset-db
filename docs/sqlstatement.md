---
sidebar_position: 3
---

# SQL Statement

The `SqlStatement` class provides an abstraction for SQL queries in the database, allowing you to prepare SQL
statements once and execute them multiple times with different parameters.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlStatement;

// Create a database connection
$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");

// Create an SQL statement
$sql = new SqlStatement("select * from table where field = :param");

// Execute the SQL using the database driver
$iterator = $dbDriver->getIterator($sql, ['param' => 'value']);
```

## The SqlStatement Class

### Constructor

The SqlStatement constructor accepts an SQL string and optional parameters:

```php
$sql = new SqlStatement("select * from table where field = :param");
// OR with parameters
$sql = new SqlStatement("select * from table where field = :param", ['param' => 'value']);
```

### Static Factory Method

Alternatively, you can use the static factory method:

```php
$sql = SqlStatement::from("select * from table where field = :param", ['param' => 'value']);
```

### Parameter Handling

Parameters can be set in multiple ways:

```php
// 1. During construction
$sql = new SqlStatement(
    'SELECT * FROM users WHERE dept_id = :deptId',
    ['deptId' => 5]
);

// 2. Using withParams()
$sql->withParams(['deptId' => 10]);

// 3. When executing with the database driver (overrides any stored parameters)
$iterator = $dbDriver->getIterator($sql, ['deptId' => 15]);

// 4. Accessing stored parameters
$params = $sql->getParams();
```

### Caching Support

The SqlStatement class supports caching query results for improved performance:

```php
<?php
use ByJG\Cache\Psr16\ArrayCacheEngine;

// Create a cache instance (any PSR-16 compliant cache)
$cache = new ArrayCacheEngine();

// Define the SQL statement
$sql = new SqlStatement("select * from table where field = :param");

// Enable caching with a specific key and TTL (time-to-live in seconds)
$sql->withCache($cache, 'my_cache_key', 60);

// Execute the query with the database driver (results will be cached)
$iterator = $dbDriver->getIterator($sql, ['param' => 'value']);

// Disable caching if needed
$sql->withoutCache();
```

## Using SqlStatement with Database Drivers

The SqlStatement object is used with the database driver methods to execute queries:

### SELECT Queries

```php
// Get an iterator to process results
$iterator = $dbDriver->getIterator($sql, ['param' => 'value']);

// Get a single scalar value (first column of first row)
$value = $dbDriver->getScalar($sql, ['param' => 'value']);
```

### Data Modification Queries (INSERT, UPDATE, DELETE)

```php
// Execute a query that doesn't return results
$dbDriver->execute($sql, ['param' => 'value']);

// Insert data and get the last inserted ID
$newId = $dbDriver->executeAndGetId($sql, ['param' => 'value']);
```

## Advantages of Using SqlStatement

- **Parameter Storage**: SQL and its parameters can be stored together in a single object
- **Reusability**: The same SQL statement can be reused with different parameters
- **Caching Support**: Built-in support for caching query results using any PSR-16 compliant cache
- **Consistent API**: Works consistently across all database drivers in the library
- **Mutex Locking**: Prevents cache stampede by implementing mutex locking for cache generation

```php
<?php

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $dbDriver->getIterator($sql, ['param' => 'value']);
```
