# Pre Fetch records

By Default the records are fetched from the database when you iterate over the records using for example `moveNext()`,
`toArray()` or `foreach`.

You can pre-fetch a number of records when you get the iterator.

```php
<?php
$sql = new SqlStatement("select * from table where field = :param");

// Pre-fetch 100 records
$iterator = $sql->getIterator($dbDriver, ['param' => 'value'], preFetch: 100);
```

or

```php
<?php
$iterator = $dbDriver->getIterator($dbDriver, ['param' => 'value'], preFetch: 100);
```

The commands above will fetch 100 records from the database and store in memory.
When you iterate over the records, it will get the records from memory instead of the database.

## Use cases for pre-fetch:

### Small tables with a few records

If you have a small table with a few records, it is better to fetch all records at once and store in memory
while you iterate over the records.

### Long processing time

If you have a long processing time between the records, it is better to prefetch the records at once and store in memory
releasing database resources earlier.

## When not to use pre-fetch

In these cases, it is better to fetch the records from the database without pre-fetching, because you can have memory
issues:

* if your records are too large (e.g. dozens of columns)
* If you have a field like a blob or large text

## How it works

When you get the `getIterator` it will fetch the number of records you defined from the database and store in memory.
When you iterate over the records, it will get the records from memory instead of the database and fetch a new one and
store in memory.

e.g.:

* You have a table with 60 records and define the preFetch of 50.
* When you get the iterator, it will fetch 50 records from the database and store in memory.
* For each record you iterate over, it will get the record from memory and fetch a new one from the database.
* When you reach the 60th record, the iterator will close the cursor from the database, and allow you fetch the
  remaining records from memory. 


