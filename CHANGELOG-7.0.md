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

### 3. Cloudflare D1 Driver

New `DbD1Driver` (`d1://` scheme) talking to the [Cloudflare D1 REST API](https://developers.cloudflare.com/api/resources/d1/subresources/database/methods/query/):

- Connection string: `d1://{account_id}:{api_token}@api.cloudflare.com/{database_id}`
- Works over any PSR-18 HTTP client; uses `byjg/webrequest` when none is injected
- Named parameters are translated to the positional `?` placeholders the API expects
- `executeAndGetId()` reads the generated id from D1's `meta.last_row_id` instead of issuing a
  second query, which would run on a different connection
- SQLite semantics via the new `D1Dialect`
- Routing observability: `isServedByPrimary()`, `getServedByRegion()`, `getServedByColo()`
  and `getLastMeta()` expose what D1 reports about the last statement

**Documentation:** [Driver: Cloudflare D1](docs/cloudflare-d1.md)

---

## Known Limitations

- The Journal recognizes only single-table DML statements; multi-statement SQL,
  `INSERT ... SELECT`, multi-table UPDATEs and CTEs are not journaled
  (strict mode fails loudly on them instead).
- Observers (and therefore the Journal) are bound to a specific executor instance:
  writes made through a different `DatabaseExecutor` are not observed.
- Cloudflare D1 has no interactive transactions (`beginTransaction()` throws `NotAvailableException`),
  no multiple rowsets and no `getAllFields()`; the Journal cannot track INSERTs on it.
- The D1 Sessions API is only available through the Workers binding, so the driver cannot provide
  sequential consistency (read-your-own-writes) on databases with read replication enabled.
  See [Driver: Cloudflare D1](docs/cloudflare-d1.md) for the full list.

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

## Requirements

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.

### ByJG dependencies

- `byjg/anydataset` is now `^7.0`.
- `byjg/cache-engine` is now `^7.0`.
- `byjg/uri` is now `^7.0`.
- `byjg/webrequest` is now `^7.0`.

While 7.0 is unreleased these resolve to `7.0.x-dev` from each component's
`7.0` branch, via `minimum-stability: dev` with `prefer-stable: true`.

## Toolchain

- PHPUnit updated to `^12.5`.
- Psalm moved out of `require-dev` into its own manifest, `tools/psalm/composer.json`.

  Psalm enumerates the PHP versions it supports and no published release lists
  8.6. As a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. It now installs separately, only for the Psalm job.

  `composer psalm` still works — it bootstraps the tool and runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor, and needs `sebastian/diff ^9.0`, which stable Psalm 6.16.1 rejects —
  a combination that silently resolves Psalm to an unreleased `6.x-dev` branch.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5 and installs Psalm from `tools/psalm`.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
