---
sidebar_position: 2
---

# Basics

> **Note**: As of version 6.0, the recommended approach is to use `DatabaseExecutor` for all query operations.
> Direct calls to driver methods like `$dbDriver->getIterator()` are deprecated and will be removed in version 7.0.
> See [DatabaseExecutor documentation](database-executor.md) for more details.

## Basic Query

### Recommended Approach (Using DatabaseExecutor)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

$iterator = $executor->getIterator('select * from table where field = :param', ['param' => 'value']);
foreach ($iterator as $row) {
    // Do Something
    // $row->get('field');
}
```

### Legacy Approach (Deprecated)

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');

// ⚠️ Deprecated: Direct driver method calls will be removed in version 7.0
$iterator = $dbDriver->getIterator('select * from table where field = :param', ['param' => 'value']);
foreach ($iterator as $row) {
    // Do Something
    // $row->get('field');
}
```

## Updating in Relational databases

### Recommended Approach (Using DatabaseExecutor)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

$executor->execute(
    'update table set other = :value where field = :param',
    [
        'value' => 'othervalue',
        'param' => 'value of param'
    ]
);
```

### Legacy Approach (Deprecated)

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');

// ⚠️ Deprecated: Direct driver method calls will be removed in version 7.0
$dbDriver->execute(
    'update table set other = :value where field = :param',
    [
        'value' => 'othervalue',
        'param' => 'value of param'
    ]
);
```

## Inserting and Get Id

### Recommended Approach (Using DatabaseExecutor)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

$id = $executor->executeAndGetId(
    'insert into table (field1, field2) values (:param1, :param2)',
    [
        'param1' => 'value1',
        'param2' => 'value2'
    ]
);
```

### Legacy Approach (Deprecated)

```php
<?php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('mysql://username:password@host/database');

// ⚠️ Deprecated: Direct driver method calls will be removed in version 7.0
$id = $dbDriver->executeAndGetId(
    'insert into table (field1, field2) values (:param1, :param2)',
    [
        'param1' => 'value1',
        'param2' => 'value2'
    ]
);
```

## Database Transaction

### Using DatabaseExecutor (Recommended)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

// Transactions can be called on either executor or driver
$executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

// ... Do your queries using executor
$executor->execute('INSERT INTO ...');

$executor->commitTransaction(); // or rollbackTransaction()
```

### Using DbDriver Directly

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IsolationLevelEnum;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');

// Transaction methods on driver are NOT deprecated
$dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

// ... Do your queries (use DatabaseExecutor for queries)

$dbDriver->commitTransaction(); // or rollbackTransaction()
```

## See Also

- [DatabaseExecutor](database-executor.md) - Recommended API documentation
- [Deprecated Features](deprecated-features.md) - Migration guide
- [Transactions](transaction.md) - Detailed transaction documentation
