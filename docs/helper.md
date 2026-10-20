---
sidebar_position: 7
---

# SQL Dialect Helper

The AnyDataset library provides a SQL dialect interface, `ByJG\AnyDataset\Db\Interfaces\SqlDialectInterface`, which
returns
database-specific SQL operations based on the current database connection.

## Available Methods

| Method                                                                                   | Description                                                                         | Return Type          |
|------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|----------------------|
| `concat($str1, $str2 = null)`                                                            | Returns the proper concatenation operation for the current connection.              | string               |
| `limit($sql, $start, $qty)`                                                              | Returns the SQL query with the correct `LIMIT` clause for the current connection.   | string               |
| `top($sql, $qty)`                                                                        | Returns the SQL query with the correct `TOP` clause for the current connection.     | string               |
| `hasTop()`                                                                               | Returns `true` if the current connection supports `TOP`.                            | bool                 |
| `hasLimit()`                                                                             | Returns `true` if the current connection supports `LIMIT`.                          | bool                 |
| `sqlDate($format, $column = null)`                                                       | Returns the proper function to format a date field based on the current connection. | string               |
| `executeAndGetInsertedId(DatabaseExecutor $executor, string\|SqlStatement $sql, $param)` | Executes a SQL query and returns the inserted ID .                                  | mixed                |
| `getSqlLastInsertId()`                                                                   | Returns the SQL that reads the last inserted ID (used by `executeAndGetInsertedId()`). | string               |
| `delimiterField(string\|array $field)`                                                   | Returns the field name with the correct field delimiter for the current connection. | string\|array        |
| `delimiterTable($table)`                                                                 | Returns the table name with the correct table delimiter for the current connection. | string               |
| `forUpdate($sql)`                                                                        | Returns the SQL query with the `FOR UPDATE` clause for the current connection.      | string               |
| `hasForUpdate()`                                                                         | Returns `true` if the current connection supports `FOR UPDATE`.                     | bool                 |
| `getTableMetadata($executor, $tableName)`                                                | The columns of the table, keyed by lower-case name.                                 | ColumnMetadata[]     |
| `getTableIndexes($executor, $tableName)`                                                 | The indexes of the table, without the primary key.                                  | IndexMetadata[]      |
| `getTableForeignKeys($executor, $tableName)`                                             | The foreign keys of the table.                                                      | ForeignKeyMetadata[] |
| `getIsolationLevelCommand($isolationLevel = null)`                                       | Returns the SQL command to set the transaction isolation level.                     | string               |
| `getJoinTablesUpdate($tables)`                                                           | Returns the tables to be updated in a JOIN statement.                               | array                |
| `getPingSql()`                                                                           | Returns the statement used to check that the connection is alive.                   | string               |

## Table Metadata

`getTableMetadata()`, `getTableIndexes()` and `getTableForeignKeys()` describe an existing table, and are
implemented for MySQL, SQLite (and Cloudflare D1), PostgreSQL, SQL Server, Oracle and DuckDB. They return objects
from `ByJG\AnyDataset\Db\Metadata`, with read-only public properties. Names are returned the way the
database stores them: PostgreSQL folds unquoted names to lower case, Oracle to upper case.

```php
$executor = \ByJG\AnyDataset\Db\DatabaseExecutor::using($dbDriver);
$dialect = $executor->getHelper();

foreach ($dialect->getTableMetadata($executor, 'pet') as $column) {
    // ColumnMetadata: name, dbType, required, default, phpType, length, precision,
    // primaryKey, autoIncrement, generated
    echo $column->name, $column->primaryKey ? ' (primary key)' : '', "\n";
}

foreach ($dialect->getTableIndexes($executor, 'pet') as $index) {
    // IndexMetadata: name, columns (in index order), unique
    echo $index->name, ': ', implode(', ', $index->columns), "\n";
}

foreach ($dialect->getTableForeignKeys($executor, 'pet') as $foreignKey) {
    // ForeignKeyMetadata: columns[i] references parentColumns[i] in parentTable
    echo implode(', ', $foreignKey->columns), ' -> ', $foreignKey->parentTable, "\n";
}
```

- `autoIncrement` is true when the database fills in the value: `auto_increment` (MySQL), `IDENTITY`
  (SQL Server, Oracle), `SERIAL` or identity (PostgreSQL), and a single-column `INTEGER PRIMARY KEY`
  in SQLite, which is an alias of the rowid, and a primary key whose default is `nextval()` of a
  sequence (DuckDB).
- `generated` is true for a column the database computes from an expression: a generated column
  (MySQL, SQLite, PostgreSQL), a computed column (SQL Server) or a virtual column (Oracle). DuckDB
  does not report it: its catalog shows the expression as the column's default.
- `getTableIndexes()` leaves out the index of the primary key, which `primaryKey` already reports.
  It includes the indexes the database creates by itself: MySQL adds one for a foreign key that has
  none, and a `UNIQUE` constraint is an index everywhere.

## Use Case

The `SqlDialectInterface` is especially useful when working with multiple database connections. It helps ensure that
SQL operations are dynamically adapted to the specific database being used, avoiding hardcoding database-specific
details in your code.

E.g.

```php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('...connection string...');
$dialect = $dbDriver->getSqlDialect();

// This will return the proper SQL with the TOP 10
// based on the current connection
$sql = $dialect->top("select * from foo", 10);

// This will return the proper concatenation operation
// based on the current connection
$concat = $dialect->concat("'This is '", "field1", "'concatenated'");


// This will return the proper function to format a date field
// based on the current connection
// These are the formats availables:
// Y => 4 digits year (e.g. 2022)
// y => 2 digits year (e.g. 22)
// M => Month fullname (e.g. January)
// m => Month with leading zero (e.g. 01)
// Q => Quarter
// q => Quarter with leading zero
// D => Day with leading zero (e.g. 01)
// d => Day (e.g. 1)
// h => Hour 12 hours format (e.g. 11)
// H => Hour 24 hours format (e.g. 23)
// i => Minute leading zero
// s => Seconds leading zero
// a => a/p
// A => AM/PM
$date = $dialect->sqlDate("d-m-Y H:i", "some_field_date");
$date2 = $dialect->sqlDate(BaseDialect::DMYH, "some_field_date"); // Same as above

// MySQL, PostgreSQL, SQL Server and Oracle support every letter above except Q/q, which only
// PostgreSQL and Oracle have, and a/p, which is AM/PM everywhere. SQLite's strftime() has no
// month names and no two-digit year: there M is the month number and y the four-digit year,
// d keeps the leading zero, and h is the 24-hour hour. DuckDB supports every letter except Q/q.


// This will return the fields with proper field delimiter
// based on the current connection
```
