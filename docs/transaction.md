# Database Transaction

## Basics

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');

$dbDriver->beginTransaction(\ByJG\AnyDataset\Db\IsolationLevelEnum::SERIALIZABLE);
try {
    // ... Do your queries

    $dbDriver->commitTransaction(); // or rollbackTransaction()
} catch (\Exception $ex) {
    $dbDriver->rollbackTransaction();
    throw $ex;
}
```

## Nested Transactions

It is possible to nest transactions between methods and functions. 

To make it possible, you need to pass the `allowJoin` parameter as `true` 
in the `beginTransaction` method of all nested transaction.

The commit process uses a technique called "Two-Phase Commit" to ensure that all participant 
transactions are committed or rolled back.

Simplifying:
1. The transaction is committed only when all participants commit the transaction.
2. If any participant rolls back the transaction, all participants will roll back the transaction.

**Important:**

- The nested transaction needs to have the same IsolationLevel as the
parent transaction, otherwise will fail.
- All participants in the database transaction needs to share the same
instance of the DbDriver object. If you use different instances even if they
are using the same connection Uri, you'll have unpredictable results.

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
1. The `mainFunction` starts a transaction and run some queries 
2. The `mainFunction` calls `nestedFunction`.
3. The `nestedFunction` starts a nested transaction with `allowJoin` as `true`.
4. The `nestedFunction` commits the transaction, however, the commit will only be executed when the `mainFunction` also commits the transaction.
5. The `mainFunction` commits the transaction.
6. The transaction is committed in the database.

## Good practices when using transactions

1. Always use the `try/catch` block to handle exceptions and rollback the transaction in case of error.
2. The transaction needs to be committed or rolled back in the same method that started it. **Never** in different methods.
3. If necessary to nest transactions, use the `allowJoin` parameter as `true` in the `beginTransaction` method.
