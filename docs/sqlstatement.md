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

// 2. Using withParams() - returns a new SqlStatement object with merged parameters
$newSql = $sql->withParams(['deptId' => 10, 'status' => 'active']);
// Original parameters: ['deptId' => 5]
// New parameters: ['deptId' => 10, 'status' => 'active']

// 3. When executing with the database driver (overrides any stored parameters)
$iterator = $dbDriver->getIterator($sql, ['deptId' => 15]);

// 4. Accessing stored parameters
$params = $sql->getParams();

// 5. Removing all parameters
$sqlWithoutParams = $sql->withoutParams();
```

### Immutability

**IMPORTANT**: This class implements the immutability pattern. All modifier methods return a new instance instead of
modifying the original object:

```php
// Original SQL statement
$sql = new SqlStatement("select * from table where field = :param", ['param' => 'value1']);

// Creates a NEW SqlStatement - original remains unchanged
$newSql = $sql->withParams(['param' => 'value2', 'other' => 'data']);

// $sql still has ['param' => 'value1']
// $newSql has ['param' => 'value2', 'other' => 'data']

// Method chaining is possible since each call returns a new object
$anotherSql = $sql->withParams(['status' => 'active'])->withCache($cache, 'cache_key', 120);
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
// Returns a new SqlStatement with caching enabled
$cachedSql = $sql->withCache($cache, 'my_cache_key', 60);

// Execute the query with the database driver (results will be cached)
$iterator = $dbDriver->getIterator($cachedSql, ['param' => 'value']);

// Disable caching if needed - returns a new SqlStatement without caching
$noCacheSql = $cachedSql->withoutCache();
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

## Entity Mapping Support (EntityClass and EntityTransformer)

Starting from this version, SqlStatement can carry entity mapping information so you don't need to pass it every time
you execute the query. This is useful when you reuse the same SqlStatement across your codebase.

- withEntityClass(string $entityClass): Define the target PHP class to map rows into (immutably returns a new
  SqlStatement).
- withEntityTransformer(PropertyHandlerInterface $transformer): Provide a PropertyHandler/mapper to customize
  field-to-property mapping and value transformations (also immutable).
- getEntityClass(): ?string and getEntityTransformer(): ?PropertyHandlerInterface are the corresponding accessors.

Example (basic entity mapping):

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\SqlStatement;

class User 
{ 
    public int $id; 
    public string $name; 
}

$db = Factory::getDbInstance("mysql://user:password@server/schema");

$sql = (new SqlStatement("SELECT id, name FROM users WHERE active = :active", [':active' => true]))
    ->withEntityClass(User::class);

$it = $db->getIterator($sql);
foreach ($it as $row) {
    $user = $row->entity(); // instance of User populated from the row
}
```

Example (with a transformer):

```php
<?php
use ByJG\Serializer\PropertyHandler\PropertyNameMapper;

class DogEntity {
    public int $dogId;
    public string $dogName;
    public string $dogBreed;
    public float $dogWeight;
}

$statement = (new SqlStatement('SELECT id, name, breed, weight FROM Dogs'))
    ->withEntityClass(DogEntity::class)
    ->withEntityTransformer(new PropertyNameMapper([
        'id' => 'dogId',
        'name' => 'dogName',
        'breed' => 'dogBreed',
        'weight' => 'dogWeight',
    ]));

$iterator = $db->getIterator($statement);
foreach ($iterator as $row) {
    $dog = $row->entity(); // DogEntity with transformed property names
}
```

Notes:

- SqlStatement remains immutable; each with* method returns a new instance.
- See docs/entity.md for a full explanation of entity mapping and transformers.

## Advantages of Using SqlStatement

- **Parameter Storage**: SQL and its parameters can be stored together in a single object
- **Reusability**: The same SQL statement can be reused with different parameters
- **Immutability**: All modifier methods return new objects, preserving the original
- **Parameter Merging**: Easily combine parameters from different sources
- **Caching Support**: Built-in support for caching query results using any PSR-16 compliant cache
- **Consistent API**: Works consistently across all database drivers in the library
- **Mutex Locking**: Prevents cache stampede by implementing mutex locking for cache generation

```php
<?php

$dbDriver = Factory::getDbInstance("mysql://user:password@server/schema");
$sql = new SqlStatement("select * from table where field = :param");

$iterator = $dbDriver->getIterator($sql, ['param' => 'value']);
```
