---
sidebar_position: 2
---

# Basics

:::info
As of version 6.0, the recommended approach is to use `DatabaseExecutor` for all query operations.
Direct calls to driver methods like `$dbDriver->getIterator()` are deprecated and will be removed in version 7.0.
See [DatabaseExecutor documentation](database-executor.md) for more details.
:::

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

## Iterator Convenience Methods

The iterator returned by `getIterator()` provides several convenience methods for common query patterns:

### Getting the First Result

Use `first()` to get the first result or `null` if no results exist:

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://username:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

$iterator = $executor->getIterator('select * from users where id = :id', ['id' => 1]);
$user = $iterator->first(); // Returns array or null

if ($user) {
    echo $user['name'];
}
```

Use `firstOrFail()` to throw an exception if no results exist:

```php
<?php
use ByJG\AnyDataset\Core\Exception\NotFoundException;

try {
    $iterator = $executor->getIterator('select * from users where id = :id', ['id' => 999]);
    $user = $iterator->firstOrFail(); // Throws NotFoundException if empty
    echo $user['name'];
} catch (NotFoundException $e) {
    echo "User not found";
}
```

### Checking if Results Exist

Use `exists()` to check if any results exist:

```php
<?php
$iterator = $executor->getIterator('select * from users where email = :email', ['email' => 'test@example.com']);

if ($iterator->exists()) {
    echo "User with this email already exists";
}
```

Use `existsOrFail()` to throw an exception if no results exist:

```php
<?php
use ByJG\AnyDataset\Core\Exception\NotFoundException;

try {
    $iterator = $executor->getIterator('select * from users where status = :status', ['status' => 'active']);
    $iterator->existsOrFail(); // Throws NotFoundException if empty
    echo "Active users found";
} catch (NotFoundException $e) {
    echo "No active users";
}
```

### Getting All Results as Entities

Use `toEntities()` to get all results as an array:

```php
<?php
$iterator = $executor->getIterator('select * from users');
$users = $iterator->toEntities(); // Returns array of arrays

foreach ($users as $user) {
    echo $user['name'] . "\n";
}
```

When using entity classes (see [Entity Mapping](entity.md)), these methods work seamlessly:

```php
<?php
use ByJG\AnyDataset\Db\SqlStatement;

$sqlStatement = (new SqlStatement('select * from users where age > :age'))
    ->withEntityClass(User::class);

// Get first result as User object
$iterator = $executor->getIterator($sqlStatement, ['age' => 18]);
$user = $iterator->first(); // Returns User object or null
echo $user->getName();

// Get all results as User objects
$users = $iterator->toEntities(); // Returns array of User objects
foreach ($users as $user) {
    echo $user->getName() . "\n";
}
```

## See Also

- [DatabaseExecutor](database-executor.md) - Recommended API documentation
- [Entity Mapping](entity.md) - Using entity classes with iterators
- [Deprecated Features](deprecated-features.md) - Migration guide
- [Transactions](transaction.md) - Detailed transaction documentation
