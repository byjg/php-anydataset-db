# Helper - DbFunctions

AnyDataset has a helper `ByJG\AnyDataset\Db\DbFunctionsInterface` that can be return some specific data based on the database connection. 

Methods availables:

| Method                                                              | Description                                                                              |
|---------------------------------------------------------------------|------------------------------------------------------------------------------------------|
| concat($str1, $str2 = null)                                         | Return the proper concatenation operation based on the current connection                |
| limit($sql, $start, $qty)                                           | Return the proper SQL with the LIMIT based on the current connection                     |
| top($sql, $qty)                                                     | Return the proper SQL with the TOP based on the current connection                       |
| hasTop()                                                            | Return true if the current connection has TOP                                            |
| hasLimit()                                                          | Return true if the current connection has LIMIT                                          |
| sqlDate($format, $column = null)                                    | Return the proper function to format a date field based on the current connection        |
| toDate($date, $dateFormat)                                          | Return the proper function to convert a date to a string based on the current connection |
| fromDate($date, $dateFormat)                                        | Return the proper function to convert a string to a date based on the current connection |
| executeAndGetInsertedId(DbDriverInterface $dbdataset, $sql, $param) | Execute a SQL and return the inserted ID                                                 |
| delimiterField($field)                                              | Return the field with proper field delimiter based on the current connection             |
| delimiterTable($table)                                              | Return the table with proper table delimiter based on the current connection             |
| forUpdate($sql)                                                     | Return the SQL with the FOR UPDATE based on the current connection                       |
| hasForUpdate()                                                      | Return true if the current connection has FOR UPDATE                                     |


It is useful when you are working with different database connections and don't want to hard code the information there. 

E.g.

```php
$dbDriver = \ByJG\AnyDataset\Db\Factory::getDbRelationalInstance('...connection string...');
$dbHelper = $dbDriver->getDbHelper();

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
