---
sidebar_position: 10
---

# Database Driver Interface

The `DbDriverInterface` is the core interface for all database drivers in AnyDataset-DB. It defines the standard methods
that all database drivers must implement, providing a consistent API for interacting with different database systems.

> **Important**: As of version 6.0, some high-level query methods in `DbDriverInterface` are deprecated in favor of
> using `DatabaseExecutor`. See the [deprecation section](#deprecated-methods) below and the
> [DatabaseExecutor documentation](database-executor.md) for the recommended approach.

## Interface Definition

All drivers in the AnyDataset-DB library implement this interface, which extends `DbTransactionInterface`:

```php
interface DbDriverInterface extends DbTransactionInterface
{
    // Methods to implement
}
```

## Available Methods

### Connection Management

| Method                                                                 | Description                                                                                |
|------------------------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `schema()`                                                             | Static method that returns the supported schema(s) for the driver (e.g., 'mysql', 'pgsql') |
| `isConnected(bool $softCheck = false, bool $throwError = false): bool` | Checks if the connection is active                                                         |
| `reconnect(bool $force = false): bool`                                 | Re-establishes a connection to the database                                                |
| `disconnect(): void`                                                   | Closes the connection to the database                                                      |
| `getDbConnection(): mixed`                                             | Returns the low-level database connection object                                           |
| `getUri(): Uri`                                                        | Returns the connection URI                                                                 |

### Low-Level Query Execution

| Method                                                                                                                                                                         | Description                                         |
|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------|
| `prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed`                                                                                         | Prepares an SQL statement for execution             |
| `executeCursor(mixed $statement): void`                                                                                                                                        | Executes a prepared statement                       |
| `processMultiRowset(mixed $statement): void`                                                                                                                                   | Processes multiple result sets                      |
| `getDriverIterator(mixed $statement, int $preFetch = 0, ?string $entityClass = null, ?PropertyHandlerInterface $entityTransformer = null): GenericDbIterator\|GenericIterator` | Creates a driver-specific iterator from a statement |

### High-Level Query Execution (Deprecated)

> **⚠️ Deprecated in version 6.0, will be removed in version 7.0**
> Use [DatabaseExecutor](database-executor.md) instead for these operations.

| Method                                                                                                                 | Description                                                   | Replacement                                           |
|------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------|-------------------------------------------------------|
| `getIterator(string\|SqlStatement $sql, ?array $params = null, int $preFetch = 0): GenericDbIterator\|GenericIterator` | Executes a SELECT query and returns an iterator               | `DatabaseExecutor::using($driver)->getIterator()`     |
| `getScalar(mixed $sql, ?array $array = null): mixed`                                                                   | Returns a single value from the first column of the first row | `DatabaseExecutor::using($driver)->getScalar()`       |
| `execute(mixed $sql, ?array $array = null): bool`                                                                      | Executes a non-query SQL statement                            | `DatabaseExecutor::using($driver)->execute()`         |
| `executeAndGetId(string\|SqlStatement $sql, ?array $array = null): mixed`                                              | Executes a query and returns the last inserted ID             | `DatabaseExecutor::using($driver)->executeAndGetId()` |
| `getAllFields(string $tablename): array`                                                                               | Gets all field names from a table                             | `DatabaseExecutor::using($driver)->getAllFields()`    |

### Database Metadata and Helpers

| Method                                | Description                                              |
|---------------------------------------|----------------------------------------------------------|
| `getDbHelper(): DbFunctionsInterface` | Returns a helper object with database-specific functions |

### Advanced Settings

| Method                                              | Description                                                 |
|-----------------------------------------------------|-------------------------------------------------------------|
| `isSupportMultiRowset(): bool`                      | Checks if the driver supports multiple result sets          |
| `setSupportMultiRowset(bool $multipleRowSet): void` | Sets whether the driver should support multiple result sets |

### Logging

| Method                                            | Description                                  |
|---------------------------------------------------|----------------------------------------------|
| `enableLogger(LoggerInterface $logger): void`     | Sets a PSR-3 compliant logger for the driver |
| `log(string $message, array $context = []): void` | Logs a message using the configured logger   |

## Transaction Methods (from DbTransactionInterface)

The interface extends `DbTransactionInterface`, so it also includes these transaction methods:

| Method                                                              | Description                                            |
|---------------------------------------------------------------------|--------------------------------------------------------|
| `beginTransaction(IsolationLevelEnum $isolationLevel = null): void` | Starts a new transaction                               |
| `commitTransaction(): void`                                         | Commits the current transaction                        |
| `rollbackTransaction(): void`                                       | Rolls back the current transaction                     |
| `hasActiveTransaction(): bool`                                      | Checks if there is an active transaction               |
| `activeIsolationLevel(): ?IsolationLevelEnum`                       | Returns the isolation level of the current transaction |

## Usage Example

### Recommended Approach (Using DatabaseExecutor)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

// Get a database driver instance
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Create a DatabaseExecutor (recommended)
$executor = DatabaseExecutor::using($dbDriver);

// Connection management (still done through driver)
if (!$dbDriver->isConnected()) {
    $dbDriver->reconnect();
}

// Basic query execution (using executor)
$iterator = $executor->getIterator("SELECT * FROM users WHERE active = :active", [':active' => true]);
foreach ($iterator as $row) {
    echo $row->get('name') . "\n";
}

// Using SqlStatement
$sqlStatement = new SqlStatement(
    "SELECT * FROM users WHERE active = :active AND role = :role",
    [':active' => true]
);
$iterator = $executor->getIterator($sqlStatement, [':role' => 'admin']);

// Transaction example (can use either executor or driver)
$executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
try {
    $executor->execute("INSERT INTO users (name, email) VALUES (:name, :email)", [
        ':name' => 'John Doe',
        ':email' => 'john@example.com'
    ]);

    $lastId = $executor->executeAndGetId(
        "INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)",
        [':user_id' => $lastId, ':role' => 'admin']
    );

    $executor->commitTransaction();
} catch (Exception $ex) {
    $executor->rollbackTransaction();
    throw $ex;
}

// Clean up (still done through driver)
$dbDriver->disconnect();
```

### Legacy Approach (Deprecated)

```php
<?php
use ByJG\AnyDataset\Db\Factory;

// Get a database driver instance
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// ⚠️ Deprecated: Direct query methods on driver will be removed in version 7.0
$iterator = $dbDriver->getIterator("SELECT * FROM users WHERE active = :active", [':active' => true]);
foreach ($iterator as $row) {
    echo $row->get('name') . "\n";
}
```

## Creating Custom Drivers

To create a custom database driver, you must implement the `DbDriverInterface` interface. The easiest way is to extend
the `DbPdoDriver` abstract class, which provides many of the required implementations.

```php
<?php
namespace MyApp\Database;

use ByJG\AnyDataset\Db\DbPdoDriver;
use Override;

class MyCustomDriver extends DbPdoDriver
{
    #[Override]
    public static function schema()
    {
        return "mycustom";
    }
    
    // Override other methods as needed
}

// Register your driver
\ByJG\AnyDataset\Db\Factory::registerDbDriver(MyCustomDriver::class);

// Now you can use it
$db = \ByJG\AnyDataset\Db\Factory::getDbInstance("mycustom://user:pass@host/db");
```

## Deprecated Methods

As of version 6.0, the following methods are deprecated and will be removed in version 7.0:

- `getIterator()` - Use `DatabaseExecutor::using($driver)->getIterator()` instead
- `getScalar()` - Use `DatabaseExecutor::using($driver)->getScalar()` instead
- `execute()` - Use `DatabaseExecutor::using($driver)->execute()` instead
- `executeAndGetId()` - Use `DatabaseExecutor::using($driver)->executeAndGetId()` instead
- `getAllFields()` - Use `DatabaseExecutor::using($driver)->getAllFields()` instead

For a complete migration guide, see [Deprecated Features](deprecated-features.md).

## Available Implementations

AnyDataset-DB provides several implementations of the `DbDriverInterface`:

| Class          | Schema | Description                                               |
|----------------|--------|-----------------------------------------------------------|
| `PdoMysql`     | mysql  | MySQL and MariaDB driver                                  |
| `PdoPgsql`     | pgsql  | PostgreSQL driver                                         |
| `PdoSqlite`    | sqlite | SQLite driver                                             |
| `PdoDblib`     | dblib  | SQL Server driver (using FreeTDS)                         |
| `PdoSqlsrv`    | sqlsrv | SQL Server driver (using Microsoft driver)                |
| `PdoOci`       | oci    | Oracle driver (using PDO OCI)                             |
| `DbOci8Driver` | oci8   | Oracle driver (using OCI8 extension)                      |
| `PdoOdbc`      | odbc   | ODBC driver                                               |
| `PdoPdo`       | pdo    | Generic PDO driver                                        |
| `Route`        | route  | Special driver for routing queries to different databases |

## See Also

- [DatabaseExecutor](database-executor.md) - Recommended API for query execution
- [Deprecated Features](deprecated-features.md) - Migration guide for deprecated methods
- [Getting Started](getting-started.md) - Quick start guide 