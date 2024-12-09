---
sidebar_position: 6
---

# Load Balancing

The API supports connection load balancing, connection pooling, and persistent connections.

The `Route` class, an implementation of the `DbDriverInterface`, provides routing capabilities.
You can define routes, and the system will automatically select the appropriate `DbDriver` based on your route
definitions.

Example:

```php
<?php
$dbDriver = new \ByJG\AnyDataset\Db\Route();

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
