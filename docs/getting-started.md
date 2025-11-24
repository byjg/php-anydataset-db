---
sidebar_position: 1
---

# Getting Started

## 1. Install the ByJG AnyDatasetDB Library

Install the library using Composer:

```bash
composer require byjg/anydataset-db
```

## 2. Connect to the database

Set up a database connection. ByJG AnyDatasetDB supports multiple databases, including MySQL, PostgreSQL, SQL Server,
Oracle and SQLite.

Example: Connecting to a MySQL database:

```php
<?php
require 'vendor/autoload.php';

use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Util\Uri;

// Create a connection string
$connectionString = 'mysql://user:password@localhost/databasename';

// Create a DbDriver
$dbDriver = Factory::getDbInstance($connectionString);

// Create a DatabaseExecutor (recommended for executing queries)
$executor = DatabaseExecutor::using($dbDriver);

// Alternatively, you can use a Uri object
$uri = new Uri('mysql://user:password@localhost/databasename');
$dbDriver = Factory::getDbInstance($uri);
$executor = DatabaseExecutor::using($dbDriver);
```

### Supported Database Types

The library supports various database types through different connection strings:

```php
// MySQL
$mysql = Factory::getDbInstance('mysql://username:password@hostname/database');

// PostgreSQL
$pgsql = Factory::getDbInstance('pgsql://username:password@hostname/database');

// SQLite
$sqlite = Factory::getDbInstance('sqlite:///path/to/database.db');

// SQL Server (via DBLIB)
$dblib = Factory::getDbInstance('dblib://username:password@hostname/database');

// SQL Server (via SQLSRV)
$sqlsrv = Factory::getDbInstance('sqlsrv://username:password@hostname/database');

// Oracle (via PDO_OCI)
$oci = Factory::getDbInstance('oci://username:password@hostname/database');

// Oracle (via OCI8)
$oci8 = Factory::getDbInstance('oci8://username:password@hostname/database');

// ODBC
$odbc = Factory::getDbInstance('odbc://username:password@dsn');

// Generic PDO
$pdo = Factory::getDbInstance('pdo://username:password@hostname/database');
```

## 3. Perform a query

Once your database connection is established, you can perform queries using the DatabaseExecutor object.

Example: Simple SELECT query:

```php
<?php
use ByJG\AnyDataset\Core\Row;

// Define your SQL query
$sql = "SELECT * FROM your_table WHERE id = :id";

// Execute the query with parameters using DatabaseExecutor (recommended)
$iterator = $executor->getIterator($sql, [':id' => 1]);

// Fetch results
foreach ($iterator as $row) {
    /** @var Row $row */
    $data = $row->toArray();
    print_r($data);

    // Access individual fields
    echo "ID: " . $row->get('id') . "\n";
    echo "Name: " . $row->get('name') . "\n";
}
```

:::info Note
While you can still use `$dbDriver->getIterator()` directly, it is deprecated as of version 6.0
and will be removed in version 7.0. Please use `DatabaseExecutor` for all query operations.
:::

### Using SqlStatement for Reusable Queries

For better performance and reusability, you can use the SqlStatement class:

```php
<?php
use ByJG\AnyDataset\Db\SqlStatement;

// Create a reusable SQL statement
$sql = new SqlStatement("SELECT * FROM your_table WHERE status = :status");

// Execute with different parameters using DatabaseExecutor
$activeUsers = $executor->getIterator($sql, [':status' => 'active']);
$inactiveUsers = $executor->getIterator($sql, [':status' => 'inactive']);
```

## 4. Insert, Update, or Delete data

You can use the execute method for data manipulation operations.

Insert data:

```php
<?php
$sql = "INSERT INTO your_table (name, age) VALUES (:name, :age)";
$executor->execute($sql, [':name' => 'John', ':age' => 30]);

// Get the last inserted ID
$lastId = $executor->executeAndGetId($sql, [':name' => 'Jane', ':age' => 25]);
echo "Last inserted ID: $lastId";
```

Update data:

```php
<?php
$sql = "UPDATE your_table SET age = :age WHERE name = :name";
$executor->execute($sql, [':age' => 31, ':name' => 'John']);
```

Delete data:

```php
<?php
$sql = "DELETE FROM your_table WHERE name = :name";
$executor->execute($sql, [':name' => 'John']);
```

## 5. Transactions

You can use transactions to ensure data consistency:

```php
<?php
use ByJG\AnyDataset\Db\IsolationLevelEnum;

// Begin a transaction (can be called on either executor or driver)
$executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

try {
    // Perform multiple operations
    $executor->execute("INSERT INTO users (name) VALUES (:name)", [':name' => 'John']);
    $userId = $executor->executeAndGetId("INSERT INTO users (name) VALUES (:name)", [':name' => 'Jane']);
    $executor->execute("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)",
        [':user_id' => $userId, ':role' => 'admin']);

    // Commit the transaction if all operations succeed
    $executor->commitTransaction();
} catch (\Exception $ex) {
    // Rollback the transaction if any operation fails
    $executor->rollbackTransaction();
    throw $ex;
}
```

## 6. Working with Results

### Getting a Single Value

To get a single value (first column of the first row):

```php
<?php
$count = $executor->getScalar("SELECT COUNT(*) FROM your_table");
echo "Total records: $count";
```

### Getting All Fields of a Table

To get all fields of a table:

```php
<?php
$fields = $executor->getAllFields("your_table");
print_r($fields);
```

### Pre-fetching Records

For better performance with large result sets:

```php
<?php
// Pre-fetch 100 records
$iterator = $executor->getIterator("SELECT * FROM large_table", null, 100);

foreach ($iterator as $row) {
    // Process each row
}
```

## 7. Close the connection (Optional)

You can explicitly close the connection when you're done:

```php
<?php
$dbDriver->disconnect();
```

## 8. Putting it all together

Here's an example of querying data from a MySQL database using ByJG AnyDatasetDB:

```php
<?php
require 'vendor/autoload.php';

use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\AnyDataset\Core\Row;
use ByJG\Cache\Psr16\ArrayCacheEngine;

// Create a connection to the database
$connectionString = 'mysql://user:password@localhost/databasename';
$dbDriver = Factory::getDbInstance($connectionString);

// Create a DatabaseExecutor (recommended)
$executor = DatabaseExecutor::using($dbDriver);

// Create a reusable SQL statement with caching
$sql = new SqlStatement("SELECT * FROM your_table WHERE age > :age");
$sql->withCache(new ArrayCacheEngine(), 'age_query', 60);

// Execute the query with parameters
$iterator = $executor->getIterator($sql, [':age' => 25]);

// Loop through the results
foreach ($iterator as $row) {
    /** @var Row $row */
    echo "Name: " . $row->get('name') . ", Age: " . $row->get('age') . "\n";
}

// Insert a new record
$executor->execute(
    "INSERT INTO your_table (name, age) VALUES (:name, :age)",
    [':name' => 'Alice', ':age' => 28]
);

// Disconnect from the database
$dbDriver->disconnect();
```

## Conclusion

With ByJG AnyDatasetDB, querying a database is straightforward. The main steps involve:

1. Connecting to the database using `Factory::getDbInstance()`
2. Creating a `DatabaseExecutor` for query operations
3. Using `getIterator()` for SELECT queries or `execute()` for INSERT, UPDATE, or DELETE operations

For more advanced features, check out the following documentation:

- [DatabaseExecutor](database-executor.md) - Recommended API for query execution
- [SQL Statement](sqlstatement.md)
- [Transactions](transaction.md)
- [Caching](cache.md)
- [Load Balancing](load-balance.md)
- [Pre-fetching](prefetch.md)
- [Deprecated Features](deprecated-features.md) - Migration guide for older code
