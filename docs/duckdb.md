---
sidebar_position: 26
---

# Driver: DuckDB

[DuckDB](https://duckdb.org) is an embedded database, like SQLite: there is no server, it runs
inside the PHP process and keeps the data in a single file or in memory. Unlike SQLite, it is
built for analytics: it stores data by column and is fast at aggregations and scans over many
rows. It can also query CSV, JSON and Parquet files directly, without importing them.

Use it for reporting and analysis, not as the transactional database of an application.

The driver is `PdoDuckdb` and needs the `pdo_duckdb` extension. See
[Installing the PHP database extensions](installing-extensions.md#duckdb).

## Connecting

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;

// A database file, created if it does not exist
$executor = DatabaseExecutor::using(Factory::getDbInstance('duckdb:///path/to/database.duckdb'));

// An in-memory database, gone when the connection closes
$memory = DatabaseExecutor::using(Factory::getDbInstance('duckdb://'));
```

## Querying files

A file is a table in the `FROM` clause, and its path can be a parameter. No database file is
needed: an in-memory database reads the file directly.

```php
<?php
$executor = DatabaseExecutor::using(Factory::getDbInstance('duckdb://'));

$rows = $executor->getIterator(
    "select region, sum(amount) as total from read_csv(:file) group by region order by region",
    ['file' => '/data/sales.csv']
)->toArray();
```

`read_json()` and `read_parquet()` work the same way. The format is detected from the content, so
the file needs no extension. See the
[DuckDB documentation](https://duckdb.org/docs/stable/data/overview) for the options.

A database file is only needed to keep tables between connections, for example to import a file
once and query it later:

```php
<?php
$executor = DatabaseExecutor::using(Factory::getDbInstance('duckdb:///data/sales.duckdb'));
$executor->execute("CREATE TABLE sales AS SELECT * FROM read_csv('/data/sales.csv')");
```

## SQL dialect

DuckDB's SQL follows PostgreSQL's, and `DuckdbDialect` extends `PgsqlDialect`: identifiers are
quoted with `"`, `limit()` and `top()` use `LIMIT ... OFFSET`, and `concat()` uses `||`. What
differs:

- **`executeAndGetId()`** reads the id from the sequence in the default value of the primary key,
  since DuckDB has no `lastval()` and no auto increment column. Create the table this way:

  ```sql
  CREATE SEQUENCE seq_dogs;
  CREATE TABLE Dogs (Id INTEGER PRIMARY KEY DEFAULT nextval('seq_dogs'), Name VARCHAR);
  ```

  It returns `null` when the primary key has no sequence.
- **`sqlDate()`** uses `strftime()` and supports every letter of the [helper](helper.md) except
  `Q`/`q`: DuckDB has no quarter in its date formats.
- **`FOR UPDATE` is not available**: `hasForUpdate()` is `false` and `forUpdate()` throws a
  `NotAvailableException`.
- **Isolation levels are ignored.** DuckDB has a single level (snapshot isolation), so
  `beginTransaction()` accepts any `IsolationLevelEnum` and sends no command.

## Limitations

- **One connection per database file in a process.** `pdo_duckdb` opens a separate database for
  each connection, and two databases on the same file do not see each other's commits. Open the
  file once and share that `DatabaseExecutor`. Across processes, DuckDB allows one process to
  open a file for writing, or several to open it at the same time if all of them only read it.
- **A boolean column is read back as `1` or `0`**, as in SQLite.
- **`VARCHAR` has no length.** `VARCHAR(50)` is a `VARCHAR`, so the table metadata reports no
  length for it.
- **Generated columns are not reported as generated.** DuckDB's catalog shows the expression of a
  generated column as its default value, the same way as a regular default, so `getTableMetadata()`
  returns `generated: false` with the expression in `default`. DuckDB has only `VIRTUAL` generated
  columns.
- **The Journal records an INSERT without the id that the sequence generated.** It falls back to
  the values written in the statement, so give the id explicitly in the INSERT of a table that
  the Journal must restore.
- **No multiple rowsets.** `isSupportMultiRowset()` is `false`, as in SQLite.
