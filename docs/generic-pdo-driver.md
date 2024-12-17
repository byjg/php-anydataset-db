---
sidebar_position: 10
---

# Generic PDO Configuration

If you want to use a PDO driver that is not mapped in the `anydataset-db` library, you can use the generic PDO driver.

The generic PDO driver follows the format:  
`pdo://username:password@pdo_driver?PDO_ARGUMENTS`.

## Steps to Configure Generic PDO

1. Install the PDO driver properly.
2. Adapt the connection string URI to the generic PDO format.
3. Use `Factory::getDbInstance` to create the database instance.

### **IMPORTANT**

Whenever possible, use a specific `Anydataset` driver for your database instead of the generic PDO driver. Specific
drivers offer additional features and better performance.

## Adapting the PDO Connection String to URI Format

For example, consider the Firebird PDO driver. Its typical connection string may look like this:

```text
firebird:User=john;Password=mypass;Database=DATABASE.GDE;DataSource=localhost;Port=3050
```

To adapt it to the URI format, remove information about the driver, user, and password, resulting in:

```php
$uri = new Uri("pdo://john:mypass@firebird?Database=DATABASE.GDE&DataSource=localhost&Port=3050");
```

### Key Configuration Points:

- The schema for the generic PDO driver is `"pdo"`.
- The host corresponds to the PDO driver (e.g., `"firebird"`).
- PDO arguments are passed as query parameters in the URI. Replace `;` with `&` to meet URI standards.
- User and password are included as part of the URI.

## Generic Conversion Rule

Convert:
```text
<pdo-driver>:User=<user>;Password=<password>;[<pdo-arguments>]
```

To:
```text
pdo://<user>:<password>@<pdo-driver>?<pdo-arguments>
```

## Using Generic PDO to Connect with a Unix Socket

To connect to a MySQL database using a Unix Socket, use a URI format like this:

```php
$uri = new Uri("pdo://root:password@mysql?unix_socket=/var/run/mysqld/mysqld.sock&dname=mydatabase");
```

