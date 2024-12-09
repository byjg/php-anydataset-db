---
sidebar_position: 5
---

# Database Transaction

A database transaction is a sequence of operations performed as a single, logical unit of work.
Transactions ensure data consistency and integrity by adhering to the ACID (Atomicity, Consistency, Isolation,
Durability) properties.
If any operation in the sequence fails, the transaction can be rolled back to its previous state.

## Basics

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');

$dbDriver->beginTransaction(\ByJG\AnyDataset\Db\IsolationLevelEnum::SERIALIZABLE);
try {
    // ... Perform your queries

    $dbDriver->commitTransaction(); // or rollbackTransaction()
} catch (\Exception $ex) {
    $dbDriver->rollbackTransaction();
    throw $ex;
}
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
use \ByJG\AnyDataset\Db\DbDriverInterface;

function mainFunction(DbDriverInterface $dbDriver)
{
    $dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);
    try {
        // ... Do your queries

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

        $dbDriver->commitTransaction();
    } catch (\Exception $ex) {
        $dbDriver->rollbackTransaction();
        throw $ex;
    }
}

# Call the main transaction
mainFunction($dbDriver);
```

Explanation:

1. The `mainFunction` starts a transaction and performs some queries.
2. It calls `nestedFunction`.
3. The `nestedFunction` starts a nested transaction with `allowJoin` set to `true`.
4. The `nestedFunction` commits its transaction, but the database commit is deferred until the `mainFunction` commits.
5. The `mainFunction` commits the transaction.
6. The entire transaction is committed to the database.

## Good Practices When Using Transactions

1. **Always use a `try/catch` block**: This ensures exceptions are handled, and transactions are rolled back in case of
   errors.
2. **Commit or rollback in the same method**: The transaction must be finalized (committed or rolled back) within the
   same method where it started. Never finalize it in a different method.
3. **Enable `allowJoin` for nested transactions**: Use the allowJoin parameter as true in the beginTransaction method
   when nesting transactions.
