# Changelog - Version 6.0

## Overview

Version 6.0 represents a major architectural refactoring of the AnyDataset-DB library, focusing on improved separation of concerns, enhanced type safety, and better code maintainability. This release introduces breaking changes that require migration from 5.x.

## System Requirements

### PHP Version
- **Minimum:** PHP 8.3
- **Maximum:** PHP 8.5
- **Previous (5.x):** PHP 8.1 - 8.3

### Dependency Updates
- **byjg/uri:** ^6.0 (previously ^5.0)
- **byjg/anydataset:** ^6.0 (previously ^5.0)
- **byjg/cache-engine:** ^6.0 (previously ^5.0)
- **PHPUnit:** ^10.5|^11.5|^12.3 (previously ^9.6)
- **Psalm:** ^5.9|^6.13 (previously ^5.9)

---

## New Features

### 1. DatabaseExecutor API
A new high-level API for database operations that provides better separation of concerns:
- Introduced `DatabaseExecutor` class as the recommended interface for query execution
- Separates high-level query operations from low-level driver management
- Improved testability and maintainability
- Factory method: `DatabaseExecutor::using($dbDriver)`

**Documentation:** [Database Executor](docs/database-executor.md)

### 2. Enhanced SQL Dialect System
Completely refactored the database-specific SQL dialect handling:
- New `SqlDialectInterface` with improved type safety
- Organized dialect classes under dedicated `SqlDialect\` namespace
- Base class `BaseSqlDialect` provides common functionality
- Database-specific dialects: `MysqlDialect`, `PgsqlDialect`, `SqliteDialect`, `DblibDialect`, `SqlsrvDialect`, `OciDialect`, `GenericPdoDialect`

### 3. Parameter Binding System
- New `ParameterBinder` class replaces legacy `SqlBind`
- Improved parameter handling with better type safety
- Enhanced consistency across database drivers
- Support for explicit type casting (e.g., port values as strings)

### 4. DatabaseRouter (Previously Route)
- Enhanced routing capabilities with better driver management
- Improved exception handling with new `RouteNotInitializedException`
- More transparent routing with `DatabaseExecutor` integration
- Better support for load balancing and connection pooling

### 5. Iterator Convenience Methods
New methods on database iterators for improved usability:
- `first()` - Get the first record from results
- `exists()` - Check if results contain any records
- `toEntities()` - Convert results to entity objects

**Documentation:** [Iterator Filter](docs/iteratorfilter.md)

### 6. Multi-Rowset Support
- New `processMultiRowset()` method for handling multiple result sets
- Extended support for stored procedures returning multiple datasets
- Database-specific implementations for optimal performance

### 7. Entity Mapping Enhancements
- Enhanced entity support in `SqlStatement`
- Improved transformer integration
- Better property handling with `PreFetchTrait` improvements
- Merge entity properties with fetched data for complete processing

**Documentation:** [Entity Mapping](docs/entity.md)

### 8. Logging Support
- PSR-3 logger integration across the library
- Enhanced logging capabilities for debugging
- Configurable log handlers

**Documentation:** [Logging](docs/logging.md)

### 9. Enhanced PostgreSQL Support
- Improved double colon (::) handling
- Better type casting support
- Updated documentation

**Documentation:** [PostgreSQL](docs/postgresql.md)

### 10. Stricter Type Safety
- Migration to nullable types in function signatures
- Added `#[Override]` annotations for better IDE support
- Enhanced runtime exception handling across all drivers
- Improved null safety validation

---

## Bug Fixes

- Fixed double colon (::) handling in PostgreSQL queries
- Corrected parameter type handling in `PdoDblib` and `PdoSqlsrv` (explicit string casting for port values)
- Resolved issues with parameter binding in `IteratorFilterSqlFormatter`
- Fixed edge cases in `PreFetchTrait` property merging
- Improved multi-rowset processing validation and error handling
- Corrected `getSqlLastInsertId` logic across database-specific implementations

---

## Breaking Changes

The following table outlines the breaking changes between version 5.x and 6.0:

| Category | Before (5.x) | After (6.0) | Description |
|----------|-------------|-------------|-------------|
| **PHP Version** | PHP 8.1 - 8.3 | PHP 8.3 - 8.5 | Minimum PHP version increased to 8.3 |
| **Query Execution** | `$dbDriver->getIterator($sql)` | `DatabaseExecutor::using($dbDriver)->getIterator($sql)` | Query methods moved from driver to executor |
| **Interface Name** | `DbFunctionsInterface` | `SqlDialectInterface` | Renamed for clarity |
| **Helper Classes** | `Helpers\DbMysqlFunctions` | `SqlDialect\MysqlDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbPgsqlFunctions` | `SqlDialect\PgsqlDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbSqliteFunctions` | `SqlDialect\SqliteDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbDblibFunctions` | `SqlDialect\DblibDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbSqlsrvFunctions` | `SqlDialect\SqlsrvDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbOci8Functions` | `SqlDialect\OciDialect` | Renamed and relocated |
| **Helper Classes** | `Helpers\DbPdoFunctions` | `SqlDialect\GenericPdoDialect` | Renamed and relocated |
| **Base Helper** | `Helpers\DbBaseFunctions` | `SqlDialect\BaseSqlDialect` | Renamed and relocated |
| **Router Class** | `Route` | `DatabaseRouter` | Renamed for clarity |
| **Method Name** | `getDbHelper()` | `getSqlDialect()` | Returns `SqlDialectInterface` |
| **Parameter Binding** | `SqlBind` | `ParameterBinder` | New parameter binding class |
| **Driver Method** | `$dbDriver->execute($sql)` | `DatabaseExecutor::using($dbDriver)->execute($sql)` | Deprecated on driver (removed in 7.0) |
| **Driver Method** | `$dbDriver->getScalar($sql)` | `DatabaseExecutor::using($dbDriver)->getScalar($sql)` | Deprecated on driver (removed in 7.0) |
| **Driver Method** | `$dbDriver->getAllFields($table)` | `DatabaseExecutor::using($dbDriver)->getAllFields($table)` | Deprecated on driver (removed in 7.0) |
| **Driver Method** | `$dbDriver->executeAndGetId($sql)` | `DatabaseExecutor::using($dbDriver)->executeAndGetId($sql)` | Deprecated on driver (removed in 7.0) |
| **Dependencies** | byjg/uri: ^5.0 | byjg/uri: ^6.0 | Major version update |
| **Dependencies** | byjg/anydataset: ^5.0 | byjg/anydataset: ^6.0 | Major version update |
| **Dependencies** | byjg/cache-engine: ^5.0 | byjg/cache-engine: ^6.0 | Major version update |
| **Testing** | PHPUnit ^9.6 | PHPUnit ^10.5\|^11.5\|^12.3 | Updated test framework |
| **Test Annotations** | PHPDoc annotations | PHP 8+ attributes | Migrated to modern attributes |
| **Namespace** | `ByJG\AnyDataset\Db\Helpers\*` | `ByJG\AnyDataset\Db\SqlDialect\*` | Namespace reorganization |

---

## Upgrade Path from 5.x to 6.0

Follow these steps to migrate your application from version 5.x to 6.0:

### Step 1: Update System Requirements

Ensure your environment meets the new requirements:

```bash
# Check PHP version (must be >= 8.3)
php -v
```

### Step 2: Update Composer Dependencies

Update your `composer.json`:

```json
{
  "require": {
    "byjg/anydataset-db": "^6.0"
  }
}
```

Then run:

```bash
composer update byjg/anydataset-db
```

### Step 3: Migrate to DatabaseExecutor

Replace direct query calls on drivers with `DatabaseExecutor`:

**Before:**
```php
use ByJG\AnyDataset\Db\Factory;

$dbDriver = Factory::getDbInstance('mysql://user:pass@host/db');
$iterator = $dbDriver->getIterator('SELECT * FROM users');
$count = $dbDriver->getScalar('SELECT COUNT(*) FROM users');
$dbDriver->execute('UPDATE users SET active = 1');
```

**After:**
```php
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = Factory::getDbInstance('mysql://user:pass@host/db');
$executor = DatabaseExecutor::using($dbDriver);

$iterator = $executor->getIterator('SELECT * FROM users');
$count = $executor->getScalar('SELECT COUNT(*) FROM users');
$executor->execute('UPDATE users SET active = 1');
```

### Step 4: Update Interface and Class References

Replace old interface and class names:

**Before:**
```php
use ByJG\AnyDataset\Db\DbFunctionsInterface;
use ByJG\AnyDataset\Db\Helpers\DbMysqlFunctions;
use ByJG\AnyDataset\Db\Route;

function process(DbFunctionsInterface $helper) {
    $sql = $helper->limit('SELECT * FROM users', 0, 10);
}

$route = new Route();
```

**After:**
```php
use ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface;
use ByJG\AnyDataset\Db\SqlDialect\MysqlDialect;
use ByJG\AnyDataset\Db\DatabaseRouter;

function process(SqlDialectInterface $dialect) {
    $sql = $dialect->limit('SELECT * FROM users', 0, 10);
}

$router = new DatabaseRouter();
```

### Step 5: Update Method Calls

Replace deprecated method names:

**Before:**
```php
$helper = $dbDriver->getDbHelper();
$sql = $helper->concat("'Hello '", "name");
```

**After:**
```php
$dialect = $dbDriver->getSqlDialect();
$sql = $dialect->concat("'Hello '", "name");
```

### Step 6: Update Imports

Find and replace namespace imports:

```bash
# Find old imports
grep -r "use ByJG\\AnyDataset\\Db\\Helpers\\" --include="*.php" .

# Replace with new namespace
# Helpers\DbMysqlFunctions -> SqlDialect\MysqlDialect
# Helpers\DbPgsqlFunctions -> SqlDialect\PgsqlDialect
# etc.
```

### Step 7: Update Parameter Binding (if used directly)

If you were using `SqlBind` directly:

**Before:**
```php
use ByJG\AnyDataset\Db\Helpers\SqlBind;

$sqlBind = new SqlBind($sql, $params);
```

**After:**
```php
use ByJG\AnyDataset\Db\ParameterBinder;

$binder = new ParameterBinder($sql, $params);
```

### Step 8: Update Test Code

If you have tests using PHPDoc annotations, migrate to attributes:

**Before:**
```php
/**
 * @dataProvider userProvider
 */
public function testUser($data) { }
```

**After:**
```php
#[DataProvider('userProvider')]
public function testUser($data) { }
```

### Step 9: Review and Test

1. Run your static analysis tools:
   ```bash
   vendor/bin/psalm
   # or
   vendor/bin/phpstan analyse
   ```

2. Run your test suite:
   ```bash
   vendor/bin/phpunit
   ```

3. Check for deprecation warnings in logs

### Step 10: Update Documentation References

Review your project documentation and update any references to:
- Class names (Route → DatabaseRouter)
- Method names (getDbHelper → getSqlDialect)
- Code examples using the old API

---

## Deprecation Notices

The following features are **deprecated in 6.0** and will be **removed in 7.0**:

### Direct Query Methods on DbDriverInterface

These methods still work in 6.0 but will be removed in 7.0:
- `DbDriverInterface::getIterator()`
- `DbDriverInterface::getScalar()`
- `DbDriverInterface::getAllFields()`
- `DbDriverInterface::execute()`
- `DbDriverInterface::executeAndGetId()`

**Action Required:** Migrate to `DatabaseExecutor` before upgrading to 7.0.

**See:** [Deprecated Features Documentation](docs/deprecated-features.md)

---

## Migration Checklist

Use this checklist to ensure complete migration:

### Environment
- [ ] Verify PHP version is 8.3 or higher
- [ ] Update composer dependencies
- [ ] Run `composer update`

### Code Changes
- [ ] Replace `$dbDriver->getIterator()` with `$executor->getIterator()`
- [ ] Replace `$dbDriver->getScalar()` with `$executor->getScalar()`
- [ ] Replace `$dbDriver->getAllFields()` with `$executor->getAllFields()`
- [ ] Replace `$dbDriver->execute()` with `$executor->execute()`
- [ ] Replace `$dbDriver->executeAndGetId()` with `$executor->executeAndGetId()`
- [ ] Update `DbFunctionsInterface` → `SqlDialectInterface`
- [ ] Update `getDbHelper()` → `getSqlDialect()`
- [ ] Update namespace imports: `Helpers\*` → `SqlDialect\*`
- [ ] Update class names: `Db*Functions` → `*Dialect`
- [ ] Update `Route` → `DatabaseRouter`
- [ ] Update `SqlBind` → `ParameterBinder` (if used directly)

### Testing
- [ ] Update PHPDoc annotations to attributes (if applicable)
- [ ] Run static analysis (Psalm/PHPStan)
- [ ] Run test suite
- [ ] Check application logs for deprecation warnings
- [ ] Test all database operations in staging environment

### Documentation
- [ ] Update code documentation
- [ ] Update README/wiki if applicable
- [ ] Update team documentation about API changes

---

## Additional Resources

- [Getting Started Guide](docs/getting-started.md)
- [DatabaseExecutor Documentation](docs/database-executor.md)
- [Deprecated Features Guide](docs/deprecated-features.md)
- [Database Driver Interface](docs/db-driver-interface.md)
- [Entity Mapping](docs/entity.md)
- [Load Balancing and Connection Pooling](docs/load-balance.md)

---

## Support

If you encounter issues during migration:

1. Review the [Deprecated Features Documentation](docs/deprecated-features.md)
2. Check the [examples in the test suite](https://github.com/byjg/php-anydataset-db/tree/master/tests)
3. Open an issue on [GitHub](https://github.com/byjg/php-anydataset-db/issues)

---

## Timeline

| Version | Status | Notes |
|---------|--------|-------|
| 5.x | Maintenance | Security fixes only |
| 6.0 | Current | Deprecated methods still work with warnings |
| 6.x | Active Development | Full backward compatibility for deprecated features |
| 7.0 | Future | Deprecated methods will be removed |

**Recommendation:** Complete migration to the new API during the 6.x release cycle to ensure smooth transition to 7.0.
