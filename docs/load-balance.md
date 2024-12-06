---
sidebar_position: 6
---

# Load balancing

The API have support for connection load balancing, connection pooling and persistent connection.

There is the Route class an DbDriverInterface implementation with route capabilities. Basically you have to define
the routes and the system will choose the proper DbDriver based on your route definition.

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

The possible route types are:

| Method                                        | Description                                                   |
|-----------------------------------------------|---------------------------------------------------------------|
| addRouteForWrite($routeName, $table = null)   | Filter any insert, update and delete. Optional specific table |
| addRouteForRead($routeName, $table = null)    | Filter any select. Optional specific table                    |
| addRouteForInsert($routeName, $table = null)  | Filter any insert. Optional specific table                    |
| addRouteForDelete($routeName, $table = null)  | Filter any delete. Optional specific table                    |
| addRouteForUpdate($routeName, $table = null)  | Filter any update. Optional specific table                    |
| addRouteForFilter($routeName, $field, $value) | Filter any WHERE clause based on FIELD = VALUE                |
| addCustomRoute($routeName, $regEx)            | Filter by a custom regular expression.                        |

