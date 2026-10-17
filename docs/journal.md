---
sidebar_position: 13
---

# Journal (Record and Restore Changes)

The Journal records all `INSERT`, `UPDATE` and `DELETE` statements executed through a
`DatabaseExecutor`, capturing the row values **before** and **after** each operation.
It can watch **all tables** or only a **specific set of tables**.

Its main use case is functional testing: record everything the test changed and restore
the previous state on tearDown — without recreating the database.

It is built on top of the [Executor Observers](observers.md), so it is fully decoupled:
if you don't attach it, nothing changes in your application.

## Recording

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Journal\JournalRecorder;

$executor = DatabaseExecutor::using(Factory::getDbInstance('mysql://user:password@host/database'));

// Watch all tables...
$recorder = JournalRecorder::forAllTables();

// ...or only specific tables
$recorder = JournalRecorder::forTables('users', 'orders');

$executor->addObserver($recorder);

$executor->execute("insert into users (name) values (:name)", ['name' => 'John']);
$executor->execute("update users set name = :name where id = :id", ['name' => 'Johnny', 'id' => 10]);
$executor->execute("delete from users where id = :id", ['id' => 11]);

foreach ($recorder->getEntries() as $entry) {
    echo $entry->getOperation()->value;  // insert, update or delete
    echo $entry->getTable();             // users
    print_r($entry->getOldRows());       // rows BEFORE the operation (empty for insert)
    print_r($entry->getNewRows());       // rows AFTER the operation (empty for delete)
    echo $entry->getSql();               // the original SQL statement
}
```

## Restoring (Setup and TearDown)

The `JournalRestorer` replays the journal in reverse order:
recorded INSERTs are deleted, UPDATEs have their old values written back,
and DELETEs are re-inserted.

```php
<?php
use ByJG\AnyDataset\Db\Journal\JournalRecorder;
use ByJG\AnyDataset\Db\Journal\JournalRestorer;
use PHPUnit\Framework\TestCase;

class MyFunctionalTest extends TestCase
{
    protected DatabaseExecutor $executor;
    protected JournalRecorder $recorder;

    public function setUp(): void
    {
        $this->executor = DatabaseExecutor::using(Factory::getDbInstance('...'));

        // strict() makes the test fail loudly if a statement cannot be
        // journaled (and therefore could not be restored) - see Strict Mode below
        $this->recorder = JournalRecorder::forAllTables()->strict();
        $this->executor->addObserver($this->recorder);
    }

    public function tearDown(): void
    {
        $this->executor->removeObserver($this->recorder);
        (new JournalRestorer())->restore($this->executor->getDriver(), $this->recorder);
    }

    public function testSomething()
    {
        // any insert/update/delete executed here (directly or by the code
        // under test using this executor) will be reverted on tearDown
    }
}
```

The restore statements run through a dedicated executor without observers,
so they are never recorded again.

## Primary Keys

The journal uses the primary key to capture rows after an `UPDATE` and to delete
inserted rows on restore. By default it assumes the column `id`. You can configure it:

```php
<?php
$recorder = JournalRecorder::forTables('products', 'users')
    ->withPrimaryKey('products', 'sku')     // per table
    ->withDefaultPrimaryKey('id');          // for all other tables
```

When an inserted table has no usable generated ID, the journal falls back to the values
parsed from the INSERT statement, and the restore deletes the row by matching all
captured values.

## Strict Mode

By default the recorder is *best-effort*: a write statement it cannot parse is executed
normally but **not journaled** — which means it will **not be restored**. For the
setUp/tearDown use case this silent gap can leak state to the next test.

Strict mode turns those silent gaps into loud failures:

```php
<?php
$recorder = JournalRecorder::forAllTables()->strict();
$executor->addObserver($recorder);

$executor->execute("INSERT INTO archive SELECT * FROM users");
// => JournalException: cannot journal statement (unsupported DML shape).
//    The statement was NOT executed.
```

In strict mode the recorder throws a `JournalException` when:

- a **write statement cannot be parsed** (`INSERT ... SELECT`, multi-table UPDATE,
  multi-statement SQL, `REPLACE`, `MERGE`, `TRUNCATE`). Thrown **before** the statement
  executes, so the database is not modified. Because the target table cannot be
  determined, this applies even when watching only specific tables;
- the rows affected by an `UPDATE`/`DELETE` **cannot be captured** (also thrown before
  execution);
- the values of an `INSERT` **cannot be determined** (no generated ID and no parseable
  values). This one is thrown **after** execution — the row was inserted — but the test
  fails at the exact statement instead of leaking state silently.

Non-write statements (SELECT, `CREATE TABLE`, `DROP`, `ALTER`, etc.) are never affected,
so fixtures can still be built while recording.

Use strict mode whenever the journal is your restore mechanism; use the default lenient
mode when the journal is informational (audit/debugging).

## How Old/New Values are Captured

- `UPDATE`: the affected rows are selected (`SELECT * ... WHERE <same where clause>`)
  before the operation; after the operation the rows are re-selected by primary key,
  so the capture works even if the UPDATE changes columns referenced in the WHERE clause.
- `DELETE`: the affected rows are selected before the operation.
- `INSERT`: after the operation the row is selected by the generated ID
  (falling back to the values parsed from the statement).

All the extra SELECTs run on the same connection, so they see the data inside
the current transaction, if any.

## Limitations

- Only **single-table DML** statements are recognized: `INSERT INTO ... VALUES (...)`,
  `UPDATE ... SET ... [WHERE ...]` and `DELETE FROM ... [WHERE ...]`.
  Multi-statement SQL, `INSERT ... SELECT`, multi-table UPDATEs and CTEs are **not** journaled
  (enable [strict mode](#strict-mode) to fail loudly instead of skipping them).
- Statements executed outside the observed `DatabaseExecutor` (another connection,
  stored procedures, triggers) are not seen by the journal.
- The journal is attached to a **specific executor instance**: writes made through another
  `DatabaseExecutor` (even one created from the same driver) are not recorded. Make sure the
  code under test uses the executor the recorder is attached to.
- If the test code rolls back its own transaction, the journal still holds the entries of the
  rolled-back statements; restoring them may re-apply unwanted changes. Clear the recorder
  (`$recorder->clear()`) after a rollback.
- Capturing values requires extra SELECT statements around each write; do not attach
  the recorder in performance-sensitive production paths.

## See Also

- [Executor Observers](observers.md)
- [DatabaseExecutor](database-executor.md)1