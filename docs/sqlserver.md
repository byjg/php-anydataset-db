---
sidebar_position: 15
---

# Driver: Microsoft SQL Server

There are two Drivers to connect to Microsoft SQL Server.

- **Dblib**: This driver is based on the Sybase protocol. It is a good driver, but it is not maintained anymore.
- **SqlSrv**: This driver is the official driver from Microsoft. It is maintained and has more features than Dblib.

## Dblib

You can check if there is a PHP extension `php_dblib` installed in your system. If you have this extension, you can use the following URI:

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance("dblib://username:password@hostname:port/database");
```

## SqlSrv

You can check if there is a PHP extension `php_sqlsrv` installed in your system. If you have this extension, you can use the following URI:

```php
<?php
$conn = \ByJG\AnyDataset\Db\Factory::getDbInstance("sqlsrv://username:password@hostname:port/database");
```


## The  Date format Issues

Date has the format `"Jul 27 2016 22:00:00.860"`. The solution is:

Follow the solution:
[https://stackoverflow.com/questions/38615458/freetds-dateformat-issues](https://stackoverflow.com/questions/38615458/freetds-dateformat-issues)
