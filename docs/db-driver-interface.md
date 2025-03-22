---
sidebar_position: 9
---

# Database Driver Interface

The `DbDriverInterface` is the core interface for all database drivers in AnyDataset-DB. It defines the standard methods
that all database drivers must implement, providing a consistent API for interacting with different database systems.

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

### Query Execution

| Method                                                                                                                                           | Description                                                                     |
|--------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------|
| `prepareStatement(string $sql, ?array $params = null, ?array &$cacheInfo = []): mixed`                                                           | Prepares an SQL statement for execution                                         |
| `executeCursor(mixed $statement): void`                                                                                                          | Executes a prepared statement                                                   |
| `getIterator(mixed $sql, ?array $params = null, ?CacheInterface $cache = null, DateInterval\|int $ttl = 60, int $preFetch = 0): GenericIterator` | Executes a SELECT query and returns an iterator to navigate through the results |
| `getScalar(mixed $sql, ?array $array = null): mixed`                                                                                             | Returns a single value from the first column of the first row of a result set   |
| `execute(mixed $sql, ?array $array = null): bool`                                                                                                | Executes a non-query SQL statement (INSERT, UPDATE, DELETE)                     |
| `executeAndGetId(string $sql, ?array $array = null): mixed`                                                                                      | Executes a query and returns the last inserted ID                               |

### Database Metadata

| Method                                   | Description                                              |
|------------------------------------------|----------------------------------------------------------|
| `getAllFields(string $tablename): array` | Gets all field names from a table                        |
| `getDbHelper(): DbFunctionsInterface`    | Returns a helper object with database-specific functions |

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

| Method                                                                                       | Description                                            |
|----------------------------------------------------------------------------------------------|--------------------------------------------------------|
| `beginTransaction(IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void` | Starts a new transaction                               |
| `commitTransaction(): void`                                                                  | Commits the current transaction                        |
| `rollbackTransaction(): void`                                                                | Rolls back the current transaction                     |
| `hasActiveTransaction(): bool`                                                               | Checks if there is an active transaction               |
| `requiresTransaction(): void`                                                                | Throws an exception if there is no active transaction  |
| `remainingCommits(): int`                                                                    | Returns the number of pending commits                  |
| `activeIsolationLevel(): ?IsolationLevelEnum`                                                | Returns the isolation level of the current transaction |

## Usage Example

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DbDriverInterface;

// Get a database driver instance
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Connection management
if (!$dbDriver->isConnected()) {
    $dbDriver->reconnect();
}

// Basic query execution
$iterator = $dbDriver->getIterator("SELECT * FROM users WHERE active = :active", [':active' => true]);
foreach ($iterator as $row) {
    echo $row->get('name') . "\n";
}

// Transaction example
$dbDriver->beginTransaction();
try {
    $dbDriver->execute("INSERT INTO users (name, email) VALUES (:name, :email)", [
        ':name' => 'John Doe',
        ':email' => 'john@example.com'
    ]);
    
    $lastId = $dbDriver->executeAndGetId(
        "INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)",
        [':user_id' => $lastId, ':role' => 'admin']
    );
    
    $dbDriver->commitTransaction();
} catch (Exception $ex) {
    $dbDriver->rollbackTransaction();
    throw $ex;
}

// Clean up
$dbDriver->disconnect();
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