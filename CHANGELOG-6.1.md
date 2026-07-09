# Changelog - Version 6.1

## Overview

Version 6.1 is a backward-compatible feature release on top of 6.0. It introduces a generic
observer mechanism for the `DatabaseExecutor` and, built on top of it, a Journal that records
INSERT/UPDATE/DELETE operations with old and new values — designed for restoring database state
between functional tests.

No breaking changes: existing code runs unchanged, and the new event hooks add no measurable
overhead when no observer is attached.

---

## New Features

### 1. Executor Observers
Generic event mechanism to observe queries and commands executed through the `DatabaseExecutor`:
- New `DatabaseEventObserverInterface` attachable with `DatabaseExecutor::addObserver()` / removable with `removeObserver()`
- Events: `BEFORE_QUERY`, `AFTER_QUERY`, `BEFORE_EXECUTE`, `AFTER_EXECUTE` (`DatabaseEventTypeEnum`)
- Each notification carries a `DatabaseEvent` with the `SqlStatement` (SQL and parameters), the executor and the result
- Enables decoupled auditing, metrics and query logging
- Queries answered from the cache do not fire query events

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
- Observers are bound to an executor instance: the deprecated driver methods
  (`$driver->execute()`, etc.) create a fresh internal executor per call and
  therefore bypass observers. Migrate to the `DatabaseExecutor` API
  (the deprecated methods are removed in 7.0).

---

## Upgrade Path from 6.0 to 6.1

No code changes required. Update the composer constraint if you want to rely on the new API:

```json
{
  "require": {
    "byjg/anydataset-db": "^6.1"
  }
}
```