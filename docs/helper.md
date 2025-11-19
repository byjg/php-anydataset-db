---
sidebar_position: 7
---

# Helper - DbFunctions

The AnyDataset library provides a helper interface, `ByJG\AnyDataset\Db\DbFunctionsInterface`, which returns
database-specific SQL operations based on the current database connection.

## Available Methods

| Method                                                                                   | Description                                                                         | Return Type   |
|------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|---------------|
| `concat($str1, $str2 = null)`                                                            | Returns the proper concatenation operation for the current connection.              | string        |
| `limit($sql, $start, $qty)`                                                              | Returns the SQL query with the correct `LIMIT` clause for the current connection.   | string        |
| `top($sql, $qty)`                                                                        | Returns the SQL query with the correct `TOP` clause for the current connection.     | string        |
| `hasTop()`                                                                               | Returns `true` if the current connection supports `TOP`.                            | bool          |
| `hasLimit()`                                                                             | Returns `true` if the current connection supports `LIMIT`.                          | bool          |
| `sqlDate($format, $column = null)`                                                       | Returns the proper function to format a date field based on the current connection. | string        |
| `executeAndGetInsertedId(DatabaseExecutor $executor, string\|SqlStatement $sql, $param)` | Executes a SQL query and returns the inserted ID .                                  | mixed         |
| `delimiterField(string\|array $field)`                                                   | Returns the field name with the correct field delimiter for the current connection. | string\|array |
| `delimiterTable($table)`                                                                 | Returns the table name with the correct table delimiter for the current connection. | string        |
| `forUpdate($sql)`                                                                        | Returns the SQL query with the `FOR UPDATE` clause for the current connection.      | string        |
| `hasForUpdate()`                                                                         | Returns `true` if the current connection supports `FOR UPDATE`.                     | bool          |
| `getTableMetadata($dbdataset, $tableName)`                                               | Returns metadata about the specified table.                                         | array         |
| `getIsolationLevelCommand($isolationLevel = null)`                                       | Returns the SQL command to set the transaction isolation level.                     | string        |
| `getJoinTablesUpdate($tables)`                                                           | Returns the tables to be updated in a JOIN statement.                               | array         |

## Use Case

The `DbFunctionsInterface` is especially useful when working with multiple database connections. It helps ensure that
SQL operations are dynamically adapted to the specific database being used, avoiding hardcoding database-specific
details in your code.

E.g.

```php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbInstance('...connection string...');
$dbHelper = $dbDriver->getSqlDialect();

// This will return the proper SQL with the TOP 10
// based on the current connection
$sql = $dbHelper->top("select * from foo", 10);

// This will return the proper concatenation operation
// based on the current connection
$concat = $dbHelper->concat("'This is '", "field1", "'concatenated'");


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
$date = $dbHelper->sqlDate("d-m-Y H:i", "some_field_date");
$date2 = $dbHelper->sqlDate(DbBaseFunctions::DMYH, "some_field_date"); // Same as above


// This will return the fields with proper field delimiter
// based on the current connection
```
