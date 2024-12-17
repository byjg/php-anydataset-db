---
sidebar_position: 13
---

# Pre-Fetch Records

By default, records are fetched from the database as you iterate over them using methods like `moveNext()`,
`toArray()`, or `foreach`.

It is possible to pre-fetch a specified number of records before starting the iteration.

```php
<?php
$sql = new SqlStatement("select * from table where field = :param");

// Pre-fetch 100 records
$iterator = $sql->getIterator($dbDriver, ['param' => 'value'], preFetch: 100);
```

or

```php
<?php
$iterator = $dbDriver->getIterator(
    "select * from table where field = :param", 
    ['param' => 'value'], 
    preFetch: 100
);
```

Both examples above fetch 100 records from the database and store them in memory.
When you iterate over the records, they are retrieved from memory instead of making additional
database queries.

## Use cases for pre-fetch:

### Small tables with a few records

If your table contains a small number of records, it is more efficient to fetch all records at once
and store them in memory during iteration. If the pre-fetch count exceeds the number of available records,
all records will be fetched and stored, releasing the database connection.
This allows iteration from memory, enhancing performance.

### Long processing time

For operations with long processing times between record iterations, pre-fetching records into memory
releases database resources earlier, improving efficiency.

## When not to use pre-fetch

Pre-fetching may lead to memory issues in certain scenarios. Avoid using pre-fetch if:

* Records contain too many fields (e.g., dozens of columns).
* Records include large fields, such as blobs or extensive text data.

## How it works

When you call `getIterator` with a pre-fetch value, the specified number of records is fetched
from the database and stored in memory. During iteration, records are retrieved from memory,
and new batches are fetched as needed.

Example:

* You have a table with 60 records and set the pre-fetch count to 50.
* Upon obtaining the iterator, the first 50 records are fetched from the database and stored in memory.
* Each record is retrieved from memory during iteration, while the next batch of records is fetched from the database as
  needed.
* After the 60th record, the database cursor is closed, and any remaining records are fetched directly from memory.


