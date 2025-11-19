---
sidebar_position: 18
---

# Deprecated Features

This document lists features that are deprecated in version 6.0 and will be removed in version 7.0. Please update your
code to use the recommended alternatives.

## Deprecated: Direct Query Methods on DbDriverInterface

**Deprecated in:** Version 6.0
**Removed in:** Version 7.0
**Reason:** Architectural refactoring to separate low-level driver operations from high-level query execution

### Affected Methods

The following methods on `DbDriverInterface` implementations are deprecated:

- `getIterator()`
- `getScalar()`
- `getAllFields()`
- `execute()`
- `executeAndGetId()`

### Migration Path

Use the `DatabaseExecutor` class instead of calling these methods directly on the driver.

#### Old Code (Deprecated)

```php
<?php
use ByJG\AnyDataset\Db\Factory;

$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');

// ❌ Deprecated - calling query methods directly on driver
$iterator = $dbDriver->getIterator('SELECT * FROM users');
$count = $dbDriver->getScalar('SELECT COUNT(*) FROM users');
$fields = $dbDriver->getAllFields('users');
$dbDriver->execute('UPDATE users SET active = 1');
$id = $dbDriver->executeAndGetId('INSERT INTO users (name) VALUES (:name)', ['name' => 'John']);
```

#### New Code (Recommended)

```php
<?php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://user:password@host/database');
$executor = DatabaseExecutor::using($dbDriver);

// ✅ Recommended - using DatabaseExecutor
$iterator = $executor->getIterator('SELECT * FROM users');
$count = $executor->getScalar('SELECT COUNT(*) FROM users');
$fields = $executor->getAllFields('users');
$executor->execute('UPDATE users SET active = 1');
$id = $executor->executeAndGetId('INSERT INTO users (name) VALUES (:name)', ['name' => 'John']);
```

### Why This Change?

The architectural change provides several benefits:

1. **Separation of Concerns**: Drivers now focus on low-level operations (connections, statement preparation), while
   executors handle high-level operations (queries, commands)
2. **Better Testing**: Easier to mock and test components independently
3. **Cleaner Code**: More explicit about which layer of abstraction you're working with
4. **Maintainability**: Clearer responsibilities make the codebase easier to maintain

### What Remains in DbDriverInterface?

The following methods are **NOT** deprecated and remain as core driver functionality:

#### Low-Level Operations

- `prepareStatement()` - Prepares SQL statements
- `executeCursor()` - Executes prepared statements
- `getDriverIterator()` - Creates driver-specific iterators
- `processMultiRowset()` - Handles multiple result sets

#### Connection Management

- `reconnect()` - Re-establishes database connection
- `disconnect()` - Closes database connection
- `isConnected()` - Checks connection status
- `getDbConnection()` - Gets the underlying connection object

#### Configuration

- `getUri()` - Gets the connection URI
- `getSqlDialect()` - Gets database-specific SQL dialect functions
- `getSqlDialectClass()` - Gets the class name of the SQL dialect implementation
- `isSupportMultiRowset()` - Checks multi-rowset support
- `setSupportMultiRowset()` - Configures multi-rowset support
- `getAttribute()` - Gets driver attributes
- `setAttribute()` - Sets driver attributes

#### Logging

- `enableLogger()` - Configures PSR-3 logger
- `log()` - Logs messages

#### Transactions

- All transaction methods remain in `DbTransactionInterface`
- Available in both driver and executor for convenience

## Backward Compatibility

### Version 6.0

The deprecated methods still work in version 6.0. They internally delegate to `DatabaseExecutor`, so your existing code
will continue to function without changes. However, you may see deprecation warnings in your logs or IDE.

Example of what happens internally:

```php
<?php
// When you call (deprecated):
$dbDriver->getIterator($sql, $params);

// It internally does (since 6.0):
DatabaseExecutor::using($dbDriver)->getIterator($sql, $params);
```

### Version 7.0

In version 7.0, the deprecated methods will be removed from `DbDriverInterface`. Code that hasn't been migrated will
throw errors.

## Renamed Classes and Methods (Version 6.0)

**Breaking Changes in:** Version 6.0

### Interface and Class Renames

The following interfaces and classes have been renamed for better clarity:

#### Interface Renames

| Old Name               | New Name              | Location                         |
|------------------------|-----------------------|----------------------------------|
| `DbFunctionsInterface` | `SqlDialectInterface` | `Interfaces\SqlDialectInterface` |

#### Class Renames

| Old Name            | New Name            | Old Location   | New Location   |
|---------------------|---------------------|----------------|----------------|
| `DbMysqlFunctions`  | `MysqlDialect`      | `Helpers\`     | `SqlDialect\`  |
| `DbSqliteFunctions` | `SqliteDialect`     | `Helpers\`     | `SqlDialect\`  |
| `DbPgsqlFunctions`  | `PgsqlDialect`      | `Helpers\`     | `SqlDialect\`  |
| `DbDblibFunctions`  | `DblibDialect`      | `Helpers\`     | `SqlDialect\`  |
| `DbSqlsrvFunctions` | `SqlsrvDialect`     | `Helpers\`     | `SqlDialect\`  |
| `DbOci8Functions`   | `OciDialect`        | `Helpers\`     | `SqlDialect\`  |
| `DbPdoFunctions`    | `GenericPdoDialect` | `Helpers\`     | `SqlDialect\`  |
| `DbBaseFunctions`   | `BaseDialect`       | `Helpers\`     | `SqlDialect\`  |
| `Route`             | `DatabaseRouter`    | Root namespace | Root namespace |

#### Method Renames

| Old Method                            | New Method                             | Returns                    |
|---------------------------------------|----------------------------------------|----------------------------|
| `getDbHelper(): DbFunctionsInterface` | `getSqlDialect(): SqlDialectInterface` | SqlDialectInterface        |
| N/A                                   | `getSqlDialectClass(): string`         | Fully qualified class name |

### Migration Examples

#### Example 1: Interface Type Hints

**Before:**

```php
use ByJG\AnyDataset\Db\DbFunctionsInterface;

function processQuery(DbFunctionsInterface $helper) {
    $sql = $helper->limit("SELECT * FROM users", 0, 10);
}
```

**After:**

```php
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;

function processQuery(SqlDialectInterface $dialect) {
    $sql = $dialect->limit("SELECT * FROM users", 0, 10);
}
```

#### Example 2: Method Calls

**Before:**

```php
$dbDriver = Factory::getDbInstance('mysql://...');
$helper = $dbDriver->getDbHelper();
$sql = $helper->top("SELECT * FROM products", 100);
```

**After:**

```php
$dbDriver = Factory::getDbInstance('mysql://...');
$dialect = $dbDriver->getSqlDialect();
$sql = $dialect->top("SELECT * FROM products", 100);
```

#### Example 3: Direct Class Usage

**Before:**

```php
use ByJG\AnyDataset\Db\Helpers\DbMysqlFunctions;

$helper = new DbMysqlFunctions();
$concat = $helper->concat("'Hello '", "name");
```

**After:**

```php
use ByJG\AnyDataset\Db\SqlDialect\MysqlDialect;

$dialect = new MysqlDialect();
$concat = $dialect->concat("'Hello '", "name");
```

#### Example 4: Router Class

**Before:**

```php
use ByJG\AnyDataset\Db\Route;

$route = new Route();
$route->addDriver('master', $masterDriver);
$route->addRouteForWrite('master');
```

**After:**

```php
use ByJG\AnyDataset\Db\DatabaseRouter;

$router = new DatabaseRouter();
$router->addDriver('master', $masterDriver);
$router->addRouteForWrite('master');
```

## Migration Checklist

Use this checklist to ensure your code is ready for version 7.0:

### DatabaseExecutor Migration
- [ ] Replace `$dbDriver->getIterator()` with `$executor->getIterator()`
- [ ] Replace `$dbDriver->getScalar()` with `$executor->getScalar()`
- [ ] Replace `$dbDriver->getAllFields()` with `$executor->getAllFields()`
- [ ] Replace `$dbDriver->execute()` with `$executor->execute()`
- [ ] Replace `$dbDriver->executeAndGetId()` with `$executor->executeAndGetId()`
- [ ] Create `DatabaseExecutor` instances using `DatabaseExecutor::using($dbDriver)`
- [ ] Update your tests to use `DatabaseExecutor` where appropriate

### Interface and Class Migration

- [ ] Replace `DbFunctionsInterface` with `SqlDialectInterface` in type hints
- [ ] Replace `getDbHelper()` calls with `getSqlDialect()`
- [ ] Update imports: `use ByJG\AnyDataset\Db\Helpers\*` → `use ByJG\AnyDataset\Db\SqlDialect\*`
- [ ] Replace `Route` with `DatabaseRouter`
- [ ] Update class names: `Db*Functions` → `*Dialect`
- [ ] Replace `DbBaseFunctions` constants with `BaseDialect` constants
- [ ] Update any direct instantiation of helper/dialect classes

### Search Commands

```bash
# Find deprecated query methods
grep -r "->getIterator\|->getScalar\|->execute\|->executeAndGetId\|->getAllFields" --include="*.php" .

# Find old interface references
grep -r "DbFunctionsInterface" --include="*.php" .

# Find old method calls
grep -r "->getDbHelper()" --include="*.php" .

# Find old helper class imports
grep -r "use.*Helpers\\Db.*Functions" --include="*.php" .

# Find Route class usage
grep -r "new Route()\|use.*\\Route;" --include="*.php" .
```

## Finding Deprecated Usage

### Using Grep/Ripgrep

Search for deprecated usage in your codebase:

```bash
# Find all potential deprecated calls
grep -r "\->getIterator\|\->getScalar\|\->execute\|\->getAllFields\|\->executeAndGetId" --include="*.php" .

# More specific search (looking for $dbDriver or similar)
grep -r "\$db.*->\(getIterator\|getScalar\|execute\|getAllFields\|executeAndGetId\)" --include="*.php" .
```

### Using PHPStan/Psalm

Modern static analysis tools will detect usage of deprecated methods:

```bash
# PHPStan
vendor/bin/phpstan analyse

# Psalm
vendor/bin/psalm
```

## Common Migration Patterns

### Pattern 1: Class Property

**Before:**

```php
<?php
class UserRepository
{
    private DbDriverInterface $db;

    public function __construct(DbDriverInterface $db)
    {
        $this->db = $db;
    }

    public function findAll()
    {
        return $this->db->getIterator('SELECT * FROM users');
    }
}
```

**After:**

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;

class UserRepository
{
    private DatabaseExecutor $executor;

    public function __construct(DbDriverInterface $db)
    {
        $this->executor = DatabaseExecutor::using($db);
    }

    // Or accept DatabaseExecutor directly:
    // public function __construct(DatabaseExecutor $executor)
    // {
    //     $this->executor = $executor;
    // }

    public function findAll()
    {
        return $this->executor->getIterator('SELECT * FROM users');
    }
}
```

### Pattern 2: Dependency Injection

**Before:**

```php
<?php
$container->set(DbDriverInterface::class, function() {
    return Factory::getDbInstance('mysql://...');
});

$container->set(UserRepository::class, function($container) {
    return new UserRepository($container->get(DbDriverInterface::class));
});
```

**After:**

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;

$container->set(DbDriverInterface::class, function() {
    return Factory::getDbInstance('mysql://...');
});

$container->set(DatabaseExecutor::class, function($container) {
    return DatabaseExecutor::using($container->get(DbDriverInterface::class));
});

$container->set(UserRepository::class, function($container) {
    return new UserRepository($container->get(DatabaseExecutor::class));
});
```

### Pattern 3: Factory Methods

**Before:**

```php
<?php
class DatabaseFactory
{
    public static function createConnection(): DbDriverInterface
    {
        return Factory::getDbInstance('mysql://...');
    }

    public static function getUsers()
    {
        $db = self::createConnection();
        return $db->getIterator('SELECT * FROM users');
    }
}
```

**After:**

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;

class DatabaseFactory
{
    public static function createConnection(): DbDriverInterface
    {
        return Factory::getDbInstance('mysql://...');
    }

    public static function createExecutor(): DatabaseExecutor
    {
        return DatabaseExecutor::using(self::createConnection());
    }

    public static function getUsers()
    {
        $executor = self::createExecutor();
        return $executor->getIterator('SELECT * FROM users');
    }
}
```

## Need Help?

If you have questions about migrating your code:

1. Check the [DatabaseExecutor documentation](database-executor.md)
2. Review the [examples in the test suite](https://github.com/byjg/php-anydataset-db/tree/master/tests)
3. Open an issue on [GitHub](https://github.com/byjg/php-anydataset-db/issues)

## Timeline

| Version | Status                                                  |
|---------|---------------------------------------------------------|
| 6.0     | Deprecated - Methods work but show deprecation warnings |
| 6.x     | Deprecated - Continue to work throughout 6.x series     |
| 7.0     | Removed - Methods no longer available, code will break  |

**Recommendation**: Migrate your code during the 6.x cycle to ensure a smooth upgrade to 7.0.
