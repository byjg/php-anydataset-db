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
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$route = new Route();

// Create database driver instances from connection strings
$driver1 = Factory::getDbInstance('sqlite:///tmp/a.db');
$driver2 = Factory::getDbInstance('sqlite:///tmp/b.db');
$driver3 = Factory::getDbInstance('sqlite:///tmp/c.db');

// Add drivers to routes
$route
    ->addDriver('route1', $driver1)
    ->addDriver('route2', $driver2)
    ->addDriver('route3', $driver3)
;

// Define the routing rules
$route
    ->addRouteForWrite('route1')
    ->addRouteForRead('route2', 'mytable')
    ->addRouteForRead('route3')
;

// Create a DatabaseExecutor wrapper around the route
$executor = DatabaseExecutor::using($route);

// Query the database through the executor
$iterator = $executor->getIterator('select * from mytable'); // Will select route2
$iterator = $executor->getIterator('select * from othertable'); // Will select route3
$executor->execute('insert into table (a) values (1)'); // Will select route1;
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
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$route = new Route();

// Create driver instances
$masterDriver = Factory::getDbInstance('mysql://user:pass@master-host/database');
$slave1Driver = Factory::getDbInstance('mysql://user:pass@slave1-host/database');
$slave2Driver = Factory::getDbInstance('mysql://user:pass@slave2-host/database');

// Add drivers to routes
$route
    ->addDriver('master', $masterDriver)
    ->addDriver('slaves', [$slave1Driver, $slave2Driver])
;

// Route all write operations to master
$route->addRouteForWrite('master');

// Route read operations to slaves (random selection between slave1 and slave2)
$route->addRouteForRead('slaves');

// Create executor to perform queries
$executor = DatabaseExecutor::using($route);

// Example usage
$executor->execute('INSERT INTO users (name) VALUES (?)', ['John']); // Goes to master
$users = $executor->getIterator('SELECT * FROM users')->toArray(); // Goes to slaves
```

### Table-Specific Routing

You can route queries for specific tables to different databases:

```php
<?php
use ByJG\AnyDataset\Db\Route;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$route = new Route();

// Create driver instances
$db1Driver = Factory::getDbInstance('mysql://user:pass@host1/database');
$db2Driver = Factory::getDbInstance('mysql://user:pass@host2/database');
$db3Driver = Factory::getDbInstance('mysql://user:pass@host3/database');

// Add drivers to routes
$route
    ->addDriver('db1', $db1Driver)
    ->addDriver('db2', $db2Driver)
    ->addDriver('db3', $db3Driver)
;

// Route queries for specific tables
$route
    ->addRouteForRead('db1', 'users')
    ->addRouteForRead('db2', 'products')
    ->addRouteForRead('db3', 'orders')
    ->addRouteForWrite('db1', 'users')
    ->addRouteForWrite('db2', 'products')
    ->addRouteForWrite('db3', 'orders')
;

// Create executor wrapper
$executor = DatabaseExecutor::using($route);

// Example usage
$executor->getIterator('SELECT * FROM users'); // Uses db1
$executor->getIterator('SELECT * FROM products'); // Uses db2
$executor->execute('INSERT INTO orders VALUES (...)'); // Uses db3
```

### Filtering Based on WHERE Clause

You can route queries based on values in the WHERE clause:

```php
<?php
use ByJG\AnyDataset\Db\Route;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$route = new Route();

// Create driver instances for different regions
$usDriver = Factory::getDbInstance('mysql://user:pass@us-host/database');
$euDriver = Factory::getDbInstance('mysql://user:pass@eu-host/database');
$asiaDriver = Factory::getDbInstance('mysql://user:pass@asia-host/database');

// Add drivers to routes
$route
    ->addDriver('us_db', $usDriver)
    ->addDriver('eu_db', $euDriver)
    ->addDriver('asia_db', $asiaDriver)
;

// Route based on region in WHERE clause
$route
    ->addRouteForFilter('us_db', 'region', 'US')
    ->addRouteForFilter('eu_db', 'region', 'EU')
    ->addRouteForFilter('asia_db', 'region', 'ASIA')
;

// Create executor wrapper
$executor = DatabaseExecutor::using($route);

// Queries will be routed based on the region value
$usData = $executor->getIterator("SELECT * FROM customers WHERE region = 'US'");  // Uses us_db
$euData = $executor->getIterator("SELECT * FROM customers WHERE region = 'EU'");  // Uses eu_db
```

### Custom Routing with Regular Expressions

For more complex routing needs, you can use custom regular expressions:

```php
<?php
use ByJG\AnyDataset\Db\Route;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

$route = new Route();

// Create driver instances
$analyticsDriver = Factory::getDbInstance('mysql://user:pass@analytics-host/database');
$transactionalDriver = Factory::getDbInstance('mysql://user:pass@transactional-host/database');

// Add drivers to routes
$route
    ->addDriver('analytics', $analyticsDriver)
    ->addDriver('transactional', $transactionalDriver)
;

// Route complex queries to analytics database
$route->addCustomRoute('analytics', '.*GROUP\s+BY.*HAVING.*');

// Route other queries to transactional database
$route->addRouteForRead('transactional');
$route->addRouteForWrite('transactional');

// Create executor wrapper
$executor = DatabaseExecutor::using($route);

// Complex queries go to analytics
$executor->getIterator('SELECT category, COUNT(*) FROM sales GROUP BY category HAVING COUNT(*) > 100');
// Simple queries go to transactional
$executor->getIterator('SELECT * FROM users WHERE id = 1');
```

## Implementation Details

The `Route` class is an implementation of `DbDriverInterface` that works by analyzing SQL queries and routing them to
the appropriate database driver. When a match is found, the corresponding driver is selected to execute the query.

The routing process follows these steps:

1. You create `DbDriverInterface` instances (drivers) from connection strings using `Factory::getDbInstance()`
2. You add these drivers to named routes using the `addDriver()` method
3. You define routing rules (for reads, writes, specific tables, filters, etc.) that map SQL patterns to route names
4. You wrap the Route in a `DatabaseExecutor` using `DatabaseExecutor::using($route)`
5. When a query is executed through the executor, the SQL is passed to the Route's `matchRoute()` method
6. The method iterates through all defined routes and checks if the query matches any of them
7. If a match is found, the corresponding driver is returned and used to execute the query
8. If no match is found, a `RouteNotMatchedException` is thrown

All `DbDriverInterface` methods are implemented in the `Route` class and delegate to the matched driver, making the
routing process transparent to the application code.

### Load Balancing

When you add multiple drivers to a single route (by passing an array), the `Route` class will randomly select one of
them for each query, providing simple load balancing across multiple database servers.
