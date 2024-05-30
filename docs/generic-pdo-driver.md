# Generic PDO configuration

If you want to use a PDO driver that is not mapped into the `anydataset-db` library you can use the generic PDO driver.

The generic PDO driver uses the format `pdo://username:password@pdo_driver?PDO_ARGUMENTS`.

That are the steps to get it working:
1. Install the PDO driver properly;
2. Adapt the connection string URI to the generic PDO format.
3. Use the `Factory::getDbRelationalInstance` to get the database instance.

**IMPORTANT**:

Avoid to use Generic PDO Driver if there is a specific `Anydataset` driver for your database.
The specific driver will have more features and better performance. 

## Adapt the PDO connection string to URI format

Let's take as example the Firebird PDO driver. The connection string is:

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

## Generic rule

From:
```text
<pdo-driver>:User=<user>;Password=<password>;[<pdo-arguments>]
```

To:
```text
pdo://<user>:<password>@<pdo-driver>?<pdo-arguments>
```

## Using Generic PDO to connect with Unix Socket

If you want to connect to a MySQL database using Unix Socket you can use the following URI:

```php
$uri = new Uri("pdo://root:password@mysql?unix_socket=/var/run/mysqld/mysqld.sock&dname=mydatabase");
```

