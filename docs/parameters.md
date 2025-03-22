---
sidebar_position: 11
---

# Passing Parameters to PDODriver

You can pass parameters directly to the PDODriver by adding query parameters to the connection string.

## Using PDO Constants

```php
<?php
use ByJG\Util\Uri;
use ByJG\AnyDataset\Db\Factory;
use PDO;

$uri = Uri::getInstanceFromString("mysql://root:password@localhost")
    ->withQueryKeyValue(PDO::MYSQL_ATTR_COMPRESS, 1);
$db = Factory::getDbInstance($uri);
```

## Special Parameters

AnyDatasetDB has some special parameters defined in the `DbPdoDriver` class:

| Parameter                       | Value     | Description                                                                                                        |
|---------------------------------|-----------|--------------------------------------------------------------------------------------------------------------------|
| `DbPdoDriver::DONT_PARSE_PARAM` | any value | If this parameter is set with any value, AnyDataset won't parse the SQL to find the values to bind the parameters. |
| `DbPdoDriver::UNIX_SOCKET`      | path      | PDO will use "unix_socket=" instead of "host=" for the connection.                                                 |

### Example: Skipping SQL parameter parsing

```php
<?php
use ByJG\Util\Uri;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Factory;

$uri = Uri::getInstanceFromString("sqlite:///path/to/database.db")
    ->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");

$db = Factory::getDbInstance($uri);
```

### Example: Using UNIX Socket

```php
<?php
use ByJG\Util\Uri;
use ByJG\AnyDataset\Db\DbPdoDriver;
use ByJG\AnyDataset\Db\Factory;

// Note: there are 3 slashes after the protocol
// The first one is the separator between the protocol and the host
$uri = Uri::getInstanceFromString("mysql:///" . $dbname)
    ->withQueryKeyValue(DbPdoDriver::UNIX_SOCKET, "/run/mysql.sock");

$db = Factory::getDbInstance($uri);
```

## Advanced Constructor Options

When instantiating a database driver directly, you can specify additional options:

```php
<?php
use ByJG\Util\Uri;
use ByJG\AnyDataset\Db\PdoMysql;

$uri = new Uri("mysql://username:password@hostname/database");

// Pre-connection options (applied before connection)
$preOptions = [PDO::ATTR_PERSISTENT => true];

// Post-connection options (applied after connection)
$postOptions = [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true];

// SQL commands to execute after connection
$executeAfterConnect = ["SET NAMES 'utf8'", "SET time_zone = '+00:00'"];

$db = new PdoMysql($uri, $preOptions, $postOptions, $executeAfterConnect);
```
