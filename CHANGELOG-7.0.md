# Changelog - Version 7.0

## Overview

Version 7.0 completes the architectural refactoring started in 6.0: the query methods deprecated
on the drivers are now **removed**, making `DatabaseExecutor` the single API for executing queries
and commands.

On top of that, 7.0 introduces a generic observer mechanism for the `DatabaseExecutor` and,
built on it, a Journal that records INSERT/UPDATE/DELETE operations with old and new values —
designed for restoring database state between functional tests.

---

## Breaking Changes

### Removed: Direct Query Methods on Drivers

The following methods — deprecated since 6.0 — were removed from `DbDriverInterface`,
all drivers (`DbPdoDriver` and subclasses, `DbOci8Driver`) and `DatabaseRouter`:

| Removed Method | Replacement |
|---|---|
| `$dbDriver->getIterator($sql, $params)` | `DatabaseExecutor::using($dbDriver)->getIterator($sql, $params)` |
| `$dbDriver->getScalar($sql, $params)` | `DatabaseExecutor::using($dbDriver)->getScalar($sql, $params)` |
| `$dbDriver->execute($sql, $params)` | `DatabaseExecutor::using($dbDriver)->execute($sql, $params)` |
| `$dbDriver->executeAndGetId($sql, $params)` | `DatabaseExecutor::using($dbDriver)->executeAndGetId($sql, $params)` |
| `$dbDriver->getAllFields($table)` | `DatabaseExecutor::using($dbDriver)->getAllFields($table)` |

Transaction methods (`beginTransaction`, `commitTransaction`, `rollbackTransaction`, etc.) and
low-level methods (`prepareStatement`, `executeCursor`, `getDriverIterator`, etc.) remain on the driver.

**Migration:**

```php
<?php
// 6.x (deprecated) - no longer works in 7.0
$iterator = $dbDriver->getIterator('SELECT * FROM users');

// 7.0
$executor = DatabaseExecutor::using($dbDriver);
$iterator = $executor->getIterator('SELECT * FROM users');
```

See [Deprecated Features](docs/deprecated-features.md) for the complete migration checklist.

---

## New Features

### 1. Executor Observers
Generic event mechanism to observe queries and commands executed through the `DatabaseExecutor`:
- New `DatabaseEventObserverInterface` attachable with `DatabaseExecutor::addObserver()` / removable with `removeObserver()`
- Events: `BEFORE_QUERY`, `AFTER_QUERY`, `BEFORE_EXECUTE`, `AFTER_EXECUTE` (`DatabaseEventTypeEnum`)
- Each notification carries a `DatabaseEvent` with the `SqlStatement` (SQL and parameters), the executor and the result
- Enables decoupled auditing, metrics and query logging
- Queries answered from the cache do not fire query events
- No measurable overhead when no observer is attached

**Documentation:** [Executor Observers](docs/observers.md)

### 2. Journal (Record and Restore Changes)
Records all INSERT, UPDATE and DELETE statements with the row values before and after each operation:
- `JournalRecorder` watches all tables or a specific set (`forAllTables()` / `forTables()`)
- Configurable primary keys: `withPrimaryKey($table, $column)` and `withDefaultPrimaryKey($column)` (default `id`)
- `JournalRestorer` replays the journal in reverse, restoring the previous database state
- Optional strict mode (`->strict()`) throws a `JournalException` on statements that cannot be journaled/restored, instead of skipping them silently
- Designed for functional tests (record on setUp, restore on tearDown)

**Documentation:** [Journal](docs/journal.md)

---

## Known Limitations

- The Journal recognizes only single-table DML statements; multi-statement SQL,
  `INSERT ... SELECT`, multi-table UPDATEs and CTEs are not journaled
  (strict mode fails loudly on them instead).
- Observers (and therefore the Journal) are bound to a specific executor instance:
  writes made through a different `DatabaseExecutor` are not observed.

---

## Upgrade Path from 6.x to 7.0

1. Migrate every direct driver query call to `DatabaseExecutor` (see the table above).
   Code already using `DatabaseExecutor` — the recommended API since 6.0 — needs no changes.
2. Update the composer constraint:

```json
{
  "require": {
    "byjg/anydataset-db": "^7.0"
  }
}
```