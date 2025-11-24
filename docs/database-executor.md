---
sidebar_position: 11
---

# DatabaseExecutor - Recommended API

Starting from version 6.0, the recommended way to execute database operations is through the `DatabaseExecutor` class.
This class provides a clean separation between low-level driver operations (connections, statement preparation) and
high-level database operations (queries, commands).

## Why Use DatabaseExecutor?

The `DatabaseExecutor` class offers several benefits:

- **Separation of Concerns**: Drivers handle connections; executors handle queries
- **Cleaner Architecture**: High-level operations are decoupled from driver implementation
- **Easier Testing**: Mock drivers for testing without database connections
- **Future-Proof**: Direct driver methods for queries are deprecated and will be removed in version 7.0

## Creating a DatabaseExecutor

There are two ways to create a `DatabaseExecutor`:

### Using the Factory Method (Recommended)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

// Create a database driver
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Create an executor using the factory method
$executor = DatabaseExecutor::using($dbDriver);
```

### Using the Constructor

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

// Create a database driver
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Create an executor using the constructor
$executor = new DatabaseExecutor($dbDriver);
```

## Available Methods

### Query Execution

#### getIterator()

Executes a SELECT query and returns an iterator to navigate through results:

```php
<?php
// Simple query
$iterator = $executor->getIterator('SELECT * FROM users WHERE active = :active', ['active' => 1]);

foreach ($iterator as $row) {
    echo $row->get('name') . "\n";
}

// With SqlStatement
use ByJG\AnyDataset\Db\SqlStatement;

$sql = new SqlStatement('SELECT * FROM users WHERE role = :role');
$iterator = $executor->getIterator($sql, ['role' => 'admin']);

// With pre-fetch
$iterator = $executor->getIterator('SELECT * FROM large_table', null, 100);
```

#### getScalar()

Returns a single value (first column of first row):

```php
<?php
// Get count
$count = $executor->getScalar('SELECT COUNT(*) FROM users');

// Get specific value
$name = $executor->getScalar('SELECT name FROM users WHERE id = :id', ['id' => 1]);

// Returns false if no results
$result = $executor->getScalar('SELECT id FROM users WHERE id = :id', ['id' => 9999]);
// $result === false
```

#### execute()

Executes a non-query SQL statement (INSERT, UPDATE, DELETE):

```php
<?php
// Insert
$executor->execute(
    'INSERT INTO users (name, email) VALUES (:name, :email)',
    ['name' => 'John', 'email' => 'john@example.com']
);

// Update
$executor->execute(
    'UPDATE users SET active = :active WHERE id = :id',
    ['active' => 1, 'id' => 5]
);

// Delete
$executor->execute('DELETE FROM users WHERE id = :id', ['id' => 5]);
```

#### executeAndGetId()

Executes an INSERT statement and returns the last inserted ID:

```php
<?php
$userId = $executor->executeAndGetId(
    'INSERT INTO users (name, email) VALUES (:name, :email)',
    ['name' => 'Jane', 'email' => 'jane@example.com']
);

echo "New user ID: $userId";
```

#### getAllFields()

Gets all field names from a table:

```php
<?php
$fields = $executor->getAllFields('users');
// Returns: ['id', 'name', 'email', 'active', 'created_at']

print_r($fields);
```

### Transaction Management

The `DatabaseExecutor` provides transaction methods that delegate to the underlying driver:

```php
<?php
use ByJG\AnyDataset\Db\IsolationLevelEnum;

// Begin transaction
$executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

try {
    // Perform multiple operations
    $executor->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'John']);

    $userId = $executor->executeAndGetId(
        'INSERT INTO users (name) VALUES (:name)',
        ['name' => 'Jane']
    );

    $executor->execute(
        'INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)',
        ['user_id' => $userId, 'role' => 'admin']
    );

    // Commit if all succeeded
    $executor->commitTransaction();
} catch (\Exception $ex) {
    // Rollback on error
    $executor->rollbackTransaction();
    throw $ex;
}
```

#### Transaction Methods

- `beginTransaction(?IsolationLevelEnum $isolationLevel = null, bool $allowJoin = false): void`
- `commitTransaction(): void`
- `rollbackTransaction(): void`
- `hasActiveTransaction(): bool`
- `activeIsolationLevel(): ?IsolationLevelEnum`
- `remainingCommits(): int`
- `requiresTransaction(): void`

### Accessing the Driver

You can access the underlying driver if needed:

```php
<?php
$driver = $executor->getDriver();

// Access driver-specific methods
$connection = $driver->getDbConnection();
$dialect = $driver->getSqlDialect();
```

## Complete Example

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\SqlStatement;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

// Create driver and executor
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

// Query data
$iterator = $executor->getIterator('SELECT * FROM users WHERE active = :active', ['active' => 1]);

foreach ($iterator as $row) {
    echo "User: " . $row->get('name') . "\n";
}

// Get a count
$totalUsers = $executor->getScalar('SELECT COUNT(*) FROM users');
echo "Total users: $totalUsers\n";

// Transaction example
$executor->beginTransaction(IsolationLevelEnum::READ_COMMITTED);

try {
    // Insert new user
    $userId = $executor->executeAndGetId(
        'INSERT INTO users (name, email, active) VALUES (:name, :email, :active)',
        ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]
    );

    // Assign role
    $executor->execute(
        'INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)',
        ['user_id' => $userId, 'role' => 'user']
    );

    $executor->commitTransaction();
    echo "User created with ID: $userId\n";
} catch (\Exception $ex) {
    $executor->rollbackTransaction();
    echo "Error: " . $ex->getMessage() . "\n";
}

// Using with SqlStatement
$sql = SqlStatement::from('SELECT * FROM users WHERE role = :role')
    ->withParams(['role' => 'admin']);

$admins = $executor->getIterator($sql);
echo "Admin users:\n";
foreach ($admins as $admin) {
    echo "  - " . $admin->get('name') . "\n";
}
```

## Migration from Direct Driver Usage

If you're currently using the driver methods directly, migrating is straightforward:

### Old Way (Deprecated)

```php
<?php
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// Direct driver usage (deprecated in 6.0, removed in 7.0)
$iterator = $dbDriver->getIterator('SELECT * FROM users');
$count = $dbDriver->getScalar('SELECT COUNT(*) FROM users');
$dbDriver->execute('UPDATE users SET active = 1');
```

### New Way (Recommended)

```php
<?php
$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

// Using executor (recommended)
$iterator = $executor->getIterator('SELECT * FROM users');
$count = $executor->getScalar('SELECT COUNT(*) FROM users');
$executor->execute('UPDATE users SET active = 1');
```

## Benefits Summary

1. **Clean Architecture**: Separation of driver responsibilities (connections) from executor responsibilities (queries)
2. **Better Testing**: Mock drivers without needing database connections
3. **Type Safety**: Clear separation of concerns makes code more maintainable
4. **Future-Proof**: Aligns with library architecture going forward

## See Also

- [Basic Query](basic-query.md)
- [Transactions](transaction.md)
- [Deprecated Features](deprecated-features.md) - Information about deprecated driver methods
