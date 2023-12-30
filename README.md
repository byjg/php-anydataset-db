# AnyDataset-DB

[![Build Status](https://github.com/byjg/anydataset-db/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/anydataset-db/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](http://opensource.byjg.com)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/anydataset-db/)
[![GitHub license](https://img.shields.io/github/license/byjg/anydataset-db.svg)](https://opensource.byjg.com/opensource/licensing.html)
[![GitHub release](https://img.shields.io/github/release/byjg/anydataset-db.svg)](https://github.com/byjg/anydataset-db/releases/)

Anydataset Database Relational abstraction. Anydataset is an agnostic data source abstraction layer in PHP.

See more about Anydataset [here](https://opensource.byjg.com/anydataset).

## Features

- Connection based on URI
- Support and fix code tricks with several databases (MySQL, PostgresSql, MS SQL Server, etc)
- Natively supports Query Cache by implementing a PSR-6 interface
- Supports Connection Routes based on regular expression against the queries, that's mean a select in a table should be
executed in a database and in another table should be executed in another (even if in different DB)

## Connection Based on URI

The connection string for databases is based on URL.

See below the current implemented drivers:

{:.table}

| Database            | Connection String                                 | Factory                   |
|---------------------|---------------------------------------------------|---------------------------|
| Sqlite              | sqlite:///path/to/file                            | getDbRelationalInstance() |
| MySql/MariaDb       | mysql://username:password@hostname:port/database  | getDbRelationalInstance() |
| Postgres            | psql://username:password@hostname:port/database   | getDbRelationalInstance() |
| Sql Server (DbLib)  | dblib://username:password@hostname:port/database  | getDbRelationalInstance() |
| Sql Server (Sqlsrv) | sqlsrv://username:password@hostname:port/database | getDbRelationalInstance() |
| Oracle (OCI)        | oci://username:password@hostname:port/database    | getDbRelationalInstance() |
| Oracle (OCI8)       | oci8://username:password@hostname:port/database   | getDbRelationalInstance() |
| Generic PDO         | pdo://username:password@pdo_driver?PDO_PARAMETERS | getDbRelationalInstance() |

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance("mysql://root:password@10.0.1.10/myschema");
```

## Examples

### Basic Query

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('mysql://username:password@host/database');
$iterator = $dbDriver->getIterator('select * from table where field = :param', ['param' => 'value']);
foreach ($iterator as $row) {
    // Do Something
    // $row->getField('field');
}
```

### Updating in Relational databases

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('mysql://username:password@host/database');
$dbDriver->execute(
    'update table set other = :value where field = :param',
    [
        'value' => 'othervalue',
        'param' => 'value of param'
    ]
);
```

### Inserting and Get Id

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('mysql://username:password@host/database');
$id = $dbDriver->executeAndGetId(
    'insert into table (field1, field2) values (:param1, :param2)',
    [
        'param1' => 'value1',
        'param2' => 'value2'
    ]
);
```

### Database Transaction

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('mysql://username:password@host/database');
$dbDriver->beginTransaction();

// ... Do your queries

$dbDriver->commitTransaction(); // or rollbackTransaction()
```

### Cache results

You can easily cache your results to speed up the results of long queries; 
You need to add to your project an implementation of PSR-16. We suggested you add "byjg/cache".

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('mysql://username:password@host/database');
$cache = new \ByJG\Cache\Psr16\ArrayCacheEngine()

// Query using the PSR16 cache interface.
// If not exists, will cache. If exists will get from cache.
$iterator = $dbDriver>getIterator('select * from table where field = :param', ['param' => 'value'], $cache, 60);
```

### Passing Parameters to PDODriver

You can pass parameter directly to the PDODriver by adding to the connection string a query parameter with the value. 

e.g.

```php
<?php
$uri = Uri::getInstanceFromUri("mysql://root:password@localhost")
                ->withQueryKeyValue(PDO::MYSQL_ATTR_COMPRESS, 1);
$db = Factory::getDbRelationalInstance($uri);
 ```

*Special Parameters*

AnyDatasetDB has some special parameters:

| Parameter                      | Value     | Description                                                                                                                |
|--------------------------------|-----------|----------------------------------------------------------------------------------------------------------------------------|
| DbPdoDriver::STATEMENT_CACHE   | true      | If this parameter is set with "true", anydataset will cache the last prepared queries.                                     |
| DbPdoDriver::DONT_PARSE_PARAM  | any value | Is this parameter is set with any value, anydataset won't try to parse the SQL to find the values to bind the parameters.  |

e.g.

```php
$uri = Uri::getInstanceFromString("sqlite://" . $this->host)
    ->withQueryKeyValue(DbPdoDriver::STATEMENT_CACHE, "true")
    ->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
```

### Load balance and connection pooling

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

- addRouteForWrite($routeName, $table = null): Filter any insert, update and delete. Optional specific table;
- addRouteForRead($routeName, $table = null): Filter any select. Optional specific table;
- addRouteForInsert($routeName, $table = null): Filter any insert. Optional specific table;
- addRouteForDelete($routeName, $table = null): Filter any delete. Optional specific table;
- addRouteForUpdate($routeName, $table = null): Filter any update. Optional specific table;
- addRouteForFilter($routeName, $field, $value): Filter any WHERE clause based on FIELD = VALUE
- addCustomRoute($routeName, $regEx): Filter by a custom regular expression.

### Connecting To MySQL via SSL

Read [here](https://gist.github.com/byjg/860065a828150caf29c20209ecbd5692) about create SSL mysql

```php
<?php
$sslCa = "/path/to/ca";
$sslKey = "/path/to/Key";
$sslCert = "/path/to/cert";
$sslCaPath = "/path";
$sslCipher = "DHE-RSA-AES256-SHA:AES128-SHA";
$verifySsl = 'false';  // or 'true'. Since PHP 7.1.

$db = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance(
    "mysql://localhost/database?ca=$sslCa&key=$sslKey&cert=$sslCert&capath=$sslCaPath&verifyssl=$verifySsl&cipher=$sslCipher"
);

$iterator = $db->getIterator('select * from table where field = :value', ['value' => 10]);
foreach ($iterator as $row) {
    // Do Something
    // $row->getField('field');
}
```

### Using IteratorFilter in order to get the SQL

You can use the IteratorFilter object to make easier create SQL

```php
<?php
// Create the IteratorFilter instance
$filter = new \ByJG\AnyDataset\Core\IteratorFilter();
$filter->addRelation('field', \ByJG\AnyDataset\Enum\Relation::EQUAL, 10);

// Generate the SQL
$param = [];
$formatter = new \ByJG\AnyDataset\Db\IteratorFilterSqlFormatter();
$sql = $formatter->format(
    $filter->getRawFilters(),
    'mytable',
    $param,
    'field1, field2'
);

// Execute the Query
$iterator = $db->getIterator($sql, $param);
```

### Using IteratorFilter with Literal values

Sometimes you need an argument as a Literal value like a function or an explicit conversion.

In this case you have to create a class that expose the "__toString()" method

```php
<?php

// The class with the "__toString()" exposed
class MyLiteral
{
    //...
    
    public function __toString() {
        return "cast('10' as integer)";
    }
}

// Create the literal instance
$literal = new MyLiteral();

// Using the IteratorFilter:
$filter = new \ByJG\AnyDataset\Core\IteratorFilter();
$filter->addRelation('field', \ByJG\AnyDataset\Core\Enum\Relation::EQUAL, $literal);
```

## Helper - DbFunctions

AnyDataset has a helper `ByJG\AnyDataset\Db\DbFunctionsInterface` that can be adjustable for each Database connection you have. It helps to do specific iteraction like:

- concat($str1, $str2 = null);
- limit($sql, $start, $qty);
- top($sql, $qty);
- hasTop();
- hasLimit();
- sqlDate($format, $column = null);
- toDate($date, $dateFormat);
- fromDate($date, $dateFormat);
- executeAndGetInsertedId(DbDriverInterface $dbdataset, $sql, $param);
- delimiterField($field);
- delimiterTable($table);
- forUpdate($sql);
- hasForUpdate();

It is useful when you are working with different and don't want to hard code the information there. E.g.

```php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('...connection string...');
$dbHelper = $dbDriver->getDbHelper();

// This will return the proper SQL with the TOP 10
// based on the current connection
$sql = $dbHelper->top("select * from foo", 10);

// This will return the proper concatenation operation
// based on the current connection
$concat = $dbHelper->concat("'This is '", "field1", "'concatenated'");


// This will return the proper function to format a date field
// based on the current connection
// These are the formats availables:
// Y => 4 digits year (e.g. 2022)
// y => 2 digits year (e.g. 22)
// M => Month fullname (e.g. January)
// m => Month with leading zero (e.g. 01)
// Q => Quarter
// q => Quarter with leading zero
// D => Day with leading zero (e.g. 01)
// d => Day (e.g. 1)
// h => Hour 12 hours format (e.g. 11)
// H => Hour 24 hours format (e.g. 23)
// i => Minute leading zero
// s => Seconds leading zero
// a => a/p
// A => AM/PM
$date = $dbHelper->sqlDate("d-m-Y H:i", "some_field_date");
$date2 = $dbHelper->sqlDate(DbBaseFunctions::DMYH, "some_field_date"); // Same as above


// This will return the fields with proper field delimiter
// based on the current connection
```

## FreeDTS / DBlib Date format Issues

Date has the format `"Jul 27 2016 22:00:00.860"`. The solution is:

Follow the solution:
<https://stackoverflow.com/questions/38615458/freetds-dateformat-issues>

## Generic PDO configuration

The generic PDO driver uses the format `pdo://username:password@pdo_driver?PDO_ARGUMENTS` and only need to be
used for drivers are not mapped into the `anydataset-db` library.

Let's say we want to connect with the PDO Interbase/Firebase database.
After install the PDO properly we need to create the connection string URI.

According to the Firebase documentation the PDO DSN is:

```text
firebird:User=john;Password=mypass;Database=DATABASE.GDE;DataSource=localhost;Port=3050
```

and adapting to URI style we remove the information about the driver, user and password. Then we have:

```php
$uri = new Uri("pdo://john:mypass@firebird?Database=DATABASE.GDE&DataSource=localhost&Port=3050");
```

Note the configuration:

- The schema for generic PDO is "pdo";
- The host is the PDO driver. In this example is "firebird";
- The PDO arguments are passed as query string. Remember to replace the `;` by `&`.
- The user and password are passed as part of the URI.

## Install

Just type:

```bash
composer require "byjg/anydataset=4.9.*"
```

## Running Unit tests

```bash
vendor/bin/phpunit
```

## Running database tests

Run integration tests require you to have the databases up and running. We provided a basic `docker-compose.yml` and you
can use to start the databases for test.

### unning the databases

```bash
docker-compose up -d postgres mysql
```

### Run the tests

```bash
phpunit testsdb/PdoMySqlTest.php 
phpunit testsdb/PdoSqliteTest.php 
phpunit testsdb/PdoPostgresTest.php 
phpunit testsdb/PdoDblibTest.php 
```

Optionally you can set the host and password used by the unit tests  

```bash
export MYSQL_TEST_HOST=localhost     # defaults to localhost
export MYSQL_PASSWORD=newpassword    # use '.' if want have a null password
export PSQL_TEST_HOST=localhost      # defaults to localhost
export PSQL_PASSWORD=newpassword     # use '.' if want have a null password
export MSSQL_TEST_HOST=localhost     # defaults to localhost
export MSSQL_PASSWORD=Pa55word            
export SQLITE_TEST_HOST=/tmp/test.db      # defaults to /tmp/test.db
```

## Dependencies

```mermaid
flowchart TD
    byjg/anydataset-db --> byjg/anydataset-array
    byjg/anydataset-db --> ext-pdo
    byjg/anydataset-db --> byjg/uri
    byjg/anydataset-db --> psr/cache
    byjg/anydataset-db --> psr/log
```

----
[Open source ByJG](http://opensource.byjg.com)
