# Passing Parameters to PDODriver

You can pass parameter directly to the PDODriver by adding to the connection string a query parameter with the value.

e.g.

```php
<?php
$uri = Uri::getInstanceFromUri("mysql://root:password@localhost")
                ->withQueryKeyValue(PDO::MYSQL_ATTR_COMPRESS, 1);
$db = Factory::getDbRelationalInstance($uri);
 ```

## Special Parameters

AnyDatasetDB has some special parameters:

| Parameter                      | Value     | Description                                                                                                               |
|--------------------------------|-----------|---------------------------------------------------------------------------------------------------------------------------|
| DbPdoDriver::STATEMENT_CACHE   | true      | If this parameter is set with "true", anydataset will cache the last prepared queries.                                    |
| DbPdoDriver::DONT_PARSE_PARAM  | any value | Is this parameter is set with any value, anydataset won't try to parse the SQL to find the values to bind the parameters. |
| DbPdoDriver::UNIX_SOCKET       | path      | PDO will use "unix_socket=" instead of "host=".                                                                           |
e.g.

```php
$uri = Uri::getInstanceFromString("sqlite://" . $this->host)
    ->withQueryKeyValue(DbPdoDriver::STATEMENT_CACHE, "true")
    ->withQueryKeyValue(DbPdoDriver::DONT_PARSE_PARAM, "");
```

If using UNIX_SOCKET:

```php
# Note: there is 3 slashes after the protocol, the first one is the separator between the protocol and the host
$uri = Uri::getInstanceFromString("mysql:///" . $this->dbname)
    ->withQueryKeyValue(DbPdoDriver::UNIX_SOCKET, "/run/mysql.sock");
```
