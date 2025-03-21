---
sidebar_position: 5
---

# Database Transaction

A database transaction is a sequence of operations performed as a single, logical unit of work.
Transactions ensure data consistency and integrity by adhering to the ACID (Atomicity, Consistency, Isolation,
Durability) properties.
If any operation in the sequence fails, the transaction can be rolled back to its previous state.

## Basic Usage

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');

$dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
try {
    // ... Perform your queries
    $dbDriver->execute("INSERT INTO table (field) VALUES (:value)", [':value' => 'test']);
    $dbDriver->execute("UPDATE table SET field = :value WHERE id = :id", [':value' => 'updated', ':id' => 1]);
    
    $dbDriver->commitTransaction(); // Commit all changes
} catch (\Exception $ex) {
    $dbDriver->rollbackTransaction(); // Rollback all changes if an error occurs
    throw $ex;
}
```

## Transaction Methods

### beginTransaction

Starts a new transaction with an optional isolation level.

```php
$dbDriver->beginTransaction(IsolationLevelEnum::READ_COMMITTED);
```

Parameters:

- `$isolationLevel`: (Optional) The isolation level for the transaction
- `$allowJoin`: (Optional) Whether to allow joining an existing transaction (default: false)

### commitTransaction

Commits the current transaction, making all changes permanent.

```php
$dbDriver->commitTransaction();
```

### rollbackTransaction

Rolls back the current transaction, discarding all changes.

```php
$dbDriver->rollbackTransaction();
```

### hasActiveTransaction

Checks if there is an active transaction.

```php
if ($dbDriver->hasActiveTransaction()) {
    // Transaction is active
}
```

### requiresTransaction

Throws an exception if there is no active transaction.

```php
$dbDriver->requiresTransaction(); // Throws TransactionNotStartedException if no transaction is active
```

## Isolation Levels

The library supports different transaction isolation levels through the `IsolationLevelEnum` class:

| Isolation Level    | Description                                                                             |
|--------------------|-----------------------------------------------------------------------------------------|
| `READ_UNCOMMITTED` | Allows dirty reads, non-repeatable reads, and phantom reads. Lowest isolation level.    |
| `READ_COMMITTED`   | Prevents dirty reads but allows non-repeatable reads and phantom reads.                 |
| `REPEATABLE_READ`  | Prevents dirty reads and non-repeatable reads but allows phantom reads.                 |
| `SERIALIZABLE`     | Prevents dirty reads, non-repeatable reads, and phantom reads. Highest isolation level. |

Example:

```php
// Start a transaction with READ_COMMITTED isolation level
$dbDriver->beginTransaction(IsolationLevelEnum::READ_COMMITTED);
```

## Nested Transactions

Nested transactions allow you to manage transactions within different functions or methods.

To enable nested transactions, pass the `allowJoin` parameter as `true` to the `beginTransaction` method in all nested
transactions.

### Two-Phase Commit

Nested transactions use a "Two-Phase Commit" process to ensure consistency:

- The transaction is only committed when all participants successfully commit.
- If any participant rolls back, all participants will roll back.

### Important Points:

- The nested transaction must use the same `IsolationLevel` as the parent transaction; otherwise, it will fail.
- All participants in the transaction must share the same instance of the `DbDriver` object.
  Using different instances, even with the same connection URI, can result in unpredictable behavior.

### Example of Nested Transactions

```php
<?php
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\AnyDataset\Db\DbDriverInterface;

function mainFunction(DbDriverInterface $dbDriver)
{
    $dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
    try {
        // ... Do your queries
        $dbDriver->execute("INSERT INTO table (field) VALUES (:value)", [':value' => 'test']);
        
        nestedFunction($dbDriver);

        $dbDriver->commitTransaction();
    } catch (\Exception $ex) {
        $dbDriver->rollbackTransaction();
        throw $ex;
    }
}

function nestedFunction(DbDriverInterface $dbDriver)
{
    $dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE, allowJoin: true);
    try {
        // ... Do your queries
        $dbDriver->execute("UPDATE table SET field = :value WHERE id = :id", [':value' => 'updated', ':id' => 1]);
        
        $dbDriver->commitTransaction();
    } catch (\Exception $ex) {
        $dbDriver->rollbackTransaction();
        throw $ex;
    }
}

// Call the main transaction
$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
mainFunction($dbDriver);
```

Explanation:

1. The `mainFunction` starts a transaction and performs some queries.
2. It calls `nestedFunction`.
3. The `nestedFunction` starts a nested transaction with `allowJoin` set to `true`.
4. The `nestedFunction` commits its transaction, but the database commit is deferred until the `mainFunction` commits.
5. The `mainFunction` commits the transaction.
6. The entire transaction is committed to the database.

## Transaction Counter

The library maintains a transaction counter to track nested transactions:

- When `beginTransaction` is called, the counter is incremented.
- When `commitTransaction` is called, the counter is decremented.
- The actual database commit only happens when the counter reaches zero.
- When `rollbackTransaction` is called, the counter is reset to zero and the transaction is rolled back immediately.

You can check the remaining commits with:

```php
$remainingCommits = $dbDriver->remainingCommits();
```

## Good Practices When Using Transactions

1. **Always use a `try/catch` block**: This ensures exceptions are handled, and transactions are rolled back in case of
   errors.
2. **Commit or rollback in the same method**: The transaction must be finalized (committed or rolled back) within the
   same method where it started. Never finalize it in a different method.
3. **Enable `allowJoin` for nested transactions**: Use the allowJoin parameter as true in the beginTransaction method
   when nesting transactions.
4. **Use the appropriate isolation level**: Choose the isolation level that best fits your requirements. Higher
   isolation
   levels provide better data consistency but may reduce concurrency.
