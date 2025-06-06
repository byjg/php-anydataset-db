---
sidebar_position: 6
---

# Load Balancing

The API supports connection load balancing, connection pooling, and persistent connections.

The `Route` class, an implementation of the `DbDriverInterface`, provides routing capabilities.
You can define routes, and the system will automatically select the appropriate `DbDriver` based on your route
definitions.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Route;
use ByJG\AnyDataset\Db\Factory;

$dbDriver = new Route();

// Define the available connections (even different databases)
$dbDriver
    ->addDbDriverInterface('route1', 'sqlite:///tmp/a.db')
    ->addDbDriverInterface('route2', 'sqlite:///tmp/b.db')
    ->addDbDriverInterface('route3', 'sqlite:///tmp/c.db')
;

// Define the route
$dbDriver
    ->addRouteForWrite('route1')
    ->addRouteForRead('route2', 'mytable')
    ->addRouteForRead('route3')
;

// Query the database
$iterator = $dbDriver->getIterator('select * from mytable'); // Will select route2
$iterator = $dbDriver->getIterator('select * from othertable'); // Will select route3
$dbDriver->execute('insert into table (a) values (1)'); // Will select route1;
```  

## Available Route Types

| Method                                          | Description                                                                                        |
|-------------------------------------------------|----------------------------------------------------------------------------------------------------|
| `addRouteForWrite($routeName, $table = null)`   | Routes any `INSERT`, `UPDATE`, or `DELETE` operation. An optional specific table can be specified. |
| `addRouteForRead($routeName, $table = null)`    | Routes any `SELECT` operation. An optional specific table can be specified.                        |
| `addRouteForInsert($routeName, $table = null)`  | Routes any `INSERT` operation. An optional specific table can be specified.                        |
| `addRouteForDelete($routeName, $table = null)`  | Routes any `DELETE` operation. An optional specific table can be specified.                        |
| `addRouteForUpdate($routeName, $table = null)`  | Routes any `UPDATE` operation. An optional specific table can be specified.                        |
| `addRouteForFilter($routeName, $field, $value)` | Routes based on `WHERE` clauses with specific `FIELD = VALUE` conditions.                          |
| `addCustomRoute($routeName, $regEx)`            | Routes based on a custom regular expression.                                                       |

## Advanced Examples

### Master-Slave Configuration

A common use case is to have a master database for write operations and multiple slave databases for read operations:

```php
<?php
use ByJG\AnyDataset\Db\Route;

$dbDriver = new Route();

// Define connections
$dbDriver
    ->addDbDriverInterface('master', 'mysql://user:pass@master-host/database')
    ->addDbDriverInterface('slave1', 'mysql://user:pass@slave1-host/database')
    ->addDbDriverInterface('slave2', 'mysql://user:pass@slave2-host/database')
;

// Route all write operations to master
$dbDriver->addRouteForWrite('master');

// Route read operations to slaves (round-robin)
$dbDriver
    ->addDbDriverInterface('slaves', [
        'mysql://user:pass@slave1-host/database',
        'mysql://user:pass@slave2-host/database'
    ])
    ->addRouteForRead('slaves')
;
```

### Table-Specific Routing

You can route queries for specific tables to different databases:

```php
<?php
use ByJG\AnyDataset\Db\Route;

$dbDriver = new Route();

// Define connections
$dbDriver
    ->addDbDriverInterface('db1', 'mysql://user:pass@host1/database')
    ->addDbDriverInterface('db2', 'mysql://user:pass@host2/database')
    ->addDbDriverInterface('db3', 'mysql://user:pass@host3/database')
;

// Route queries for specific tables
$dbDriver
    ->addRouteForRead('db1', 'users')
    ->addRouteForRead('db2', 'products')
    ->addRouteForRead('db3', 'orders')
    ->addRouteForWrite('db1', 'users')
    ->addRouteForWrite('db2', 'products')
    ->addRouteForWrite('db3', 'orders')
;
```

### Filtering Based on WHERE Clause

You can route queries based on values in the WHERE clause:

```php
<?php
use ByJG\AnyDataset\Db\Route;

$dbDriver = new Route();

// Define connections for different regions
$dbDriver
    ->addDbDriverInterface('us_db', 'mysql://user:pass@us-host/database')
    ->addDbDriverInterface('eu_db', 'mysql://user:pass@eu-host/database')
    ->addDbDriverInterface('asia_db', 'mysql://user:pass@asia-host/database')
;

// Route based on region in WHERE clause
$dbDriver
    ->addRouteForFilter('us_db', 'region', 'US')
    ->addRouteForFilter('eu_db', 'region', 'EU')
    ->addRouteForFilter('asia_db', 'region', 'ASIA')
;

// Queries will be routed based on the region value
$usData = $dbDriver->getIterator("SELECT * FROM customers WHERE region = 'US'");  // Uses us_db
$euData = $dbDriver->getIterator("SELECT * FROM customers WHERE region = 'EU'");  // Uses eu_db
```

### Custom Routing with Regular Expressions

For more complex routing needs, you can use custom regular expressions:

```php
<?php
use ByJG\AnyDataset\Db\Route;

$dbDriver = new Route();

// Define connections
$dbDriver
    ->addDbDriverInterface('analytics', 'mysql://user:pass@analytics-host/database')
    ->addDbDriverInterface('transactional', 'mysql://user:pass@transactional-host/database')
;

// Route complex queries to analytics database
$dbDriver->addCustomRoute('analytics', '.*GROUP\s+BY.*HAVING.*');

// Route other queries to transactional database
$dbDriver->addRouteForRead('transactional');
$dbDriver->addRouteForWrite('transactional');
```

## Implementation Details

The `Route` class works by analyzing the SQL query and matching it against the defined routes. When a match is found,
the corresponding `DbDriverInterface` is used to execute the query.

The routing process follows these steps:

1. The SQL query is passed to the `matchRoute` method.
2. The method iterates through all defined routes and checks if the query matches any of them.
3. If a match is found, the corresponding `DbDriverInterface` is returned.
4. If no match is found, a `RouteNotMatchedException` is thrown.

All `DbDriverInterface` methods are implemented in the `Route` class and delegate to the matched driver, making the
routing process transparent to the application code.
